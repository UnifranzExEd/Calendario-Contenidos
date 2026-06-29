<?php
/**
 * API: Dropdowns (Configuración de opciones)
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {
    case 'list':
        $campo = $_GET['campo'] ?? '';
        
        if ($campo) {
            $stmt = $db->prepare("SELECT * FROM dropdown_opciones WHERE campo = ? AND activo = 1 ORDER BY orden ASC");
            $stmt->execute([$campo]);
        } else {
            $stmt = $db->query("SELECT * FROM dropdown_opciones WHERE activo = 1 ORDER BY campo ASC, orden ASC");
        }
        
        $opciones = $stmt->fetchAll();
        
        // Group by campo
        $grouped = [];
        foreach ($opciones as $o) {
            $grouped[$o['campo']][] = $o;
        }
        
        jsonResponse(['data' => $grouped]);
        break;

    case 'all':
        // Get all dropdowns grouped (for initial app load)
        $stmt = $db->query("SELECT * FROM dropdown_opciones WHERE activo = 1 ORDER BY campo ASC, orden ASC");
        $opciones = $stmt->fetchAll();
        $grouped = [];
        foreach ($opciones as $o) {
            $grouped[$o['campo']][] = [
                'id' => $o['id'],
                'valor' => $o['valor'],
                'color' => $o['color'],
            ];
        }
        jsonResponse(['data' => $grouped]);
        break;

    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_dropdowns')) jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $campo = $input['campo'] ?? '';
        $valor = trim($input['valor'] ?? '');
        $color = $input['color'] ?? '#6366f1';

        if (empty($campo) || empty($valor)) jsonResponse(['error' => 'Campo y valor requeridos'], 400);

        // Get max order
        $maxOrder = $db->prepare("SELECT MAX(orden) FROM dropdown_opciones WHERE campo = ?");
        $maxOrder->execute([$campo]);
        $orden = intval($maxOrder->fetchColumn()) + 1;

        $stmt = $db->prepare("INSERT INTO dropdown_opciones (campo, valor, color, orden) VALUES (?, ?, ?, ?)");
        $stmt->execute([$campo, $valor, $color, $orden]);

        jsonResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
        break;

    case 'update':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if (!can('config_dropdowns')) jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        $updates = [];
        $params = [];
        if (isset($input['valor'])) { $updates[] = "valor = ?"; $params[] = trim($input['valor']); }
        if (isset($input['color'])) { $updates[] = "color = ?"; $params[] = $input['color']; }
        if (isset($input['orden'])) { $updates[] = "orden = ?"; $params[] = intval($input['orden']); }

        if ($updates) {
            $params[] = $id;
            $db->prepare("UPDATE dropdown_opciones SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
        }

        jsonResponse(['success' => true]);
        break;

    case 'delete':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if ($user['rol'] !== 'admin') jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        $db->prepare("UPDATE dropdown_opciones SET activo = 0 WHERE id = ?")->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
