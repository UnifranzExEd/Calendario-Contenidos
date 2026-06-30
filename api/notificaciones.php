<?php
require_once __DIR__ . '/../config/supabase.php';
$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
switch ($action) {
    case 'list':
        $res = sb_get('notificaciones', 'usuario_id=eq.' . $user['id'] . '&order=created_at.desc&limit=30');
        jsonResponse(['data' => $res['data'] ?? []]);
    case 'count':
        $res = sb_get('notificaciones', 'usuario_id=eq.' . $user['id'] . '&leida=eq.false&select=id');
        jsonResponse(['count' => count($res['data'] ?? [])]);
    case 'read':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $id    = intval($input['id'] ?? 0);
        if ($id) sb_patch('notificaciones', 'id=eq.' . $id, ['leida' => true]);
        else     sb_patch('notificaciones', 'usuario_id=eq.' . $user['id'], ['leida' => true]);
        jsonResponse(['success' => true]);
    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
