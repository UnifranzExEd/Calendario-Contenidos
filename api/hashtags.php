<?php
require_once __DIR__ . '/../config/supabase.php';
$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
switch ($action) {
    case 'list':
        $red = $_GET['red_social'] ?? '';
        $f   = $red ? 'red_social=eq.' . urlencode($red) . '&activo=eq.1&order=tag.asc' : 'activo=eq.1&order=red_social.asc,tag.asc';
        $res = sb_get('hashtags', $f);
        jsonResponse(['data' => $res['data'] ?? []]);
    case 'by_contenido':
        $cid = intval($_GET['contenido_id'] ?? 0);
        $res = sb_get('contenido_hashtags', 'contenido_id=eq.' . $cid . '&select=hashtags(id,tag,categoria,red_social)');
        $tags = array_map(fn($r) => $r['hashtags'], $res['data'] ?? []);
        jsonResponse(['data' => $tags]);
    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        if (empty($input['tag'])) jsonResponse(['error' => 'Tag requerido'], 400);
        $res = sb_post('hashtags', ['tag' => ltrim($input['tag'], '#'), 'categoria' => $input['categoria'] ?? null, 'red_social' => $input['red_social'] ?? null, 'activo' => 1]);
        jsonResponse(['success' => true, 'id' => $res['data'][0]['id'] ?? null], 201);
    case 'delete':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        sb_patch('hashtags', 'id=eq.' . intval($input['id'] ?? 0), ['activo' => 0]);
        jsonResponse(['success' => true]);
    case 'attach':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $cid   = intval($input['contenido_id'] ?? 0);
        $hid   = intval($input['hashtag_id']   ?? 0);
        sb_post('contenido_hashtags', ['contenido_id' => $cid, 'hashtag_id' => $hid]);
        jsonResponse(['success' => true]);
    case 'detach':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        sb_delete('contenido_hashtags', 'contenido_id=eq.' . intval($input['contenido_id'] ?? 0) . '&hashtag_id=eq.' . intval($input['hashtag_id'] ?? 0));
        jsonResponse(['success' => true]);
    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
