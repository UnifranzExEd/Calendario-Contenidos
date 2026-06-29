<?php
/**
 * API: Upload de imágenes de referencia
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'upload';

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

switch ($action) {
    case 'upload':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);

        $contenidoId = intval($_POST['contenido_id'] ?? 0);
        
        // Check if it's a base64 paste (from clipboard)
        $rawInput = file_get_contents('php://input');
        $jsonInput = json_decode($rawInput, true);
        
        if ($jsonInput && isset($jsonInput['image_data'])) {
            // Base64 image from Ctrl+V paste
            $contenidoId = intval($jsonInput['contenido_id'] ?? 0);
            $data = $jsonInput['image_data'];
            
            // Extract base64 data
            if (preg_match('/^data:image\/(\w+);base64,/', $data, $matches)) {
                $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
                $data = substr($data, strpos($data, ',') + 1);
                $data = base64_decode($data);
                
                $filename = 'ref_' . time() . '_' . uniqid() . '.' . $ext;
                $filepath = $uploadDir . $filename;
                
                file_put_contents($filepath, $data);
                
                // Save to DB
                $db = getDB();
                $stmt = $db->prepare("INSERT INTO contenido_imagenes (contenido_id, filename, subido_por) VALUES (?, ?, ?)");
                $stmt->execute([$contenidoId ?: null, $filename, $user['id']]);
                
                $baseUrl = getBaseUrl();
                jsonResponse([
                    'success' => true,
                    'id' => $db->lastInsertId(),
                    'filename' => $filename,
                    'url' => $baseUrl . 'uploads/' . $filename
                ]);
            } else {
                jsonResponse(['error' => 'Formato de imagen inválido'], 400);
            }
        } else {
            // File upload
            if (empty($_FILES['imagen'])) {
                jsonResponse(['error' => 'No se recibió archivo'], 400);
            }

            $file = $_FILES['imagen'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (!in_array($file['type'], $allowed)) {
                jsonResponse(['error' => 'Tipo de archivo no permitido. Solo: JPG, PNG, GIF, WEBP'], 400);
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                jsonResponse(['error' => 'Archivo muy grande (máx 5MB)'], 400);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'ref_' . time() . '_' . uniqid() . '.' . $ext;
            $filepath = $uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                jsonResponse(['error' => 'Error al guardar archivo'], 500);
            }

            // Save to DB
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO contenido_imagenes (contenido_id, filename, subido_por) VALUES (?, ?, ?)");
            $stmt->execute([$contenidoId ?: null, $filename, $user['id']]);

            $baseUrl = getBaseUrl();
            jsonResponse([
                'success' => true,
                'id' => $db->lastInsertId(),
                'filename' => $filename,
                'url' => $baseUrl . 'uploads/' . $filename
            ]);
        }
        break;

    case 'list':
        $contenidoId = intval($_GET['contenido_id'] ?? 0);
        if (!$contenidoId) jsonResponse(['data' => []]);
        
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM contenido_imagenes WHERE contenido_id = ? ORDER BY id ASC");
        $stmt->execute([$contenidoId]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $baseUrl = getBaseUrl();
        foreach ($images as &$img) {
            $img['url'] = $baseUrl . 'uploads/' . $img['filename'];
        }
        
        jsonResponse(['data' => $images]);
        break;

    case 'delete':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        
        $db = getDB();
        $stmt = $db->prepare("SELECT filename FROM contenido_imagenes WHERE id = ?");
        $stmt->execute([$id]);
        $img = $stmt->fetch();
        
        if ($img) {
            $filepath = $uploadDir . $img['filename'];
            if (file_exists($filepath)) unlink($filepath);
            $db->prepare("DELETE FROM contenido_imagenes WHERE id = ?")->execute([$id]);
        }
        
        jsonResponse(['success' => true]);
        break;

    case 'assign':
        // Assign temp images to a contenido after creation
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $contenidoId = intval($input['contenido_id'] ?? 0);
        $imageIds = $input['image_ids'] ?? [];
        
        if ($contenidoId && $imageIds) {
            $db = getDB();
            $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
            $params = array_merge([$contenidoId], array_map('intval', $imageIds));
            $db->prepare("UPDATE contenido_imagenes SET contenido_id = ? WHERE id IN ($placeholders)")->execute($params);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
