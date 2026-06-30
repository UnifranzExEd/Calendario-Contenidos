<?php
// Diagnostic endpoint - remove after debugging
header('Content-Type: application/json');
$info = [
    'php_version' => PHP_VERSION,
    'pdo_drivers' => PDO::getAvailableDrivers(),
    'pgsql_loaded' => extension_loaded('pdo_pgsql'),
    'test' => 'v3',
    'query_string' => $_SERVER['QUERY_STRING'] ?? 'NOT SET',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
    'get_params' => $_GET,
];

// Try connecting
try {
    $pdo = new PDO(
        "pgsql:host=db.fhnolvqocysnjwgsdflq.supabase.co;port=5432;dbname=postgres",
        "postgres",
        "P6mIlecuZClU1qyU"
    );
    $info['connection'] = 'SUCCESS';
    $info['tables'] = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public'")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $info['connection'] = 'FAILED';
    $info['error'] = $e->getMessage();
}

echo json_encode($info, JSON_PRETTY_PRINT);
