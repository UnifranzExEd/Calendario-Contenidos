<?php
/**
 * API: Historial de cambios de estado
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireAuth();
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {
    case 'list':
        $contenido_id = intval($_GET['contenido_id'] ?? 0);
        if (!$contenido_id) jsonResponse(['error' => 'ID contenido requerido'], 400);
        
        $stmt = $db->prepare("SELECT h.*, u.nombre as usuario_nombre 
                              FROM historial_estado h 
                              LEFT JOIN usuarios u ON h.usuario_id = u.id 
                              WHERE h.contenido_id = ? 
                              ORDER BY h.created_at DESC");
        $stmt->execute([$contenido_id]);
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'recent':
        // Recent activity across all content
        $limit = intval($_GET['limit'] ?? 20);
        $stmt = $db->prepare("SELECT h.*, u.nombre as usuario_nombre, c.tema as contenido_tema, p.nombre as pestana_nombre
                              FROM historial_estado h 
                              LEFT JOIN usuarios u ON h.usuario_id = u.id 
                              LEFT JOIN contenidos c ON h.contenido_id = c.id 
                              LEFT JOIN pestanas p ON c.pestana_id = p.id
                              ORDER BY h.created_at DESC 
                              LIMIT ?");
        $stmt->execute([$limit]);
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
