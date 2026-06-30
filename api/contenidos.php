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
        $filters = ['deleted_at=is.null'];
        if ($p = $_GET['pestana'] ?? '') $filters[] = 'pestanas.slug=eq.' . urlencode($p);
        if ($m = $_GET['mes']     ?? '') $filters[] = 'mes=eq.'         . urlencode($m);
        if ($a = $_GET['anio']    ?? '') $filters[] = 'anio=eq.'        . $a;
        if ($e = $_GET['estado']  ?? '') $filters[] = 'estado=eq.'      . urlencode($e);
        if ($r = $_GET['red_social'] ?? '') $filters[] = 'red_social=eq.' . urlencode($r);
        if ($b = $_GET['buyer']   ?? '') $filters[] = 'buyer=eq.'       . urlencode($b);
        if ($pi= $_GET['pilar']   ?? '') $filters[] = 'pilar=eq.'       . urlencode($pi);

        $select = 'select=*,pestanas(slug,nombre,color),contenido_detalle(titulo_post,copy_facebook,copy_instagram,copy_tiktok)';
        $filter = implode('&', $filters);
        $res = sb_get('contenidos', $select . '&' . $filter . '&order=fecha.asc.nullslast,id.asc');

        // Flatten embedded data for front-end compatibility
        $items = [];
        foreach (($res['data'] ?? []) as $c) {
            $det = $c['contenido_detalle'][0] ?? [];
            unset($c['contenido_detalle']);
            $pst = $c['pestanas'] ?? [];
            unset($c['pestanas']);
            $c['pestana_slug']   = $pst['slug']   ?? '';
            $c['pestana_nombre'] = $pst['nombre'] ?? '';
            $c['pestana_color']  = $pst['color']  ?? '';
            $c['titulo_post']    = $det['titulo_post']    ?? null;
            $c['copy_facebook']  = $det['copy_facebook']  ?? null;
            $c['copy_instagram'] = $det['copy_instagram'] ?? null;
            $c['copy_tiktok']    = $det['copy_tiktok']    ?? null;
            $items[] = $c;
        }
        jsonResponse(['data' => $items, 'total' => count($items)]);

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        // ONE request to Supabase - embed all related data
        $select = implode(',', [
            '*',
            'pestanas(slug,nombre,color)',
            'contenido_detalle(titulo_post,copy_facebook,copy_instagram,copy_tiktok)',
            'contenido_slides(numero_slide,texto,notas)',
            'metricas(alcance,impresiones,interacciones,clicks,guardados,compartidos,comentarios,reproducciones,fecha_registro)',
            'historial_estado(estado_anterior,estado_nuevo,usuario_id,comentario,created_at)',
            'contenido_imagenes(filename,tipo)',
            'contenido_hashtags(hashtags(id,tag,categoria,red_social))',
        ]);
        $res = sb_get('contenidos', 'id=eq.' . $id . '&select=' . urlencode($select) . '&limit=1');
        $c   = $res['data'][0] ?? null;
        if (!$c) jsonResponse(['error' => 'No encontrado'], 404);

        // Flatten embedded data
        $pst = $c['pestanas'] ?? [];
        unset($c['pestanas']);
        $c['pestana_slug']   = $pst['slug']   ?? '';
        $c['pestana_nombre'] = $pst['nombre'] ?? '';
        $c['pestana_color']  = $pst['color']  ?? '';
        $c['detalle']   = $c['contenido_detalle'][0]  ?? null;  unset($c['contenido_detalle']);
        $c['slides']    = $c['contenido_slides']      ?? [];    unset($c['contenido_slides']);
        $c['metricas']  = $c['metricas'][0]           ?? null;
        $c['historial'] = array_slice($c['historial_estado'] ?? [], 0, 20); unset($c['historial_estado']);
        $c['captura']   = null;
        foreach (($c['contenido_imagenes'] ?? []) as $img) {
            if ($img['tipo'] === 'captura_post') { $c['captura'] = $img['filename']; break; }
        }
        unset($c['contenido_imagenes']);
        $c['hashtags'] = array_map(fn($h) => $h['hashtags'] ?? [], $c['contenido_hashtags'] ?? []);
        unset($c['contenido_hashtags']);

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
        if (isset($input['titulo_post']) || isset($input['copy_facebook'])) {
            sb_post('contenido_detalle', [
                'contenido_id'  => $cid,
                'titulo_post'   => $input['titulo_post']    ?? null,
                'copy_facebook' => $input['copy_facebook']  ?? null,
                'copy_instagram'=> $input['copy_instagram'] ?? null,
                'copy_tiktok'   => $input['copy_tiktok']    ?? null,
            ]);
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
        $detFields = ['titulo_post','copy_facebook','copy_instagram','copy_tiktok'];
        $detBody   = [];
        foreach ($detFields as $f) { if (array_key_exists($f, $input)) $detBody[$f] = $input[$f]; }
        if ($detBody) {
            $detExists = sb_get('contenido_detalle', 'contenido_id=eq.' . $id . '&limit=1');
            if (!empty($detExists['data'])) {
                sb_patch('contenido_detalle', 'contenido_id=eq.' . $id, $detBody);
            } else {
                sb_post('contenido_detalle', array_merge(['contenido_id' => $id], $detBody));
            }
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

        $detRes = sb_get('contenido_detalle', 'contenido_id=eq.' . $id . '&limit=1');
        if (!empty($detRes['data'])) {
            $d = $detRes['data'][0]; unset($d['id'], $d['contenido_id']);
            sb_post('contenido_detalle', array_merge(['contenido_id' => $newId], $d));
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
