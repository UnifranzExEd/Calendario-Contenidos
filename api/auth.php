<?php
/**
 * API: Autenticación
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            jsonResponse(['error' => 'Email y contraseña requeridos'], 400);
        }
        
        $user = loginUser($email, $password);
        if ($user) {
            jsonResponse([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'nombre' => $user['nombre'],
                    'email' => $user['email'],
                    'rol' => $user['rol']
                ]
            ]);
        } else {
            jsonResponse(['error' => 'Credenciales inválidas'], 401);
        }
        break;

    case 'logout':
        logoutUser();
        jsonResponse(['success' => true, 'message' => 'Sesión cerrada']);
        break;

    case 'me':
        $user = requireAuth();
        $perms = getPermissions($user['rol']);
        jsonResponse([
            'user' => $user,
            'permissions' => $perms
        ]);
        break;

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
