<?php
require_once __DIR__ . '/../config/supabase.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

switch ($action) {

    case 'all':
        // All campos grouped by pestana slug (initial load)
        $campos   = sb_get('pestana_campos', 'visible=eq.1&order=orden.asc&select=*,pestanas(slug)');
        $grouped  = [];
        foreach (($campos['data'] ?? []) as $r) {
            $slug = $r['pestanas']['slug'] ?? 'default';
            unset($r['pestanas']);
            $grouped[$slug][] = $r;
        }
        jsonResponse(['data' => $grouped]);

    case 'list':
        $pestana = $_GET['pestana'] ?? '';
        if ($pestana) {
            $pRes = sb_get('pestanas', 'slug=eq.' . urlencode($pestana) . '&select=id');
            $pid  = $pRes['data'][0]['id'] ?? 0;
            $res  = sb_get('pestana_campos', 'pestana_id=eq.' . $pid . '&visible=eq.1&order=orden.asc');
        } else {
            $res = sb_get('pestana_campos', 'visible=eq.1&order=orden.asc&select=*,pestanas(slug)');
        }
        jsonResponse(['data' => $res['data'] ?? []]);

    case 'pestanas':
        $res = sb_get('pestanas', 'activo=eq.1&order=orden.asc');
        jsonResponse(['data' => $res['data'] ?? []]);

    case 'update':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_campos', $user)) jsonResponse(['error' => 'No autorizado'], 403);
        $input = getJsonInput();
        $id    = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        $body = array_intersect_key($input, array_flip(['nombre_display','visible','orden','ancho']));
        sb_patch('pestana_campos', 'id=eq.' . $id, $body);
        jsonResponse(['success' => true]);

    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_campos', $user)) jsonResponse(['error' => 'No autorizado'], 403);
        $input = getJsonInput();
        $pRes  = sb_get('pestanas', 'slug=eq.' . urlencode($input['pestana'] ?? '') . '&select=id');
        $pid   = $pRes['data'][0]['id'] ?? 0;
        if (!$pid) jsonResponse(['error' => 'Pestaña no válida'], 400);
        $maxRes = sb_get('pestana_campos', 'pestana_id=eq.' . $pid . '&select=orden&order=orden.desc&limit=1');
        $orden  = (intval($maxRes['data'][0]['orden'] ?? 0)) + 1;
        $res = sb_post('pestana_campos', [
            'pestana_id'     => $pid,
            'nombre_campo'   => $input['nombre_campo']   ?? 'custom_' . time(),
            'nombre_display' => $input['nombre_display'] ?? 'Nuevo Campo',
            'tipo_campo'     => $input['tipo_campo']     ?? 'texto',
            'dropdown_grupo' => $input['dropdown_grupo'] ?? null,
            'orden'          => $orden,
            'ancho'          => $input['ancho']          ?? '150px',
        ]);
        jsonResponse(['success' => true, 'id' => $res['data'][0]['id'] ?? null], 201);

    case 'delete':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_campos', $user)) jsonResponse(['error' => 'No autorizado'], 403);
        $input = getJsonInput();
        sb_patch('pestana_campos', 'id=eq.' . intval($input['id'] ?? 0), ['visible' => false]);
        jsonResponse(['success' => true]);

    case 'update_pestana':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_campos', $user)) jsonResponse(['error' => 'No autorizado'], 403);
        $input = getJsonInput();
        $id    = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        $body  = array_intersect_key($input, array_flip(['nombre','color','enlace_carpeta_base']));
        sb_patch('pestanas', 'id=eq.' . $id, $body);
        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
