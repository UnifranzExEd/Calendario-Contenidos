<?php
/**
 * API: Notificaciones
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {
    case 'list':
        $limit = intval($_GET['limit'] ?? 30);
        $stmt = $db->prepare("SELECT n.*, c.tema as contenido_tema 
                              FROM notificaciones n 
                              LEFT JOIN contenidos c ON n.contenido_id = c.id 
                              WHERE n.usuario_id = ? 
                              ORDER BY n.created_at DESC 
                              LIMIT ?");
        $stmt->execute([$user['id'], $limit]);
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'count':
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notificaciones WHERE usuario_id = ? AND leida = 0");
        $stmt->execute([$user['id']]);
        jsonResponse($stmt->fetch());
        break;

    case 'read':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        
        if ($id) {
            $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?")->execute([$id, $user['id']]);
        } else {
            // Mark all as read
            $db->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0")->execute([$user['id']]);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
