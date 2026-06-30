<?php
/**
 * API: Autenticación - UNIFRANZ Calendar v2.1
 * Self-contained: no requires, all DB logic inline
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ─── Inline DB Connection ────────────────────────────────────────────
function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;
    // Try env var first, then hardcoded
    $host = getenv('PGHOST') ?: getenv('DB_HOST') ?: 'db.fhnolvqocysnjwgsdflq.supabase.co';
    $name = getenv('PGDATABASE') ?: getenv('DB_NAME') ?: 'postgres';
    $user = getenv('PGUSER') ?: getenv('DB_USER') ?: 'postgres';
    $pass = getenv('PGPASSWORD') ?: getenv('DB_PASS') ?: 'P6mIlecuZClU1qyU';
    $port = getenv('PGPORT') ?: getenv('DB_PORT') ?: '5432';

    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function jsr($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getToken() {
    $h = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($h as $k => $v) { if (strtolower($k) === 'x-auth-token') return trim($v); }
    return $_COOKIE['auth_token'] ?? null;
}

// ─── Router ─────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $email = trim($input['email'] ?? '');
        $pass  = $input['password'] ?? '';

        if (!$email || !$pass) { jsr(['error' => 'Email y contraseña requeridos'], 400); }

        try {
            $db = getDB();
        } catch (Exception $e) {
            jsr(['error' => 'DB Error: ' . $e->getMessage(), 'drivers' => PDO::getAvailableDrivers()], 500);
        }

        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password'])) {
            jsr(['error' => 'Credenciales inválidas'], 401);
        }

        $token = bin2hex(random_bytes(32));
        $db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$user['id']]);
        $db->prepare("INSERT INTO user_sessions (token, user_id, rol, nombre, expires_at) VALUES (?, ?, ?, ?, NOW() + INTERVAL '24 hours')")
           ->execute([$token, $user['id'], $user['rol'], $user['nombre']]);

        setcookie('auth_token', $token, ['expires' => time()+86400, 'path' => '/', 'httponly' => true, 'secure' => true, 'samesite' => 'Lax']);
        jsr(['success' => true, 'token' => $token, 'user' => ['id' => $user['id'], 'nombre' => $user['nombre'], 'email' => $user['email'], 'rol' => $user['rol']]]);

    case 'logout':
        $token = getToken();
        if ($token) { try { getDB()->prepare("DELETE FROM user_sessions WHERE token = ?")->execute([$token]); } catch(Exception $e){} }
        setcookie('auth_token', '', time()-3600, '/');
        jsr(['success' => true]);

    case 'me':
        $token = getToken();
        if (!$token) jsr(['error' => 'No autenticado'], 401);
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT u.id, u.nombre, u.email, u.rol, u.avatar FROM user_sessions s JOIN usuarios u ON s.user_id = u.id WHERE s.token = ? AND s.expires_at > NOW() AND u.activo = 1");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
        } catch (Exception $e) {
            jsr(['error' => 'DB Error: ' . $e->getMessage()], 500);
        }
        if (!$user) jsr(['error' => 'Sesión inválida'], 401);
        require_once __DIR__ . '/../config/auth.php';
        jsr(['user' => $user, 'permissions' => getPermissions($user['rol'])]);

    default:
        jsr(['error' => 'Acción no válida', 'version' => '2.1'], 400);
}
