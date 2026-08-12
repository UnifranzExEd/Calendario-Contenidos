<?php
/**
 * external.php — Multi-purpose merged endpoint
 * Routes: historial | notificaciones | stats (legacy) | organicos (legacy)
 * Merged to stay within Vercel Hobby 12-function limit.
 */
require_once __DIR__ . '/../config/supabase.php';

$action = $_GET['action'] ?? '';
$api    = $_GET['api']    ?? '';   // route selector: historial | notificaciones
$method = $_SERVER['REQUEST_METHOD'];

// ── Historial ─────────────────────────────────────────────────────────
if ($api === 'historial') {
    $user = requireAuth();
    switch ($action) {
        case 'list':
            $cid = intval($_GET['contenido_id'] ?? 0);
            if (!$cid) jsonResponse(['error' => 'contenido_id requerido'], 400);
            $res = sb_get('historial_estado', 'contenido_id=eq.' . $cid . '&order=created_at.desc&limit=50');
            jsonResponse(['data' => $res['data'] ?? []]);
        default:
            jsonResponse(['error' => 'Acción no válida'], 400);
    }
}

// ── Notificaciones ────────────────────────────────────────────────────
if ($api === 'notificaciones') {
    $user = requireAuth();
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
            if ($id) sb_patch('notificaciones', 'id=eq.' . $id, ['leida' => 1]);
            else     sb_patch('notificaciones', 'usuario_id=eq.' . $user['id'], ['leida' => 1]);
            jsonResponse(['success' => true]);
        default:
            jsonResponse(['error' => 'Acción no válida'], 400);
    }
}

// ── Legacy: stats ─────────────────────────────────────────────────────
if ($action === 'stats') {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    if ($apiKey !== STATS_API_KEY) { jsonResponse(['error' => 'No autorizado'], 401); }
    $res = sb_get('contenidos', 'select=estado,red_social,prioridad,fecha');
    $contenidos = $res['data'] ?? [];
    $stats = ['total_piezas' => count($contenidos), 'por_estado' => [], 'por_red' => [], 'por_prioridad' => []];
    foreach ($contenidos as $c) {
        $stats['por_estado'][$c['estado'] ?? 'Sin Estado'] = ($stats['por_estado'][$c['estado'] ?? 'Sin Estado'] ?? 0) + 1;
        $stats['por_red'][$c['red_social'] ?? 'Otra']      = ($stats['por_red'][$c['red_social'] ?? 'Otra']      ?? 0) + 1;
        $stats['por_prioridad'][$c['prioridad'] ?? 'Media']= ($stats['por_prioridad'][$c['prioridad'] ?? 'Media']?? 0) + 1;
    }
    jsonResponse(['success' => true, 'data' => $stats]);
}

// ── Legacy: organicos ─────────────────────────────────────────────────
if ($action === 'organicos') {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    if ($apiKey !== ORGANICOS_API_KEY) { jsonResponse(['error' => 'No autorizado'], 401); }
    $mes = $_GET['mes'] ?? '';
    if (!$mes) jsonResponse(['error' => 'Parámetro mes requerido'], 400);
    $res = sb_get('contenidos', 'semana=eq.' . urlencode($mes) . '&select=tema,fecha,postproductor_id,estado,red_social');
    jsonResponse(['success' => true, 'data' => $res['data'] ?? []]);
}

jsonResponse(['error' => 'Endpoint no válido'], 400);
