<?php
require_once __DIR__ . '/../config/supabase.php';
$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
switch ($action) {
    case 'list':
        $cid = intval($_GET['contenido_id'] ?? 0);
        $res = sb_get('contenido_slides', 'contenido_id=eq.' . $cid . '&order=orden.asc');
        jsonResponse(['data' => $res['data'] ?? []]);
    case 'save':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $cid   = intval($input['contenido_id'] ?? 0);
        if (!$cid) jsonResponse(['error' => 'contenido_id requerido'], 400);
        sb_delete('contenido_slides', 'contenido_id=eq.' . $cid);
        foreach (($input['slides'] ?? []) as $i => $slide) {
            sb_post('contenido_slides', ['contenido_id' => $cid, 'orden' => $i+1, 'texto' => $slide['texto'] ?? '', 'notas' => $slide['notas'] ?? null]);
        }
        jsonResponse(['success' => true]);
    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
