<?php
require_once __DIR__ . '/../config/supabase.php';

$action = $_GET['action'] ?? '';

if ($action === 'stats') {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    if ($apiKey !== STATS_API_KEY) {
        jsonResponse(['error' => 'No autorizado'], 401);
    }
    
    $res = sb_get('contenidos', 'select=estado,red_social,prioridad,fecha');
    $contenidos = $res['data'] ?? [];
    
    $stats = [
        'total_piezas' => count($contenidos),
        'por_estado' => [],
        'por_red' => [],
        'por_prioridad' => []
    ];
    
    foreach ($contenidos as $c) {
        $estado = $c['estado'] ?? 'Sin Estado';
        $red = $c['red_social'] ?? 'Otra';
        $prioridad = $c['prioridad'] ?? 'Media';
        
        $stats['por_estado'][$estado] = ($stats['por_estado'][$estado] ?? 0) + 1;
        $stats['por_red'][$red] = ($stats['por_red'][$red] ?? 0) + 1;
        $stats['por_prioridad'][$prioridad] = ($stats['por_prioridad'][$prioridad] ?? 0) + 1;
    }
    
    jsonResponse(['success' => true, 'data' => $stats]);

} elseif ($action === 'organicos') {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    if ($apiKey !== ORGANICOS_API_KEY) {
        jsonResponse(['error' => 'No autorizado'], 401);
    }
    
    $mes = $_GET['mes'] ?? '';
    if (!$mes) jsonResponse(['error' => 'Parámetro mes requerido'], 400);
    
    $res = sb_get('contenidos', 'semana=eq.' . urlencode($mes) . '&select=tema,fecha,postproductor_id,estado,red_social');
    $contenidos = $res['data'] ?? [];
    
    jsonResponse(['success' => true, 'data' => $contenidos]);
}

jsonResponse(['error' => 'Endpoint no válido'], 400);
