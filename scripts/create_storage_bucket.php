#!/usr/bin/env php
<?php
/**
 * Create the 'imagenes' bucket in Supabase Storage
 * Run once: php scripts/create_storage_bucket.php
 */
define('SB_URL', 'https://fhnolvqocysnjwgsdflq.supabase.co');
define('SB_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZobm9sdnFvY3lzbmp3Z3NkZmxxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4Mjc1NzQ5NSwiZXhwIjoyMDk4MzMzNDk1fQ.IO59t9zhCbyFi_nHNjMlrckHWJEdzYU4-5gCVbgWaog');

// Create bucket
$url = SB_URL . '/storage/v1/bucket';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'apikey: ' . SB_KEY,
        'Authorization: Bearer ' . SB_KEY,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'id'     => 'imagenes',
        'name'   => 'imagenes',
        'public' => true,
        'file_size_limit' => 5242880, // 5MB
        'allowed_mime_types' => ['image/png', 'image/jpeg', 'image/gif', 'image/webp'],
    ]),
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $code\n";
echo "Response: $res\n";

if ($code >= 200 && $code < 300) {
    echo "\n✅ Bucket 'imagenes' creado exitosamente!\n";
} else {
    $data = json_decode($res, true);
    if (strpos($res, 'already exists') !== false) {
        echo "\n✅ Bucket 'imagenes' ya existe.\n";
    } else {
        echo "\n❌ Error al crear bucket.\n";
    }
}
