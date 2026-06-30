<?php
require_once __DIR__ . '/../config/supabase.php';
$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'get';
switch ($action) {
    case 'get':
        $cid = intval($_GET['contenido_id'] ?? 0);
        $res = sb_get('metricas', 'contenido_id=eq.' . $cid . '&order=fecha_registro.desc&limit=1');
        jsonResponse(['data' => $res['data'][0] ?? null]);
    case 'save':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $cid   = intval($input['contenido_id'] ?? 0);
        if (!$cid) jsonResponse(['error' => 'contenido_id requerido'], 400);
        $exists = sb_get('metricas', 'contenido_id=eq.' . $cid . '&limit=1');
        $body   = array_intersect_key($input, array_flip(['contenido_id','alcance','impresiones','interacciones','clicks','guardados','compartidos','comentarios','seguidores_ganados','reproducciones','fecha_registro']));
        if (!empty($exists['data'])) {
            sb_patch('metricas', 'contenido_id=eq.' . $cid, $body);
        } else {
            sb_post('metricas', $body);
        }
        jsonResponse(['success' => true]);
    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
