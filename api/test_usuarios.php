<?php
require_once __DIR__ . '/../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Test 1: Can we read usuarios?
$read = sb_get('usuarios', 'select=id,nombre,email,rol,activo&order=id.asc');

// Test 2: Try inserting a test user
$testInsert = sb_post('usuarios', [
    'nombre'   => 'TEST_DELETE_ME',
    'email'    => 'test_' . time() . '@test.com',
    'password' => password_hash('test123', PASSWORD_BCRYPT),
    'rol'      => 'community',
    'activo'   => 1,
]);

echo json_encode([
    'read_result' => [
        'code'  => $read['code'],
        'count' => is_array($read['data']) ? count($read['data']) : 0,
        'data'  => $read['data'],
    ],
    'insert_result' => [
        'code' => $testInsert['code'],
        'data' => $testInsert['data'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
