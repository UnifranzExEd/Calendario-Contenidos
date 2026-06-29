<?php
/**
 * API: Slides de contenido
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {
    case 'list':
        $contenido_id = intval($_GET['contenido_id'] ?? 0);
        if (!$contenido_id) jsonResponse(['error' => 'ID contenido requerido'], 400);
        
        $stmt = $db->prepare("SELECT * FROM contenido_slides WHERE contenido_id = ? ORDER BY numero_slide ASC");
        $stmt->execute([$contenido_id]);
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'save':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        
        $input = getJsonInput();
        $contenido_id = intval($input['contenido_id'] ?? 0);
        $slides = $input['slides'] ?? [];
        
        if (!$contenido_id) jsonResponse(['error' => 'ID contenido requerido'], 400);
        
        // Delete existing and re-insert
        $db->prepare("DELETE FROM contenido_slides WHERE contenido_id = ?")->execute([$contenido_id]);
        
        $stmt = $db->prepare("INSERT INTO contenido_slides (contenido_id, numero_slide, texto, notas) VALUES (?, ?, ?, ?)");
        foreach ($slides as $i => $slide) {
            $stmt->execute([$contenido_id, $i + 1, $slide['texto'] ?? '', $slide['notas'] ?? null]);
        }
        
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
