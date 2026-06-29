<?php
/**
 * API: Campos por pestaña (Configuración)
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {
    case 'list':
        $pestana = $_GET['pestana'] ?? '';
        
        if ($pestana) {
            $stmt = $db->prepare("SELECT pc.* FROM pestana_campos pc 
                                  JOIN pestanas p ON pc.pestana_id = p.id 
                                  WHERE p.slug = ? AND pc.visible = 1 
                                  ORDER BY pc.orden ASC");
            $stmt->execute([$pestana]);
        } else {
            $stmt = $db->query("SELECT pc.*, p.slug as pestana_slug FROM pestana_campos pc 
                               JOIN pestanas p ON pc.pestana_id = p.id 
                               WHERE pc.visible = 1 
                               ORDER BY p.orden ASC, pc.orden ASC");
        }
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'all':
        // Get all campos grouped by pestana slug (for initial load)
        $stmt = $db->query("SELECT pc.*, p.slug as pestana_slug FROM pestana_campos pc 
                           JOIN pestanas p ON pc.pestana_id = p.id 
                           WHERE pc.visible = 1 
                           ORDER BY p.orden ASC, pc.orden ASC");
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['pestana_slug']][] = $r;
        }
        jsonResponse(['data' => $grouped]);
        break;

    case 'pestanas':
        // List all pestanas
        $stmt = $db->query("SELECT * FROM pestanas WHERE activa = 1 ORDER BY orden ASC");
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'update':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_campos')) jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        $updates = [];
        $params = [];
        if (isset($input['nombre_display'])) { $updates[] = "nombre_display = ?"; $params[] = $input['nombre_display']; }
        if (isset($input['visible'])) { $updates[] = "visible = ?"; $params[] = intval($input['visible']); }
        if (isset($input['orden'])) { $updates[] = "orden = ?"; $params[] = intval($input['orden']); }
        if (isset($input['ancho'])) { $updates[] = "ancho = ?"; $params[] = $input['ancho']; }

        if ($updates) {
            $params[] = $id;
            $db->prepare("UPDATE pestana_campos SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
        }
        jsonResponse(['success' => true]);
        break;

    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_campos')) jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $pestana_slug = $input['pestana'] ?? '';
        
        $pStmt = $db->prepare("SELECT id FROM pestanas WHERE slug = ?");
        $pStmt->execute([$pestana_slug]);
        $p = $pStmt->fetch();
        if (!$p) jsonResponse(['error' => 'Pestaña no válida'], 400);

        $maxOrder = $db->prepare("SELECT MAX(orden) FROM pestana_campos WHERE pestana_id = ?");
        $maxOrder->execute([$p['id']]);
        $orden = intval($maxOrder->fetchColumn()) + 1;

        $stmt = $db->prepare("INSERT INTO pestana_campos (pestana_id, nombre_campo, nombre_display, tipo_campo, dropdown_grupo, orden, ancho) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $p['id'],
            $input['nombre_campo'] ?? 'custom_' . time(),
            $input['nombre_display'] ?? 'Nuevo Campo',
            $input['tipo_campo'] ?? 'texto',
            $input['dropdown_grupo'] ?? null,
            $orden,
            $input['ancho'] ?? '150px'
        ]);
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
        break;

    case 'delete':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_campos')) jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        // Soft delete - just hide
        $db->prepare("UPDATE pestana_campos SET visible = 0 WHERE id = ?")->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    case 'update_pestana':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_campos')) jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        $updates = [];
        $params = [];
        if (isset($input['nombre'])) { $updates[] = "nombre = ?"; $params[] = $input['nombre']; }
        if (isset($input['color'])) { $updates[] = "color = ?"; $params[] = $input['color']; }
        if (isset($input['enlace_carpeta_base'])) { $updates[] = "enlace_carpeta_base = ?"; $params[] = $input['enlace_carpeta_base']; }

        if ($updates) {
            $params[] = $id;
            $db->prepare("UPDATE pestanas SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
