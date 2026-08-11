<?php
require_once __DIR__ . '/../config/supabase.php';

// Auth: session or API Key
$apiKey = $_GET['api_key'] ?? null;
if (!$apiKey) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $apiKey = $matches[1];
    }
}

$user = null;
if ($apiKey === ORGANICOS_API_KEY) {
    // API key mode: system acts as admin
    $user = ['id' => 1, 'rol' => 'admin', 'nombre' => 'API System'];
} else {
    // Session mode
    $user = requireAuth();
}

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();

switch ($action) {
    case 'list':
        $proyecto_id = $_GET['proyecto_id'] ?? null;
        $filter = 'deleted_at=is.null';
        if ($proyecto_id) {
            $filter .= '&proyecto_id=eq.' . intval($proyecto_id);
        }
        // Complex ordering in Supabase REST API isn't fully possible through single order param with CASE, 
        // we'll fetch and sort in PHP.
        $res = sb_get('microtareas', $filter);
        $data = $res['data'] ?? [];
        
        $mtIds = array_column($data, 'id');
        $checklists = [];
        if (!empty($mtIds)) {
            $chRes = sb_get('microtareas_items', 'microtarea_id=in.(' . implode(',', $mtIds) . ')');
            foreach ($chRes['data'] ?? [] as $ch) {
                $checklists[$ch['microtarea_id']][] = $ch;
            }
        }
        
        $usersRes = sb_get('usuarios', 'select=id,nombre');
        $usersMap = [];
        foreach ($usersRes['data'] ?? [] as $u) {
            $usersMap[$u['id']] = $u['nombre'];
        }

        foreach ($data as &$row) {
            $row['responsable_nombre'] = $usersMap[$row['responsable_id']] ?? '';
            $row['creador_nombre'] = $usersMap[$row['creado_por']] ?? '';
            $row['checklist'] = $checklists[$row['id']] ?? [];
        }
        unset($row);

        usort($data, function($a, $b) {
            $prioMap = ['Alta' => 0, 'Media' => 1, 'Baja' => 2];
            $pa = $prioMap[$a['prioridad'] ?? 'Media'] ?? 1;
            $pb = $prioMap[$b['prioridad'] ?? 'Media'] ?? 1;
            if ($pa !== $pb) return $pa - $pb;
            
            $da = $a['fecha_entrega'] ?: '9999-12-31';
            $db = $b['fecha_entrega'] ?: '9999-12-31';
            if ($da !== $db) return strcmp($da, $db);
            
            return $b['id'] - $a['id'];
        });

        jsonResponse(['data' => $data, 'total' => count($data)]);
        break;

    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (empty($input['titulo'])) jsonResponse(['error' => 'El título es requerido'], 400);

        $body = [
            'titulo' => $input['titulo'],
            'descripcion' => $input['descripcion'] ?? null,
            'responsable_id' => $input['responsable_id'] ?? null,
            'proyecto_id' => $input['proyecto_id'] ?? null,
            'fecha_entrega' => $input['fecha_entrega'] ?? null,
            'prioridad' => $input['prioridad'] ?? 'Media',
            'estado' => $input['estado'] ?? 'Pendiente',
            'creado_por' => $user['id']
        ];
        
        $res = sb_post('microtareas', $body);
        if (empty($res['data'])) jsonResponse(['error' => 'Error creando microtarea'], 500);
        $mt = $res['data'][0];

        $checklist = $input['checklist'] ?? [];
        if (!empty($checklist)) {
            foreach ($checklist as $item) {
                $texto = is_string($item) ? $item : ($item['texto'] ?? '');
                $comp = is_array($item) && !empty($item['completada']) ? 1 : 0;
                if ($texto) {
                    sb_post('microtareas_items', [
                        'microtarea_id' => $mt['id'],
                        'texto' => $texto,
                        'completada' => $comp
                    ]);
                }
            }
        }
        
        jsonResponse(['success' => true, 'data' => $mt]);
        break;

    case 'update':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        $id = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        $body = [];
        if (isset($input['titulo'])) $body['titulo'] = $input['titulo'];
        if (isset($input['descripcion'])) $body['descripcion'] = $input['descripcion'];
        if (isset($input['estado'])) $body['estado'] = $input['estado'];
        if (isset($input['prioridad'])) $body['prioridad'] = $input['prioridad'];
        if (array_key_exists('fecha_entrega', $input)) $body['fecha_entrega'] = $input['fecha_entrega'];
        if (isset($input['responsable_id'])) $body['responsable_id'] = $input['responsable_id'];
        
        if (!empty($body)) {
            $body['updated_at'] = date('c');
            sb_patch('microtareas', 'id=eq.' . $id, $body);
        }

        if (isset($input['checklist'])) {
            $keepIds = [];
            foreach ($input['checklist'] as $item) {
                if (!empty($item['id'])) {
                    $keepIds[] = $item['id'];
                    sb_patch('microtareas_items', 'id=eq.' . intval($item['id']), [
                        'texto' => $item['texto'] ?? '',
                        'completada' => intval($item['completada'] ?? 0)
                    ]);
                } else if (!empty($item['texto'])) {
                    $ires = sb_post('microtareas_items', [
                        'microtarea_id' => $id,
                        'texto' => $item['texto'],
                        'completada' => intval($item['completada'] ?? 0)
                    ]);
                    if (!empty($ires['data'][0]['id'])) {
                        $keepIds[] = $ires['data'][0]['id'];
                    }
                }
            }
            
            // Delete removed items
            $chRes = sb_get('microtareas_items', 'microtarea_id=eq.' . $id);
            foreach ($chRes['data'] ?? [] as $ch) {
                if (!in_array($ch['id'], $keepIds)) {
                    sb_delete('microtareas_items', 'id=eq.' . $ch['id']);
                }
            }
        }
        
        jsonResponse(['success' => true]);
        break;

    case 'delete':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        $id = intval($input['id'] ?? 0);
        if ($id) {
            sb_patch('microtareas', 'id=eq.' . $id, ['deleted_at' => date('c')]);
        }
        jsonResponse(['success' => true]);
        break;

    case 'add_item':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        $res = sb_post('microtareas_items', [
            'microtarea_id' => intval($input['microtarea_id'] ?? 0),
            'texto' => $input['texto'] ?? '',
            'completada' => 0
        ]);
        jsonResponse(['success' => true, 'item' => $res['data'][0] ?? null]);
        break;

    case 'toggle_item':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        sb_patch('microtareas_items', 'id=eq.' . intval($input['id'] ?? 0), [
            'completada' => intval($input['completada'] ?? 0)
        ]);
        jsonResponse(['success' => true]);
        break;

    case 'delete_item':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        sb_delete('microtareas_items', 'id=eq.' . intval($input['id'] ?? 0));
        jsonResponse(['success' => true]);
        break;

    case 'stats':
        $res = sb_get('microtareas', 'deleted_at=is.null&select=estado,prioridad');
        $data = $res['data'] ?? [];
        
        $stats = [
            'Pendiente' => ['total' => 0, 'Alta' => 0, 'Media' => 0, 'Baja' => 0],
            'En proceso' => ['total' => 0, 'Alta' => 0, 'Media' => 0, 'Baja' => 0],
            'Completada' => ['total' => 0, 'Alta' => 0, 'Media' => 0, 'Baja' => 0]
        ];
        
        foreach ($data as $row) {
            $est = $row['estado'];
            $prio = ucfirst(strtolower($row['prioridad'] ?? 'Media'));
            if (!isset($stats[$est])) $stats[$est] = ['total' => 0, 'Alta' => 0, 'Media' => 0, 'Baja' => 0];
            $stats[$est]['total']++;
            if (isset($stats[$est][$prio])) {
                $stats[$est][$prio]++;
            }
        }
        
        jsonResponse(['success' => true, 'stats' => $stats]);
        break;

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
