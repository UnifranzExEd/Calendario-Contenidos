<?php
/**
 * Autenticación sin PHP Sessions (compatible con Vercel Serverless)
 * Usa tokens en base de datos en lugar de $_SESSION
 */

require_once __DIR__ . '/database.php';

/**
 * Obtener token de la request (cookie o header)
 */
function getRequestToken() {
    // Primero buscar en header Authorization
    $headers = getallheaders();
    if (!empty($headers['X-Auth-Token'])) {
        return trim($headers['X-Auth-Token']);
    }
    // Luego en cookie
    return $_COOKIE['auth_token'] ?? null;
}

/**
 * Verificar si el usuario está autenticado
 */
function isAuthenticated() {
    $token = getRequestToken();
    if (!$token) return false;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM user_sessions WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    return $stmt->fetch() !== false;
}

/**
 * Obtener usuario actual
 */
function getCurrentUser() {
    $token = getRequestToken();
    if (!$token) return null;
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.id, u.nombre, u.email, u.rol, u.avatar, u.activo 
        FROM user_sessions s
        JOIN usuarios u ON s.user_id = u.id
        WHERE s.token = ? AND s.expires_at > NOW() AND u.activo = 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

/**
 * Requerir autenticación (devuelve error JSON si no autenticado)
 */
function requireAuth() {
    $user = getCurrentUser();
    if (!$user) {
        jsonResponse(['error' => 'No autenticado'], 401);
    }
    return $user;
}

/**
 * Login de usuario - genera token en DB
 */
function loginUser($email, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }
    
    // Generar token único
    $token = bin2hex(random_bytes(32));
    
    // Limpiar sesiones viejas del usuario
    $db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$user['id']]);
    
    // Crear nueva sesión
    $stmt = $db->prepare("
        INSERT INTO user_sessions (token, user_id, rol, nombre, expires_at) 
        VALUES (?, ?, ?, ?, NOW() + INTERVAL '24 hours')
    ");
    $stmt->execute([$token, $user['id'], $user['rol'], $user['nombre']]);
    
    // Setear cookie
    setcookie('auth_token', $token, [
        'expires'  => time() + 86400,
        'path'     => '/',
        'httponly' => true,
        'secure'   => true,
        'samesite' => 'Lax'
    ]);
    
    $user['_token'] = $token;
    return $user;
}

/**
 * Logout
 */
function logoutUser() {
    $token = getRequestToken();
    if ($token) {
        $db = getDB();
        $db->prepare("DELETE FROM user_sessions WHERE token = ?")->execute([$token]);
    }
    setcookie('auth_token', '', time() - 3600, '/');
}

/**
 * Requerir un rol específico
 */
function requireRole($roles) {
    $user = requireAuth();
    if (!is_array($roles)) $roles = [$roles];
    if (!in_array($user['rol'], $roles)) {
        jsonResponse(['error' => 'No tienes permisos para esta acción'], 403);
    }
    return $user;
}

/**
 * Verificar si tiene un rol específico
 */
function hasRole($role) {
    $user = getCurrentUser();
    if (!$user) return false;
    if (is_array($role)) return in_array($user['rol'], $role);
    return $user['rol'] === $role;
}

/**
 * Verificar si es una petición a la API
 */
function isApiRequest() {
    return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
}

/**
 * Definición de permisos por rol
 */
function getPermissions($rol) {
    $perms = [
        'admin' => [
            'ver_pestanas'       => true,
            'crear_contenido'    => true,
            'editar_contenido'   => true,
            'editar_cualquier'   => true,
            'cambiar_estado'     => ['En elaboración','Redacción','En revisión','Producción','Corrección','Aprobado','Programado','Publicado'],
            'asignar_pp'         => true,
            'subir_link_producido' => true,
            'subir_link_publicado' => true,
            'registrar_metricas' => true,
            'gestionar_usuarios' => true,
            'config_campos'      => true,
            'config_dropdowns'   => true,
            'exportar'           => true,
            'gestionar_hashtags' => true,
            'archivar'           => true,
            'ver_historial'      => true,
        ],
        'community' => [
            'ver_pestanas'       => true,
            'crear_contenido'    => true,
            'editar_contenido'   => true,
            'editar_cualquier'   => false,
            'cambiar_estado'     => ['En elaboración','Redacción','En revisión','Publicado'],
            'asignar_pp'         => false,
            'subir_link_producido' => false,
            'subir_link_publicado' => true,
            'registrar_metricas' => true,
            'gestionar_usuarios' => false,
            'config_campos'      => true,
            'config_dropdowns'   => true,
            'exportar'           => true,
            'gestionar_hashtags' => true,
            'archivar'           => false,
            'ver_historial'      => true,
        ],
        'postproductor' => [
            'ver_pestanas'       => true,
            'crear_contenido'    => true,
            'editar_contenido'   => true,
            'editar_cualquier'   => true,
            'cambiar_estado'     => ['En elaboración','Redacción','En revisión','Producción','Corrección','Aprobado','Programado','Publicado'],
            'asignar_pp'         => true,
            'subir_link_producido' => true,
            'subir_link_publicado' => true,
            'registrar_metricas' => true,
            'gestionar_usuarios' => true,
            'config_campos'      => true,
            'config_dropdowns'   => true,
            'exportar'           => true,
            'gestionar_hashtags' => true,
            'archivar'           => true,
            'ver_historial'      => true,
        ],
    ];
    
    return $perms[$rol] ?? [];
}

/**
 * Verificar un permiso específico
 */
function can($permiso) {
    $user = getCurrentUser();
    if (!$user) return false;
    $perms = getPermissions($user['rol']);
    return $perms[$permiso] ?? false;
}

/**
 * Crear notificación para un usuario
 */
function crearNotificacion($usuario_id, $tipo, $mensaje, $contenido_id = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notificaciones (usuario_id, tipo, mensaje, contenido_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$usuario_id, $tipo, $mensaje, $contenido_id]);
}

/**
 * Notificar a todos los usuarios de un rol
 */
function notificarRol($rol, $tipo, $mensaje, $contenido_id = null) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE rol = ? AND activo = 1");
    $stmt->execute([$rol]);
    $usuarios = $stmt->fetchAll();
    foreach ($usuarios as $u) {
        crearNotificacion($u['id'], $tipo, $mensaje, $contenido_id);
    }
}

/**
 * Obtener URL base
 */
function getBaseUrl() {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    if (preg_match('#/(views|api|import)$#', $scriptDir)) {
        $scriptDir = dirname($scriptDir);
    }
    return rtrim($scriptDir, '/') . '/';
}
