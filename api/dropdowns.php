<?php
require_once __DIR__ . '/../config/supabase.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'all';

switch ($action) {

    case 'all':
    case 'list':
        $campo = $_GET['campo'] ?? '';
        $filter = 'activo=eq.1&order=campo.asc,orden.asc';
        if ($campo) $filter = 'campo=eq.' . urlencode($campo) . '&activo=eq.1&order=orden.asc';

        $res     = sb_get('dropdown_opciones', $filter);
        if (($res['__code'] ?? $res['code'] ?? 200) !== 200 || isset($res['data']['message'])) {
            jsonResponse(['error' => 'Error en base de datos: ' . ($res['data']['message'] ?? 'Desconocido')], 500);
        }
        $grouped = [];
        foreach (($res['data'] ?? []) as $o) {
            if (is_array($o) && isset($o['campo'])) {
                $grouped[$o['campo']][] = ['id' => $o['id'], 'valor' => $o['valor'], 'color' => $o['color']];
            }
        }
        jsonResponse(['data' => $grouped], 200, true);


    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_dropdowns', $user)) jsonResponse(['error' => 'No autorizado'], 403);
        $input = getJsonInput();
        $campo = $input['campo'] ?? '';
        $valor = trim($input['valor'] ?? '');
        if (!$campo || !$valor) jsonResponse(['error' => 'Campo y valor requeridos'], 400);
        $maxRes = sb_get('dropdown_opciones', 'campo=eq.' . urlencode($campo) . '&select=orden&order=orden.desc&limit=1');
        $orden  = intval($maxRes['data'][0]['orden'] ?? 0) + 1;
        $res    = sb_post('dropdown_opciones', ['campo' => $campo, 'valor' => $valor, 'color' => $input['color'] ?? '#e53935', 'orden' => $orden]);
        jsonResponse(['success' => true, 'id' => $res['data'][0]['id'] ?? null], 201);

    case 'update':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_dropdowns', $user)) jsonResponse(['error' => 'No autorizado'], 403);
        $input = getJsonInput();
        $id    = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        $body  = array_intersect_key($input, array_flip(['valor','color','orden']));
        if (isset($body['valor'])) $body['valor'] = trim($body['valor']);
        sb_patch('dropdown_opciones', 'id=eq.' . $id, $body);
        jsonResponse(['success' => true]);

    case 'delete':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if ($user['rol'] !== 'admin') jsonResponse(['error' => 'No autorizado'], 403);
        $input = getJsonInput();
        sb_patch('dropdown_opciones', 'id=eq.' . intval($input['id'] ?? 0), ['activo' => 0]);
        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
