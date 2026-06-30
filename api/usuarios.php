<?php
require_once __DIR__ . '/../config/supabase.php';
$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

// postproductores is accessible to all logged-in users
// all other actions require admin
if ($action !== 'postproductores' && $user['rol'] !== 'admin') {
    jsonResponse(['error' => 'Solo administradores'], 403);
}

switch ($action) {

    case 'postproductores':
        // Return all active users that can act as post-producers
        $res = sb_get('usuarios', 'activo=eq.1&order=nombre.asc&select=id,nombre,rol');
        $all = $res['data'] ?? [];
        $pp  = array_values(array_filter($all, function($u) {
            return in_array($u['rol'], ['postproductor', 'admin'], true);
        }));
        jsonResponse(['data' => $pp]);

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        $res = sb_get('usuarios', 'id=eq.' . $id);
        if (empty($res['data'])) jsonResponse(['error' => 'No encontrado'], 404);
        jsonResponse(['data' => $res['data'][0]]);

    case 'list':
        $res = sb_get('usuarios', 'order=nombre.asc');
        jsonResponse(['data' => $res['data'] ?? []]);

    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        if (empty($input['email']) || empty($input['password'])) {
            jsonResponse(['error' => 'Email y contraseña requeridos'], 400);
        }
        $res = sb_post('usuarios', [
            'nombre'   => trim($input['nombre']   ?? ''),
            'email'    => trim($input['email']),
            'password' => password_hash($input['password'], PASSWORD_BCRYPT),
            'rol'      => $input['rol']      ?? 'community',
            'activo'   => 1,
        ]);
        if ($res['code'] >= 400 || empty($res['data'])) {
            $errMsg = 'Error al crear usuario';
            if (is_array($res['data']) && isset($res['data']['message'])) $errMsg = $res['data']['message'];
            if (is_array($res['data']) && isset($res['data']['details'])) $errMsg .= ': ' . $res['data']['details'];
            jsonResponse(['error' => $errMsg], 422);
        }
        jsonResponse(['success' => true, 'id' => $res['data'][0]['id'] ?? null], 201);

    case 'update':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $id    = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        $body  = array_intersect_key($input, array_flip(['nombre','email','rol','avatar']));
        $body['activo'] = isset($input['activo']) ? intval($input['activo']) : 1;
        if (!empty($input['password'])) {
            $body['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
        }
        sb_patch('usuarios', 'id=eq.' . $id, $body);
        jsonResponse(['success' => true]);

    case 'delete':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $id    = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        sb_patch('usuarios', 'id=eq.' . $id, ['activo' => 0]);
        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Acción no válida: ' . htmlspecialchars($action)], 400);
}
