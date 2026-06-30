<?php
require_once __DIR__ . '/../config/supabase.php';
$user   = requireAuth();
if ($user['rol'] !== 'admin') jsonResponse(['error' => 'Solo administradores'], 403);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
switch ($action) {
    case 'list':
        $res = sb_get('usuarios', 'order=nombre.asc');
        jsonResponse(['data' => $res['data'] ?? []]);
    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        if (empty($input['email']) || empty($input['password'])) jsonResponse(['error' => 'Email y contraseña requeridos'], 400);
        $res = sb_post('usuarios', [
            'nombre'   => $input['nombre']   ?? '',
            'email'    => $input['email'],
            'password' => password_hash($input['password'], PASSWORD_BCRYPT),
            'rol'      => $input['rol']      ?? 'community',
            'activo'   => true,
        ]);
        jsonResponse(['success' => true, 'id' => $res['data'][0]['id'] ?? null], 201);
    case 'update':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $id    = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        $body  = array_intersect_key($input, array_flip(['nombre','email','rol','activo','avatar']));
        if (!empty($input['password'])) $body['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
        sb_patch('usuarios', 'id=eq.' . $id, $body);
        jsonResponse(['success' => true]);
    case 'delete':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        sb_patch('usuarios', 'id=eq.' . intval($input['id'] ?? 0), ['activo' => 0]);
        jsonResponse(['success' => true]);
    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
