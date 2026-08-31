<?php
define('SB_URL', 'https://sovizuthexmkfabcspsd.supabase.co');
define('SB_KEY', 'sb_secret_' . 'RGiKa27vBdmkjiEZJXxmlw_HPwmpjTR');

function sb_get($table, $filter = '') {
    $url = SB_URL . '/rest/v1/' . $table . ($filter ? '?' . $filter : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SB_KEY,
            'Authorization: Bearer ' . SB_KEY,
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

// 1. Fetch matching user IDs
$responsable = 'rubi';
$users = sb_get('usuarios', 'nombre=ilike.*' . urlencode($responsable) . '*&select=id,nombre');
var_dump($users);

$ids = array_column($users, 'id');
if (empty($ids)) {
    $ids = [-1];
}
$ids_str = implode(',', $ids);

// 2. Build filter strings
$responsable_filter_contenidos = 'or=(creado_por.in.(' . $ids_str . '),postproductor_id.in.(' . $ids_str . '))&limit=2';
var_dump($responsable_filter_contenidos);

$contenidos = sb_get('contenidos', $responsable_filter_contenidos);
var_dump($contenidos);
