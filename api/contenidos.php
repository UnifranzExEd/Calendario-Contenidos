<?php
require_once __DIR__ . '/../config/supabase.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$MESES  = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];

function mesFromFecha($fecha) {
    global $MESES;
    if (!$fecha) return null;
    return $MESES[intval(date('n', strtotime($fecha))) - 1] ?? null;
}

switch ($action) {

    case 'list':
        // Resolve pestana slug to id first (no embedding - safer)
        $pestana_slug = $_GET['pestana'] ?? '';
        $filters = ['deleted_at=is.null'];
        if ($pestana_slug) {
            $pRes = sb_get('pestanas', 'slug=eq.' . urlencode($pestana_slug) . '&select=id&limit=1');
            $pid  = $pRes['data'][0]['id'] ?? 0;
            if ($pid) $filters[] = 'pestana_id=eq.' . $pid;
        }
        if ($m = $_GET['mes']        ?? '') $filters[] = 'mes=eq.'       . urlencode($m);
        if ($a = $_GET['anio']       ?? '') $filters[] = 'anio=eq.'      . $a;
        if ($e = $_GET['estado']     ?? '') $filters[] = 'estado=eq.'    . urlencode($e);
        if ($r = $_GET['red_social'] ?? '') $filters[] = 'red_social=eq.' . urlencode($r);
        if ($b = $_GET['buyer']      ?? '') $filters[] = 'buyer=eq.'     . urlencode($b);
        if ($pi= $_GET['pilar']      ?? '') $filters[] = 'pilar=eq.'     . urlencode($pi);

        $res        = sb_get('contenidos', implode('&', $filters) . '&order=fecha.asc.nullslast,id.asc');
        $contenidos = is_array($res['data']) ? $res['data'] : [];

        // One shared query for pestana metadata
        $pestMap = [];
        $p2 = sb_get('pestanas', 'select=id,slug,nombre,color');
        foreach (($p2['data'] ?? []) as $p) {
            if (isset($p['id'])) $pestMap[$p['id']] = $p;
        }
        foreach ($contenidos as &$c) {
            if (!is_array($c)) continue;
            $pd = $pestMap[$c['pestana_id'] ?? 0] ?? [];
            $c['pestana_slug']   = $pd['slug']   ?? '';
            $c['pestana_nombre'] = $pd['nombre'] ?? '';
            $c['pestana_color']  = $pd['color']  ?? '';
        }
        unset($c);
        jsonResponse(['data' => $contenidos, 'total' => count($contenidos)]);

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        // Manual joins since Supabase doesn't have Foreign Keys set up
        $res = sb_get('contenidos', 'id=eq.' . $id . '&limit=1');
        $c   = $res['data'][0] ?? null;
        if (!$c) jsonResponse(['error' => 'No encontrado'], 404);

        // Fetch related data
        $pestanas = sb_get('pestanas', 'id=eq.' . intval($c['pestana_id']));
        $pst = $pestanas['data'][0] ?? [];
        $c['pestana_slug']   = $pst['slug']   ?? '';
        $c['pestana_nombre'] = $pst['nombre'] ?? '';
        $c['pestana_color']  = $pst['color']  ?? '';

        $detalle = sb_get('contenido_detalle', 'contenido_id=eq.' . $id);
        $flatDetalle = [];
        foreach (($detalle['data'] ?? []) as $row) {
            if (isset($row['campo'])) {
                $flatDetalle[$row['campo']] = $row['valor'] ?? '';
            }
        }
        $c['detalle'] = $flatDetalle;

        $slides = sb_get('contenido_slides', 'contenido_id=eq.' . $id);
        $c['slides'] = $slides['data'] ?? [];

        $metricas = sb_get('metricas', 'contenido_id=eq.' . $id);
        $c['metricas'] = $metricas['data'][0] ?? null;

        $historial = sb_get('historial_estado', 'contenido_id=eq.' . $id . '&order=created_at.desc&limit=20');
        $c['historial'] = $historial['data'] ?? [];

        $imagenes = sb_get('contenido_imagenes', 'contenido_id=eq.' . $id);
        $c['captura'] = null;
        foreach (($imagenes['data'] ?? []) as $img) {
            if ($img['tipo'] === 'captura_post') { $c['captura'] = $img['filename']; break; }
        }

        $hashtags = sb_get('contenido_hashtags', 'contenido_id=eq.' . $id);
        $c['hashtags'] = []; // we can skip full hashtag hydration if not strictly needed or do it manually


        jsonResponse(['data' => $c]);


    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $perms = getPermissions($user['rol']);
        if (!$perms['crear_contenido']) jsonResponse(['error' => 'Sin permisos'], 403);

        $input = getJsonInput();
        $pRes  = sb_get('pestanas', 'slug=eq.' . urlencode($input['pestana'] ?? '') . '&select=id&limit=1');
        $pid   = $pRes['data'][0]['id'] ?? null;
        if (!$pid) jsonResponse(['error' => 'Pestaña no válida'], 400);

        $fecha = $input['fecha'] ?? null;
        $body  = [
            'pestana_id'                 => $pid,
            'semana'                     => $input['semana']   ?? null,
            'mes'                        => mesFromFecha($fecha),
            'anio'                       => $fecha ? intval(date('Y', strtotime($fecha))) : intval(date('Y')),
            'fecha'                      => $fecha,
            'buyer'                      => $input['buyer']    ?? null,
            'pilar'                      => $input['pilar']    ?? null,
            'atributo'                   => $input['atributo'] ?? null,
            'etapa'                      => $input['etapa']    ?? null,
            'aspecto'                    => $input['aspecto']  ?? null,
            'carrera'                    => $input['carrera']  ?? null,
            'tema'                       => $input['tema']     ?? null,
            'idea'                       => $input['idea']     ?? null,
            'red_social'                 => $input['red_social'] ?? null,
            'estado'                     => $input['estado']   ?? 'En elaboración',
            'error_ortografico'          => !empty($input['error_ortografico']),
            'error_ortografico_detalle'  => $input['error_ortografico_detalle'] ?? null,
            'formato'                    => $input['formato']  ?? null,
            'horario'                    => $input['horario']  ?? null,
            'enlace_contenido'           => $input['enlace_contenido'] ?? null,
            'enlace_publicado'           => $input['enlace_publicado'] ?? null,
            'enlace_diseno'              => $input['enlace_diseno']    ?? null,
            'observaciones'              => $input['observaciones']    ?? null,
            'enviar_postproduccion'      => !empty($input['enviar_postproduccion']),
            'creado_por'                 => $user['id'],
        ];

        $cRes = sb_post('contenidos', $body);
        $cid  = $cRes['data'][0]['id'] ?? null;
        if (!$cid) jsonResponse(['error' => 'Error al crear'], 500);

        // Detail
        $detBody = [];
        foreach (['titulo_post','copy_facebook','copy_instagram','copy_tiktok','copy_linkedin','cta'] as $f) {
            if (isset($input[$f])) {
                $detBody[] = ['contenido_id' => $cid, 'campo' => $f, 'valor' => $input[$f]];
            }
        }
        if (!empty($detBody)) {
            sb_post('contenido_detalle', $detBody);
        }
        // Slides
        foreach (($input['slides'] ?? []) as $i => $slide) {
            sb_post('contenido_slides', ['contenido_id' => $cid, 'numero_slide' => $i+1, 'texto' => $slide['texto'] ?? '', 'notas' => $slide['notas'] ?? null]);
        }
        // History
        sb_post('historial_estado', ['contenido_id' => $cid, 'estado_nuevo' => $body['estado'], 'usuario_id' => $user['id'], 'comentario' => 'Contenido creado']);

        jsonResponse(['success' => true, 'id' => $cid], 201);

    case 'update':
        if (!in_array($method, ['POST','PUT'])) jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $id    = intval($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        $curRes = sb_get('contenidos', 'id=eq.' . $id . '&limit=1');
        $cur    = $curRes['data'][0] ?? null;
        if (!$cur) jsonResponse(['error' => 'No encontrado'], 404);

        $fields = ['buyer','pilar','atributo','etapa','aspecto','carrera','tema','idea','red_social',
                   'estado','error_ortografico','error_ortografico_detalle','formato','horario',
                   'enlace_contenido','enlace_publicado','enlace_diseno','observaciones','semana',
                   'fecha','espectadores','interacciones','postproductor_id','enviar_postproduccion'];
        $body = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $input)) $body[$f] = $input[$f];
        }
        if (isset($body['fecha']) && $body['fecha']) {
            $body['mes']  = mesFromFecha($body['fecha']);
            $body['anio'] = intval(date('Y', strtotime($body['fecha'])));
        }
        $body['actualizado_por'] = $user['id'];
        if ($body) sb_patch('contenidos', 'id=eq.' . $id, $body);

        // State history
        if (isset($input['estado']) && $input['estado'] !== $cur['estado']) {
            sb_post('historial_estado', ['contenido_id' => $id, 'estado_anterior' => $cur['estado'], 'estado_nuevo' => $input['estado'], 'usuario_id' => $user['id']]);
        }
        // Detail
        $detFields = ['titulo_post','copy_facebook','copy_instagram','copy_tiktok','copy_linkedin','cta'];
        $detBody   = [];
        foreach ($detFields as $f) { 
            if (array_key_exists($f, $input)) {
                $detBody[] = ['contenido_id' => $id, 'campo' => $f, 'valor' => $input[$f]];
            }
        }
        if (!empty($detBody)) {
            // Delete old details for these fields (or just delete all for this content)
            sb_delete('contenido_detalle', 'contenido_id=eq.' . $id);
            // Insert new key-value rows
            sb_post('contenido_detalle', $detBody);
        }
        // Slides
        if (isset($input['slides'])) {
            sb_delete('contenido_slides', 'contenido_id=eq.' . $id);
            foreach ($input['slides'] as $i => $slide) {
                sb_post('contenido_slides', ['contenido_id' => $id, 'numero_slide' => $i+1, 'texto' => $slide['texto'] ?? '', 'notas' => $slide['notas'] ?? null]);
            }
        }
        jsonResponse(['success' => true]);

    case 'inline':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $id    = intval($input['id'] ?? 0);
        $field = $input['field'] ?? '';
        $value = $input['value'] ?? null;
        if (!$id || !$field) jsonResponse(['error' => 'ID y campo requeridos'], 400);

        $allowed = ['buyer','pilar','atributo','etapa','aspecto','carrera','tema','idea','red_social',
                    'estado','error_ortografico','error_ortografico_detalle','formato','horario',
                    'enlace_contenido','enlace_publicado','enlace_diseno','observaciones','fecha',
                    'espectadores','interacciones','semana','postproductor_id'];
        if (!in_array($field, $allowed)) jsonResponse(['error' => 'Campo no permitido'], 400);

        $body = [$field => $value, 'actualizado_por' => $user['id']];
        if ($field === 'fecha' && $value) {
            $body['mes']  = mesFromFecha($value);
            $body['anio'] = intval(date('Y', strtotime($value)));
        }
        if ($field === 'estado') {
            $curRes = sb_get('contenidos', 'id=eq.' . $id . '&select=estado&limit=1');
            $prev   = $curRes['data'][0]['estado'] ?? null;
            sb_post('historial_estado', ['contenido_id' => $id, 'estado_anterior' => $prev, 'estado_nuevo' => $value, 'usuario_id' => $user['id']]);
        }
        sb_patch('contenidos', 'id=eq.' . $id, $body);
        jsonResponse(['success' => true]);

    case 'delete':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $id    = intval($input['id'] ?? 0);
        sb_patch('contenidos', 'id=eq.' . $id, ['deleted_at' => date('c')]);
        jsonResponse(['success' => true]);

    case 'restore':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        sb_patch('contenidos', 'id=eq.' . intval($input['id'] ?? 0), ['deleted_at' => null]);
        jsonResponse(['success' => true]);

    case 'purge':
        if ($method !== 'POST' || $user['rol'] !== 'admin') jsonResponse(['error' => 'No autorizado'], 403);
        $input = getJsonInput();
        sb_delete('contenidos', 'id=eq.' . intval($input['id'] ?? 0));
        jsonResponse(['success' => true]);

    case 'duplicate':
    case 'link_pauta':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input  = getJsonInput();
        $id     = intval($input['id'] ?? 0);
        $origRes = sb_get('contenidos', 'id=eq.' . $id . '&limit=1');
        $orig   = $origRes['data'][0] ?? null;
        if (!$orig) jsonResponse(['error' => 'No encontrado'], 404);

        unset($orig['id'], $orig['created_at'], $orig['updated_at']);
        $orig['creado_por']         = $user['id'];
        $orig['estado']             = 'En elaboración';
        $orig['postproductor_id']   = null;
        $orig['enlace_contenido']   = null;
        $orig['enlace_publicado']   = null;
        $orig['enlace_diseno']      = null;
        $orig['deleted_at']         = null;

        if ($action === 'link_pauta') {
            $pRes = sb_get('pestanas', 'slug=eq.pauta&select=id&limit=1');
            $orig['pestana_id'] = $pRes['data'][0]['id'] ?? $orig['pestana_id'];
        }

        $newRes = sb_post('contenidos', $orig);
        $newId  = $newRes['data'][0]['id'] ?? null;

        $detRes = sb_get('contenido_detalle', 'contenido_id=eq.' . $id);
        $detBody = [];
        foreach (($detRes['data'] ?? []) as $d) {
            $detBody[] = ['contenido_id' => $newId, 'campo' => $d['campo'], 'valor' => $d['valor']];
        }
        if (!empty($detBody)) {
            sb_post('contenido_detalle', $detBody);
        }
        jsonResponse(['success' => true, 'id' => $newId]);

    case 'shift_date':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input  = getJsonInput();
        $id     = intval($input['id'] ?? 0);
        $curRes = sb_get('contenidos', 'id=eq.' . $id . '&select=fecha&limit=1');
        $fecha  = $curRes['data'][0]['fecha'] ?? null;
        if ($fecha) {
            $nf = date('Y-m-d', strtotime($fecha . ' +1 day'));
            sb_patch('contenidos', 'id=eq.' . $id, ['fecha' => $nf, 'mes' => mesFromFecha($nf), 'anio' => intval(date('Y', strtotime($nf)))]);
        }
        jsonResponse(['success' => true]);

    case 'stats':
        $filter = 'deleted_at=is.null';
        if ($p = $_GET['pestana'] ?? '') $filter .= '&pestanas.slug=eq.' . urlencode($p);
        if ($m = $_GET['mes']     ?? '') $filter .= '&mes=eq.'           . urlencode($m);
        $res   = sb_get('contenidos', $filter . '&select=estado');
        $stats = [];
        foreach (($res['data'] ?? []) as $r) {
            $e = $r['estado'];
            $stats[$e] = ($stats[$e] ?? 0) + 1;
        }
        $out = array_map(fn($e,$t) => ['estado'=>$e,'total'=>$t], array_keys($stats), $stats);
        jsonResponse(['stats' => $out, 'total' => array_sum($stats)]);

    case 'notifications':
        $today  = date('Y-m-d');
        $res    = sb_get('contenidos', 'deleted_at=is.null&estado=in.(Listo,Por publicar,Aprobado)&fecha=lte.' . $today . '&select=id');
        jsonResponse(['success' => true, 'count' => count($res['data'] ?? [])]);

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
