<?php
/**
 * Configuración de Base de Datos - UNIFRANZ Calendar
 * Compatible con InfinityFree MySQL
 */

// Auto-detect environment (local vs production)
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || 
           (isset($_SERVER['HTTP_HOST']) && preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1]))/', $_SERVER['HTTP_HOST']));

if ($isLocal) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: 'unifranz_cal');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');
} else {
    // Vercel / Supabase environment variables
    define('DB_HOST', getenv('DB_HOST') ?: 'sql203.infinityfree.com');
    define('DB_NAME', getenv('DB_NAME') ?: 'if0_42131777_redes');
    define('DB_USER', getenv('DB_USER') ?: 'if0_42131777');
    define('DB_PASS', getenv('DB_PASS') ?: 'PLOtKer1lMLElnf');
    define('DB_DRIVER', getenv('DB_DRIVER') ?: 'pgsql'); // Supabase utiliza pgsql
}
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_CHARSET', 'utf8mb4');

// Configuración de la aplicación
define('APP_NAME', 'UNIFRANZ Online');
define('APP_VERSION', '1.0.0');
define('APP_URL', '');               // URL base de la aplicación (sin slash final)
define('SESSION_LIFETIME', 86400);   // 24 horas

/**
 * Obtener conexión PDO a la base de datos
 * @return PDO
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
                    PDO::ATTR_EMULATE_PREPARES   => false
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
            // Hide the 'observaciones' field from the UI by setting it to invisible in database
            try {
                $pdo->exec("UPDATE pestana_campos SET visible = 0 WHERE nombre_campo = 'observaciones'");
            } catch (Exception $e) {}
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Error de conexión a la base de datos']));
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
