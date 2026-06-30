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
        $grouped = [];
        foreach (($res['data'] ?? []) as $o) {
            $grouped[$o['campo']][] = ['id' => $o['id'], 'valor' => $o['valor'], 'color' => $o['color']];
        }
        jsonResponse(['data' => $grouped]);

    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_dropdowns', $user)) jsonResponse(['error' => 'No autorizado'], 403);
        $input = getJsonInput();
        $campo = $input['campo'] ?? '';
        $valor = trim($input['valor'] ?? '');
        if (!$campo || !$valor) jsonResponse(['error' => 'Campo y valor requeridos'], 400);
        $maxRes = sb_get('dropdown_opciones', 'campo=eq.' . urlencode($campo) . '&select=orden&order=orden.desc&limit=1');
        $orden  = intval($maxRes['data'][0]['orden'] ?? 0) + 1;
        $res    = sb_post('dropdown_opciones', ['campo' => $campo, 'valor' => $valor, 'color' => $input['color'] ?? '#6366f1', 'orden' => $orden]);
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
        sb_patch('dropdown_opciones', 'id=eq.' . intval($input['id'] ?? 0), ['activo' => false]);
        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
