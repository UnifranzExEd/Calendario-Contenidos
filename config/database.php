<?php
/**
 * Configuración de Base de Datos - UNIFRANZ Calendar
 * Compatible con Supabase (PostgreSQL) y Vercel Serverless
 */

// InfinityFree MySQL credentials
define('DB_HOST',   getenv('DB_HOST')   ?: 'sql103.infinityfree.com');
define('DB_NAME',   getenv('DB_NAME')   ?: 'if0_42313714_exeed');
define('DB_USER',   getenv('DB_USER')   ?: 'if0_42313714');
define('DB_PASS',   'eWsGmSb954rky4');
define('DB_PORT',   getenv('DB_PORT')   ?: '3306');
define('DB_DRIVER', 'mysql');            // always MySQL
define('DB_CHARSET', 'utf8mb4');

// App config
define('APP_NAME', 'UNIFRANZ Executive Education');
define('APP_VERSION', '2.0.0');
define('SESSION_LIFETIME', 86400); // 24 horas

/**
 * Obtener conexión PDO a la base de datos
 */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            if (DB_DRIVER === 'pgsql') {
                $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
            } else {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];
            }
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

/**
 * Helper para respuestas JSON
 */
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Helper para obtener input JSON del body
 */
function getJsonInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    return $input ?: [];
}
