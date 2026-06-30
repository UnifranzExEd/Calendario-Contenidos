<?php
require_once __DIR__ . '/../config/supabase.php';
$user   = requireAuth();
$action = $_GET['action'] ?? 'list';
switch ($action) {
    case 'list':
        $cid = intval($_GET['contenido_id'] ?? 0);
        if (!$cid) jsonResponse(['error' => 'contenido_id requerido'], 400);
        $res = sb_get('historial_estado', 'contenido_id=eq.' . $cid . '&order=created_at.desc&limit=50');
        jsonResponse(['data' => $res['data'] ?? []]);
    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
