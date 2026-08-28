<?php
require_once __DIR__ . '/../config/supabase.php';
$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'get';
switch ($action) {
    case 'get':
        $cid = intval($_GET['contenido_id'] ?? 0);
        $res = sb_get('metricas', 'contenido_id=eq.' . $cid . '&order=fecha_registro.desc&limit=1');
        jsonResponse(['data' => $res['data'][0] ?? null]);
    case 'save':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $cid   = intval($input['contenido_id'] ?? 0);
        if (!$cid) jsonResponse(['error' => 'contenido_id requerido'], 400);
        $exists = sb_get('metricas', 'contenido_id=eq.' . $cid . '&limit=1');
        $body   = array_intersect_key($input, array_flip(['contenido_id','alcance','impresiones','interacciones','clicks','guardados','compartidos','comentarios','seguidores_ganados','reproducciones','fecha_registro']));
        if (!empty($exists['data'])) {
            sb_patch('metricas', 'contenido_id=eq.' . $cid, $body);
        } else {
            sb_post('metricas', $body);
        }
        jsonResponse(['success' => true]);

    case 'save_captura':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input   = getJsonInput();
        $cid     = intval($input['contenido_id'] ?? 0);
        $imgData = $input['image_data'] ?? '';
        if (!$cid || !$imgData) jsonResponse(['error' => 'Datos incompletos'], 400);

        $uploaded = _uploadBase64ToStorage($imgData, $cid, 'captura');
        if (!$uploaded) jsonResponse(['error' => 'Error subiendo imagen a Storage', 'debug' => $uploaded], 500);

        // Remove existing captura_post for this content
        sb_delete('contenido_imagenes', 'contenido_id=eq.' . $cid . '&tipo=eq.captura_post');
        $res = sb_post('contenido_imagenes', [
            'contenido_id'   => $cid,
            'tipo'           => 'captura_post',
            'ruta'           => $uploaded['url'],
            'nombre_guardado'=> $uploaded['path'],
            'nombre_original'=> 'captura.png',
            'tamano'         => $uploaded['size'],
        ]);
        if (empty($res['data'])) jsonResponse(['error' => 'Error guardando en DB', 'debug' => $res], 500);
        jsonResponse(['success' => true, 'url' => $uploaded['url']]);

    case 'delete_captura':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $cid   = intval($input['contenido_id'] ?? 0);
        if (!$cid) jsonResponse(['error' => 'contenido_id requerido'], 400);
        $existing = sb_get('contenido_imagenes', 'contenido_id=eq.' . $cid . '&tipo=eq.captura_post&limit=1');
        if (!empty($existing['data'])) {
            $storagePath = $existing['data'][0]['nombre_guardado'] ?? '';
            if ($storagePath) sb_storage_delete('imagenes', [$storagePath]);
        }
        sb_delete('contenido_imagenes', 'contenido_id=eq.' . $cid . '&tipo=eq.captura_post');
        jsonResponse(['success' => true]);

    case 'save_referencia_visual':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input   = getJsonInput();
        $cid     = intval($input['contenido_id'] ?? 0);
        $imgData = $input['image_data'] ?? '';
        if (!$cid || !$imgData) jsonResponse(['error' => 'Datos incompletos', 'cid' => $cid, 'has_data' => !empty($imgData)], 400);

        // Log incoming data size for debugging
        $dataSize = strlen($imgData);
        if ($dataSize > 10 * 1024 * 1024) { // > 10MB base64 = ~7.5MB image
            jsonResponse(['error' => 'Imagen demasiado grande (máx. 7MB)', 'size_bytes' => $dataSize], 413);
        }

        $uploaded = _uploadBase64ToStorage($imgData, $cid, 'ref');
        if (!$uploaded || !empty($uploaded['__error'])) {
            jsonResponse(['error' => 'Error subiendo imagen a Supabase Storage', 'details' => $uploaded], 500);
        }

        $res = sb_post('contenido_imagenes', [
            'contenido_id'   => $cid,
            'tipo'           => 'referencia_visual',
            'ruta'           => $uploaded['url'],
            'nombre_guardado'=> $uploaded['path'],
            'nombre_original'=> 'referencia.png',
            'tamano'         => $uploaded['size'],
        ]);
        if (empty($res['data'])) jsonResponse(['error' => 'Error guardando en DB', 'debug' => $res], 500);
        $newId = $res['data'][0]['id'] ?? null;
        jsonResponse(['success' => true, 'id' => $newId, 'url' => $uploaded['url']]);

    case 'delete_referencia_visual':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input  = getJsonInput();
        $imgId  = intval($input['imagen_id'] ?? 0);
        if (!$imgId) jsonResponse(['error' => 'imagen_id requerido'], 400);
        $existing = sb_get('contenido_imagenes', 'id=eq.' . $imgId . '&tipo=eq.referencia_visual&limit=1');
        if (!empty($existing['data'])) {
            $storagePath = $existing['data'][0]['nombre_guardado'] ?? '';
            if ($storagePath) sb_storage_delete('imagenes', [$storagePath]);
        }
        sb_delete('contenido_imagenes', 'id=eq.' . $imgId . '&tipo=eq.referencia_visual');
        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}

// ─── Helper: decode base64 data URL and upload to Supabase Storage ────
function _uploadBase64ToStorage($dataUrl, $contenidoId, $prefix = 'img') {
    if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $dataUrl, $m)) return null;
    $ext      = $m[1] === 'jpeg' ? 'jpg' : $m[1];
    $binary   = base64_decode($m[2]);
    if (!$binary) return null;

    $mimeMap = ['png'=>'image/png','jpg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp'];
    $mime    = $mimeMap[$ext] ?? 'image/png';
    $path    = $contenidoId . '/' . $prefix . '_' . time() . '.' . $ext;

    $result = sb_storage_upload('imagenes', $path, $binary, $mime);
    if ($result['code'] < 200 || $result['code'] >= 300) {
        // Return result so callers can expose the real Supabase error
        return ['__error' => true, 'code' => $result['code'], 'body' => $result['body'] ?? ''];
    }

    return [
        'path' => $path,
        'url'  => sb_storage_url('imagenes', $path),
        'size' => strlen($binary),
    ];
}
