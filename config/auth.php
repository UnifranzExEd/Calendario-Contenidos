<?php
/**
 * Autenticación y Permisos - UNIFRANZ Calendar
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

/**
 * Obtener URL base del proyecto
 */
function getBaseUrl() {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    // Si estamos en views/ o api/, subir un nivel
    if (preg_match('#/(views|api|import)$#', $scriptDir)) {
        $scriptDir = dirname($scriptDir);
    }
    return rtrim($scriptDir, '/') . '/';
}

/**
 * Verificar si el usuario está autenticado
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Obtener usuario actual
 */
function getCurrentUser() {
    if (!isAuthenticated()) return null;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, nombre, email, rol, avatar, activo FROM usuarios WHERE id = ? AND activo = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Requerir autenticación (redirige al login si no está autenticado)
 */
function requireAuth() {
    if (!isAuthenticated()) {
        if (isApiRequest()) {
            jsonResponse(['error' => 'No autenticado'], 401);
        }
        header('Location: ' . getBaseUrl() . 'index.php');
        exit;
    }
    $user = getCurrentUser();
    if (!$user) {
        session_destroy();
        if (isApiRequest()) {
            jsonResponse(['error' => 'Usuario no válido'], 401);
        }
        header('Location: ' . getBaseUrl() . 'index.php');
        exit;
    }
    return $user;
}

/**
 * Requerir un rol específico
 */
function requireRole($roles) {
    $user = requireAuth();
    if (!is_array($roles)) $roles = [$roles];
    
    if (!in_array($user['rol'], $roles)) {
        if (isApiRequest()) {
            jsonResponse(['error' => 'No tienes permisos para esta acción'], 403);
        }
        header('Location: ' . getBaseUrl() . 'views/dashboard.php');
        exit;
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
 * Login de usuario
 */
function loginUser($email, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_rol'] = $user['rol'];
    $_SESSION['user_nombre'] = $user['nombre'];
    $_SESSION['login_time'] = time();
    
    return $user;
}

/**
 * Logout
 */
function logoutUser() {
    session_destroy();
}

/**
 * Verificar si es una petición a la API
 */
function isApiRequest() {
    return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false ||
           (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
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
 * Crear notificación para todos los usuarios de un rol
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
