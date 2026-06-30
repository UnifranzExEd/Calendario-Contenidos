<?php
/**
 * Supabase Helper - UNIFRANZ Calendar
 * All API files include this instead of config/auth.php + config/database.php
 * Uses Supabase REST API (HTTP) - works with Vercel serverless
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ─── Supabase Config ─────────────────────────────────────────────────
define('SB_URL', 'https://fhnolvqocysnjwgsdflq.supabase.co');
define('SB_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZobm9sdnFvY3lzbmp3Z3NkZmxxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4Mjc1NzQ5NSwiZXhwIjoyMDk4MzMzNDk1fQ.IO59t9zhCbyFi_nHNjMlrckHWJEdzYU4-5gCVbgWaog');

// ─── HTTP Helper ──────────────────────────────────────────────────────
function sb($method, $path, $body = null, $extra = []) {
    $url = SB_URL . '/rest/v1/' . ltrim($path, '/');
    $headers = [
        'apikey: ' . SB_KEY,
        'Authorization: Bearer ' . SB_KEY,
        'Content-Type: application/json',
    ];
    foreach ($extra as $h) $headers[] = $h;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['data' => json_decode($res, true), 'code' => $code];
}

function sb_get($table, $filter = '')    { return sb('GET',    $table . ($filter ? '?' . $filter : '')); }
function sb_post($table, $body)          { return sb('POST',   $table, $body, ['Prefer: return=representation']); }
function sb_patch($table, $filter, $body){ return sb('PATCH',  $table . '?' . $filter, $body, ['Prefer: return=representation']); }
function sb_delete($table, $filter)      { return sb('DELETE', $table . '?' . $filter); }
function sb_rpc($fn, $params = [])       { return sb('POST', str_replace('/rest/v1/', '/rest/v1/rpc/', SB_URL . '/rest/v1/rpc/' . $fn), $params); }

// ─── Auth Helpers ─────────────────────────────────────────────────────
function getToken() {
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strtolower($k) === 'x-auth-token') return trim($v);
        }
    }
    return $_COOKIE['auth_token'] ?? null;
}

function requireAuth() {
    $token = getToken();
    if (!$token) jsonResponse(['error' => 'No autenticado'], 401);

    $now = date('c');
    $res = sb_get('user_sessions', 'token=eq.' . urlencode($token) . '&expires_at=gt.' . urlencode($now) . '&select=user_id,rol,nombre');
    if (empty($res['data'])) jsonResponse(['error' => 'Sesión inválida'], 401);

    $s   = $res['data'][0];
    $ures = sb_get('usuarios', 'id=eq.' . $s['user_id'] . '&activo=eq.1&select=id,nombre,email,rol,avatar');
    if (empty($ures['data'])) jsonResponse(['error' => 'Usuario no válido'], 401);

    return $ures['data'][0];
}

function can($permiso, $user) {
    $perms = getPermissions($user['rol']);
    return $perms[$permiso] ?? false;
}

function getPermissions($rol) {
    $all = ['En elaboración','Redacción','En revisión','Producción','Corrección','Aprobado','Programado','Publicado'];
    $map = [
        'admin'         => ['ver_pestanas'=>true,'crear_contenido'=>true,'editar_contenido'=>true,'editar_cualquier'=>true,'cambiar_estado'=>$all,'asignar_pp'=>true,'subir_link_producido'=>true,'subir_link_publicado'=>true,'registrar_metricas'=>true,'gestionar_usuarios'=>true,'config_campos'=>true,'config_dropdowns'=>true,'exportar'=>true,'gestionar_hashtags'=>true,'archivar'=>true,'ver_historial'=>true],
        'community'     => ['ver_pestanas'=>true,'crear_contenido'=>true,'editar_contenido'=>true,'editar_cualquier'=>false,'cambiar_estado'=>['En elaboración','Redacción','En revisión','Publicado'],'asignar_pp'=>false,'subir_link_producido'=>false,'subir_link_publicado'=>true,'registrar_metricas'=>true,'gestionar_usuarios'=>false,'config_campos'=>true,'config_dropdowns'=>true,'exportar'=>true,'gestionar_hashtags'=>true,'archivar'=>false,'ver_historial'=>true],
        'postproductor' => ['ver_pestanas'=>true,'crear_contenido'=>true,'editar_contenido'=>true,'editar_cualquier'=>true,'cambiar_estado'=>$all,'asignar_pp'=>true,'subir_link_producido'=>true,'subir_link_publicado'=>true,'registrar_metricas'=>true,'gestionar_usuarios'=>true,'config_campos'=>true,'config_dropdowns'=>true,'exportar'=>true,'gestionar_hashtags'=>true,'archivar'=>true,'ver_historial'=>true],
    ];
    return $map[$rol] ?? [];
}

// ─── Common Helpers ───────────────────────────────────────────────────
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true) ?: [];
}
