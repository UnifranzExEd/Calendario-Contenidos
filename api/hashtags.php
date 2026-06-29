<?php
/**
 * API: Hashtags (Librería por red social)
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {
    case 'list':
        $red_social = $_GET['red_social'] ?? '';
        $search = $_GET['search'] ?? '';
        
        $where = "1=1";
        $params = [];
        
        if ($red_social) {
            $where .= " AND (red_social = ? OR red_social = 'TODAS')";
            $params[] = $red_social;
        }
        if ($search) {
            $where .= " AND tag LIKE ?";
            $params[] = "%$search%";
        }
        
        $stmt = $db->prepare("SELECT * FROM hashtags WHERE $where ORDER BY veces_usado DESC, tag ASC LIMIT 100");
        $stmt->execute($params);
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        
        $perms = getPermissions($user['rol']);
        if (!$perms['gestionar_hashtags']) jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $tag = trim($input['tag'] ?? '');
        $red_social = $input['red_social'] ?? 'TODAS';
        
        if (empty($tag)) jsonResponse(['error' => 'Hashtag requerido'], 400);
        if ($tag[0] !== '#') $tag = '#' . $tag;
        
        // Check if exists
        $check = $db->prepare("SELECT id FROM hashtags WHERE tag = ? AND red_social = ?");
        $check->execute([$tag, $red_social]);
        $existing = $check->fetch();
        
        if ($existing) {
            jsonResponse(['success' => true, 'id' => $existing['id'], 'exists' => true]);
        } else {
            $stmt = $db->prepare("INSERT INTO hashtags (tag, red_social) VALUES (?, ?)");
            $stmt->execute([$tag, $red_social]);
            jsonResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
        }
        break;

    case 'link':
        // Link hashtag to content
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        
        $input = getJsonInput();
        $contenido_id = intval($input['contenido_id'] ?? 0);
        $hashtag_id = intval($input['hashtag_id'] ?? 0);
        
        if (!$contenido_id || !$hashtag_id) jsonResponse(['error' => 'IDs requeridos'], 400);
        
        try {
            $db->prepare("INSERT IGNORE INTO contenido_hashtags (contenido_id, hashtag_id) VALUES (?, ?)")
                ->execute([$contenido_id, $hashtag_id]);
            $db->prepare("UPDATE hashtags SET veces_usado = veces_usado + 1 WHERE id = ?")->execute([$hashtag_id]);
        } catch (Exception $e) {}
        
        jsonResponse(['success' => true]);
        break;

    case 'unlink':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        
        $input = getJsonInput();
        $contenido_id = intval($input['contenido_id'] ?? 0);
        $hashtag_id = intval($input['hashtag_id'] ?? 0);
        
        $db->prepare("DELETE FROM contenido_hashtags WHERE contenido_id = ? AND hashtag_id = ?")
            ->execute([$contenido_id, $hashtag_id]);
        $db->prepare("UPDATE hashtags SET veces_usado = GREATEST(veces_usado - 1, 0) WHERE id = ?")->execute([$hashtag_id]);
        
        jsonResponse(['success' => true]);
        break;

    case 'update_content_hashtags':
        // Bulk update hashtags for a content
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        
        $input = getJsonInput();
        $contenido_id = intval($input['contenido_id'] ?? 0);
        $tags = $input['tags'] ?? [];
        $red_social = $input['red_social'] ?? 'TODAS';
        
        if (!$contenido_id) jsonResponse(['error' => 'ID contenido requerido'], 400);
        
        // Remove old links
        $oldTags = $db->prepare("SELECT hashtag_id FROM contenido_hashtags WHERE contenido_id = ?");
        $oldTags->execute([$contenido_id]);
        foreach ($oldTags->fetchAll() as $old) {
            $db->prepare("UPDATE hashtags SET veces_usado = GREATEST(veces_usado - 1, 0) WHERE id = ?")->execute([$old['hashtag_id']]);
        }
        $db->prepare("DELETE FROM contenido_hashtags WHERE contenido_id = ?")->execute([$contenido_id]);
        
        // Add new
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (empty($tag)) continue;
            if ($tag[0] !== '#') $tag = '#' . $tag;
            
            // Find or create hashtag
            $check = $db->prepare("SELECT id FROM hashtags WHERE tag = ? AND red_social = ?");
            $check->execute([$tag, $red_social]);
            $ht = $check->fetch();
            
            if (!$ht) {
                $db->prepare("INSERT INTO hashtags (tag, red_social) VALUES (?, ?)")->execute([$tag, $red_social]);
                $htId = $db->lastInsertId();
            } else {
                $htId = $ht['id'];
            }
            
            $db->prepare("INSERT IGNORE INTO contenido_hashtags (contenido_id, hashtag_id) VALUES (?, ?)")
                ->execute([$contenido_id, $htId]);
            $db->prepare("UPDATE hashtags SET veces_usado = veces_usado + 1 WHERE id = ?")->execute([$htId]);
        }
        
        jsonResponse(['success' => true]);
        break;

    case 'delete':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if ($user['rol'] !== 'admin') jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        $db->prepare("DELETE FROM contenido_hashtags WHERE hashtag_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM hashtags WHERE id = ?")->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
