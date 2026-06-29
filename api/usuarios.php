<?php
/**
 * API: Usuarios (CRUD - Solo Admin)
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$currentUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {
    case 'list':
        if ($currentUser['rol'] !== 'admin') jsonResponse(['error' => 'No autorizado'], 403);
        
        $stmt = $db->query("SELECT id, nombre, email, rol, activo, created_at FROM usuarios ORDER BY nombre ASC");
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'get':
        if ($currentUser['rol'] !== 'admin') jsonResponse(['error' => 'No autorizado'], 403);
        
        $id = intval($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT id, nombre, email, rol, activo, created_at FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if (!$u) jsonResponse(['error' => 'Usuario no encontrado'], 404);
        jsonResponse(['data' => $u]);
        break;

    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        if ($currentUser['rol'] !== 'admin') jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $nombre = trim($input['nombre'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $rol = $input['rol'] ?? 'community';

        if (empty($nombre) || empty($email) || empty($password)) {
            jsonResponse(['error' => 'Nombre, email y contraseña son requeridos'], 400);
        }

        if (!in_array($rol, ['admin','community','postproductor'])) {
            jsonResponse(['error' => 'Rol no válido'], 400);
        }

        // Check unique email
        $check = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) jsonResponse(['error' => 'El email ya está registrado'], 400);

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $email, $hash, $rol]);

        jsonResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
        break;

    case 'update':
        if ($method !== 'POST' && $method !== 'PUT') jsonResponse(['error' => 'Método no permitido'], 405);
        if ($currentUser['rol'] !== 'admin') jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        $updates = [];
        $params = [];

        if (isset($input['nombre'])) { $updates[] = "nombre = ?"; $params[] = trim($input['nombre']); }
        if (isset($input['email'])) { 
            $check = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $check->execute([trim($input['email']), $id]);
            if ($check->fetch()) jsonResponse(['error' => 'El email ya está en uso'], 400);
            $updates[] = "email = ?"; 
            $params[] = trim($input['email']); 
        }
        if (!empty($input['password'])) { $updates[] = "password = ?"; $params[] = password_hash($input['password'], PASSWORD_BCRYPT); }
        if (isset($input['rol'])) { $updates[] = "rol = ?"; $params[] = $input['rol']; }
        if (isset($input['activo'])) { $updates[] = "activo = ?"; $params[] = intval($input['activo']); }

        if (empty($updates)) jsonResponse(['error' => 'Nada que actualizar'], 400);

        $params[] = $id;
        $db->prepare("UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

        jsonResponse(['success' => true]);
        break;

    case 'delete':
        if ($method !== 'POST' && $method !== 'DELETE') jsonResponse(['error' => 'Método no permitido'], 405);
        if ($currentUser['rol'] !== 'admin') jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? $_GET['id'] ?? 0);
        if ($id === $currentUser['id']) jsonResponse(['error' => 'No puedes eliminarte a ti mismo'], 400);

        $db->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?")->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    case 'postproductores':
        // List only post producers (for assignment dropdown)
        $stmt = $db->query("SELECT id, nombre FROM usuarios WHERE rol = 'postproductor' AND activo = 1 ORDER BY nombre");
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
