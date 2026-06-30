<?php
/**
 * API: Autenticación UNIFRANZ Calendar v3.0
 * Uses Supabase REST API (HTTP) - works with any serverless environment
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ─── Supabase Config ─────────────────────────────────────────────────
define('SB_URL', 'https://fhnolvqocysnjwgsdflq.supabase.co');
define('SB_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZobm9sdnFvY3lzbmp3Z3NkZmxxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4Mjc1NzQ5NSwiZXhwIjoyMDk4MzMzNDk1fQ.IO59t9zhCbyFi_nHNjMlrckHWJEdzYU4-5gCVbgWaog');

// ─── HTTP Helper ──────────────────────────────────────────────────────
function sb_request($method, $path, $body = null, $extra_headers = []) {
    $url = SB_URL . '/rest/v1/' . ltrim($path, '/');
    $headers = [
        'apikey: ' . SB_KEY,
        'Authorization: Bearer ' . SB_KEY,
        'Content-Type: application/json',
    ];
    foreach ($extra_headers as $h) $headers[] = $h;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) return ['error' => $err, '__code' => 0];
    return ['data' => json_decode($res, true), '__code' => $code];
}

function sb_select($table, $filter = '') {
    return sb_request('GET', $table . ($filter ? '?' . $filter : ''));
}

function sb_insert($table, $data) {
    return sb_request('POST', $table, $data, ['Prefer: return=representation']);
}

function sb_delete($table, $filter) {
    return sb_request('DELETE', $table . '?' . $filter);
}

// ─── Helpers ─────────────────────────────────────────────────────────
function jsr($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getToken() {
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strtolower($k) === 'x-auth-token') return trim($v);
        }
    }
    return $_COOKIE['auth_token'] ?? null;
}

function getPermissions($rol) {
    $perms = [
        'admin' => [
            'ver_pestanas' => true, 'crear_contenido' => true, 'editar_contenido' => true,
            'editar_cualquier' => true, 'cambiar_estado' => ['En elaboración','Redacción','En revisión','Producción','Corrección','Aprobado','Programado','Publicado'],
            'asignar_pp' => true, 'subir_link_producido' => true, 'subir_link_publicado' => true,
            'registrar_metricas' => true, 'gestionar_usuarios' => true, 'config_campos' => true,
            'config_dropdowns' => true, 'exportar' => true, 'gestionar_hashtags' => true,
            'archivar' => true, 'ver_historial' => true,
        ],
        'community' => [
            'ver_pestanas' => true, 'crear_contenido' => true, 'editar_contenido' => true,
            'editar_cualquier' => false, 'cambiar_estado' => ['En elaboración','Redacción','En revisión','Publicado'],
            'asignar_pp' => false, 'subir_link_producido' => false, 'subir_link_publicado' => true,
            'registrar_metricas' => true, 'gestionar_usuarios' => false, 'config_campos' => true,
            'config_dropdowns' => true, 'exportar' => true, 'gestionar_hashtags' => true,
            'archivar' => false, 'ver_historial' => true,
        ],
        'postproductor' => [
            'ver_pestanas' => true, 'crear_contenido' => true, 'editar_contenido' => true,
            'editar_cualquier' => true, 'cambiar_estado' => ['En elaboración','Redacción','En revisión','Producción','Corrección','Aprobado','Programado','Publicado'],
            'asignar_pp' => true, 'subir_link_producido' => true, 'subir_link_publicado' => true,
            'registrar_metricas' => true, 'gestionar_usuarios' => true, 'config_campos' => true,
            'config_dropdowns' => true, 'exportar' => true, 'gestionar_hashtags' => true,
            'archivar' => true, 'ver_historial' => true,
        ],
    ];
    return $perms[$rol] ?? [];
}

// ─── Router ──────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'login':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $email = trim($input['email'] ?? '');
        $pass  = $input['password'] ?? '';
        if (!$email || !$pass) jsr(['error' => 'Email y contraseña requeridos'], 400);

        // Find user via Supabase REST API
        $res = sb_select('usuarios', 'email=eq.' . urlencode($email) . '&activo=eq.1&select=*');
        if ($res['__code'] !== 200 || empty($res['data'])) {
            jsr(['error' => 'Credenciales inválidas'], 401);
        }
        $user = $res['data'][0];

        if (!password_verify($pass, $user['password'])) {
            jsr(['error' => 'Credenciales inválidas'], 401);
        }

        // Delete old sessions for this user
        sb_delete('user_sessions', 'user_id=eq.' . $user['id']);

        // Create new session token
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        sb_insert('user_sessions', [
            'token'      => $token,
            'user_id'    => $user['id'],
            'rol'        => $user['rol'],
            'nombre'     => $user['nombre'],
            'expires_at' => $expires,
        ]);

        setcookie('auth_token', $token, ['expires' => time()+86400, 'path' => '/', 'httponly' => true, 'secure' => true, 'samesite' => 'Lax']);

        jsr([
            'success' => true,
            'token'   => $token,
            'user'    => ['id' => $user['id'], 'nombre' => $user['nombre'], 'email' => $user['email'], 'rol' => $user['rol']]
        ]);

    case 'logout':
        $token = getToken();
        if ($token) sb_delete('user_sessions', 'token=eq.' . urlencode($token));
        setcookie('auth_token', '', time()-3600, '/');
        jsr(['success' => true]);

    case 'me':
        $token = getToken();
        if (!$token) jsr(['error' => 'No autenticado'], 401);

        $now = date('Y-m-d\TH:i:s');
        $res = sb_select('user_sessions', 'token=eq.' . urlencode($token) . '&expires_at=gt.' . $now . '&select=user_id,rol,nombre');
        if ($res['__code'] !== 200 || empty($res['data'])) {
            jsr(['error' => 'Sesión inválida o expirada'], 401);
        }
        $session = $res['data'][0];

        $ures = sb_select('usuarios', 'id=eq.' . $session['user_id'] . '&activo=eq.1&select=id,nombre,email,rol,avatar');
        if (empty($ures['data'])) jsr(['error' => 'Usuario no válido'], 401);
        $user = $ures['data'][0];

        jsr(['user' => $user, 'permissions' => getPermissions($user['rol'])]);

    default:
        jsr(['error' => 'Acción no válida', 'version' => '3.0'], 400);
}
