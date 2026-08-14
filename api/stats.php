<?php
/**
 * API de Estadísticas UNIFRANZ Calendar
 * Endpoint público (requiere API Key) — solo lectura
 * 
 * Acciones:
 *   resumen           → totales del mes (diseños en postproducción, microtareas, etc.)
 *   postproduccion    → diseños enviados a post-producción con detalle
 *   microtareas       → estado de microtareas (kanban)
 *   piezas_por_mes    → evolución anual
 *   piezas_por_red    → desglose por red social
 *   piezas_por_tipo   → desglose por pestaña
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ─── Config ───────────────────────────────────────────────────────────
define('SB_URL', 'https://sovizuthexmkfabcspsd.supabase.co');
define('SB_KEY', 'sb_secret_' . 'RGiKa27vBdmkjiEZJXxmlw_HPwmpjTR');
define('STATS_API_KEY', 'sk_stats_e727955ad8a6cc63641ebd045900757d');

// ─── Auth ─────────────────────────────────────────────────────────────
$provided_key = $_GET['api_key']
    ?? (function() {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        return str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : null;
    })();

if ($provided_key !== STATS_API_KEY) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'API Key inválida o no proporcionada.',
        'hint'    => 'Usa ?api_key=YOUR_KEY o el header Authorization: Bearer YOUR_KEY'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── HTTP Helper ──────────────────────────────────────────────────────
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

function sb_multi(array $queries) {
    $headers = ['apikey: ' . SB_KEY, 'Authorization: Bearer ' . SB_KEY];
    $mh = curl_multi_init();
    $handles = [];
    foreach ($queries as $key => $path) {
        $ch = curl_init(SB_URL . '/rest/v1/' . ltrim($path, '/'));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 10]);
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }
    $active = null;
    do { curl_multi_exec($mh, $active); } while ($active);
    $results = [];
    foreach ($handles as $key => $ch) {
        $results[$key] = json_decode(curl_multi_getcontent($ch), true) ?? [];
        curl_multi_remove_handle($mh, $ch);
    }
    curl_multi_close($mh);
    return $results;
}

function jsOk($data) {
    echo json_encode(array_merge(['success' => true, 'generated_at' => date('c')], $data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
function jsErr($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Filters ──────────────────────────────────────────────────────────
$action  = $_GET['action']  ?? 'resumen';
$mes     = strtoupper(trim($_GET['mes']    ?? ''));
$anio    = intval($_GET['anio']   ?? date('Y'));
$pestana = strtolower(trim($_GET['pestana'] ?? ''));
$responsable = strtolower(trim($_GET['responsable'] ?? ''));

$responsable_filter_contenidos = '';
$responsable_filter_microtareas = '';
if ($responsable) {
    $users = sb_get('usuarios', 'nombre=ilike.*' . urlencode($responsable) . '*&select=id');
    $ids = array_column($users, 'id');
    if (empty($ids)) {
        $ids = [-1]; // Si no hay match, forzamos un ID inexistente
    }
    $ids_str = implode(',', $ids);
    $responsable_filter_contenidos = 'or=(creado_por.in.(' . $ids_str . '),postproductor_id.in.(' . $ids_str . '))';
    $responsable_filter_microtareas = 'responsable_id=in.(' . $ids_str . ')';
}

// Resolve pestana slug → id if given
$pestana_id = null;
if ($pestana) {
    $p = sb_get('pestanas', 'slug=eq.' . urlencode($pestana) . '&select=id&limit=1');
    $pestana_id = $p[0]['id'] ?? null;
}

$MESES_ORDEN = ['ENERO'=>1,'FEBRERO'=>2,'MARZO'=>3,'ABRIL'=>4,'MAYO'=>5,'JUNIO'=>6,
                'JULIO'=>7,'AGOSTO'=>8,'SEPTIEMBRE'=>9,'OCTUBRE'=>10,'NOVIEMBRE'=>11,'DICIEMBRE'=>12];

// Build base filter for contenidos
function base_filter($mes, $anio, $pestana_id, $extra = '') {
    global $responsable_filter_contenidos;
    $f = ['deleted_at=is.null', 'anio=eq.' . $anio];
    if ($mes)        $f[] = 'mes=eq.' . urlencode($mes);
    if ($pestana_id) $f[] = 'pestana_id=eq.' . $pestana_id;
    if ($responsable_filter_contenidos) $f[] = $responsable_filter_contenidos;
    if ($extra)      $f[] = $extra;
    return implode('&', $f);
}

// ─── Router ───────────────────────────────────────────────────────────
switch ($action) {

    // ── RESUMEN ──────────────────────────────────────────────────────
    case 'resumen':
        $mesActual = $mes ?: strtoupper(['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO',
            'JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'][date('n')-1]);

        global $responsable_filter_microtareas;
        $data = sb_multi([
            'contenidos'    => 'contenidos?select=estado,prioridad&' . base_filter($mesActual, $anio, $pestana_id),
            'postprod'      => 'contenidos?select=id,tema,estado,red_social,fecha,pestana_id&' 
                             . base_filter($mesActual, $anio, $pestana_id, 'enviar_postproduccion=eq.1'),
            'microtareas'   => 'microtareas?select=estado,prioridad&deleted_at=is.null' . ($responsable_filter_microtareas ? '&' . $responsable_filter_microtareas : ''),
        ]);

        $contenidos   = $data['contenidos'];
        $postprod     = $data['postprod'];
        $microtareas  = $data['microtareas'];

        // Count by estado
        $estados = [];
        foreach ($contenidos as $c) {
            $e = $c['estado'] ?? 'Sin estado';
            $estados[$e] = ($estados[$e] ?? 0) + 1;
        }

        $en_espera   = ($estados['En elaboración'] ?? 0) + ($estados['Redacción'] ?? 0);
        $en_proceso  = ($estados['En revisión'] ?? 0) + ($estados['Producción'] ?? 0) + ($estados['Corrección'] ?? 0);
        $hechas      = ($estados['Aprobado'] ?? 0) + ($estados['Programado'] ?? 0);
        $publicadas  = $estados['Publicado'] ?? 0;

        // Prioridades
        $prios = ['Alta' => 0, 'Media' => 0, 'Baja' => 0];
        foreach ($contenidos as $c) {
            $p = $c['prioridad'] ?? 'Media';
            if (isset($prios[$p])) $prios[$p]++;
        }

        // Microtareas by estado
        $mt_estados = [];
        foreach ($microtareas as $mt) {
            $e = $mt['estado'] ?? 'Pendiente';
            $mt_estados[$e] = ($mt_estados[$e] ?? 0) + 1;
        }

        jsOk([
            'periodo' => ['mes' => $mesActual, 'anio' => $anio],
            'resumen' => [
                'total_piezas'           => count($contenidos),
                'enviadas_postproduccion' => count($postprod),
                'en_espera'              => $en_espera,
                'en_proceso'             => $en_proceso,
                'hechas'                 => $hechas,
                'publicadas'             => $publicadas,
            ],
            'prioridades'  => $prios,
            'detalle_por_estado' => array_map(fn($e, $n) => ['estado' => $e, 'cantidad' => $n], array_keys($estados), $estados),
            'microtareas'  => [
                'total'           => count($microtareas),
                'por_estado'      => array_map(fn($e, $n) => ['estado' => $e, 'cantidad' => $n], array_keys($mt_estados), $mt_estados),
            ],
        ]);
        break;

    // ── POST-PRODUCCIÓN ───────────────────────────────────────────────
    case 'postproduccion':
        $filter = base_filter($mes, $anio, $pestana_id, 'enviar_postproduccion=eq.1');
        $filter .= '&select=id,tema,fecha,mes,estado,red_social,formato,prioridad,postproductor_id,pestana_id&order=fecha.asc.nullslast';

        $contenidos = sb_get('contenidos', $filter);

        // Get pestanas for lookup
        $pestanas_list = sb_get('pestanas', 'select=id,nombre,slug,color');
        $pmap = [];
        foreach ($pestanas_list as $p) $pmap[$p['id']] = $p;

        // Enrich
        $result = [];
        foreach ($contenidos as $c) {
            $pst = $pmap[$c['pestana_id'] ?? 0] ?? [];
            $result[] = [
                'id'             => $c['id'],
                'tema'           => $c['tema'],
                'fecha'          => $c['fecha'],
                'mes'            => $c['mes'],
                'estado'         => $c['estado'],
                'red_social'     => $c['red_social'],
                'formato'        => $c['formato'],
                'prioridad'      => $c['prioridad'],
                'pestana'        => $pst['nombre'] ?? '',
                'pestana_slug'   => $pst['slug']   ?? '',
                'postproductor_asignado' => !empty($c['postproductor_id']),
            ];
        }

        // Group by estado
        $por_estado = [];
        foreach ($result as $c) {
            $e = $c['estado'];
            $por_estado[$e] = ($por_estado[$e] ?? 0) + 1;
        }

        jsOk([
            'periodo'     => ['mes' => $mes ?: 'TODOS', 'anio' => $anio],
            'total'       => count($result),
            'por_estado'  => array_map(fn($e, $n) => ['estado' => $e, 'cantidad' => $n], array_keys($por_estado), $por_estado),
            'diseños'     => $result,
        ]);
        break;

    // ── MICROTAREAS ───────────────────────────────────────────────────
    case 'microtareas':
        global $responsable_filter_microtareas;
        $filter = 'deleted_at=is.null&select=id,titulo,descripcion,estado,prioridad,fecha_entrega,responsable_id&order=created_at.desc';
        if ($responsable_filter_microtareas) {
            $filter .= '&' . $responsable_filter_microtareas;
        }
        if ($estado_filter = strtolower($_GET['estado'] ?? '')) {
            $filter .= '&estado=ilike.' . urlencode($estado_filter);
        }
        $microtareas = sb_get('microtareas', $filter);

        $por_estado = [];
        $por_prioridad = [];
        foreach ($microtareas as $mt) {
            $e = $mt['estado']    ?? 'Pendiente';
            $p = $mt['prioridad'] ?? 'Media';
            $por_estado[$e]    = ($por_estado[$e]    ?? 0) + 1;
            $por_prioridad[$p] = ($por_prioridad[$p] ?? 0) + 1;
        }

        jsOk([
            'total'        => count($microtareas),
            'por_estado'   => array_map(fn($e, $n) => ['estado' => $e, 'cantidad' => $n], array_keys($por_estado), $por_estado),
            'por_prioridad'=> array_map(fn($p, $n) => ['prioridad' => $p, 'cantidad' => $n], array_keys($por_prioridad), $por_prioridad),
            'microtareas'  => $microtareas,
        ]);
        break;

    // ── PIEZAS POR MES ────────────────────────────────────────────────
    case 'piezas_por_mes':
        global $responsable_filter_contenidos;
        $all = sb_get('contenidos', 'deleted_at=is.null&anio=eq.' . $anio
            . ($pestana_id ? '&pestana_id=eq.' . $pestana_id : '')
            . ($responsable_filter_contenidos ? '&' . $responsable_filter_contenidos : '')
            . '&select=mes,estado,enviar_postproduccion');

        $meses_data = [];
        foreach (array_keys($MESES_ORDEN) as $m) {
            $meses_data[$m] = ['mes' => $m, 'total' => 0, 'publicadas' => 0, 'postproduccion' => 0, 'estados' => []];
        }
        foreach ($all as $c) {
            $m = $c['mes'] ?? '';
            if (!isset($meses_data[$m])) continue;
            $meses_data[$m]['total']++;
            if ($c['estado'] === 'Publicado') $meses_data[$m]['publicadas']++;
            if ($c['enviar_postproduccion'])   $meses_data[$m]['postproduccion']++;
            $e = $c['estado'];
            $meses_data[$m]['estados'][$e] = ($meses_data[$m]['estados'][$e] ?? 0) + 1;
        }
        // Convert estados to array
        foreach ($meses_data as &$md) {
            $md['estados'] = array_map(fn($e, $n) => ['estado' => $e, 'cantidad' => $n], array_keys($md['estados']), $md['estados']);
        }

        jsOk(['anio' => $anio, 'meses' => array_values($meses_data)]);
        break;

    // ── PIEZAS POR RED ────────────────────────────────────────────────
    case 'piezas_por_red':
        $all = sb_get('contenidos', 'deleted_at=is.null&' . base_filter($mes, $anio, $pestana_id)
            . '&select=red_social,estado,enviar_postproduccion&order=red_social.asc');

        $redes = [];
        foreach ($all as $c) {
            $r = $c['red_social'] ?? 'Sin red';
            if (!isset($redes[$r])) $redes[$r] = ['red_social' => $r, 'total' => 0, 'postproduccion' => 0, 'estados' => []];
            $redes[$r]['total']++;
            if ($c['enviar_postproduccion']) $redes[$r]['postproduccion']++;
            $e = $c['estado'];
            $redes[$r]['estados'][$e] = ($redes[$r]['estados'][$e] ?? 0) + 1;
        }
        usort($redes, fn($a, $b) => $b['total'] - $a['total']);
        foreach ($redes as &$rd) {
            $rd['estados'] = array_map(fn($e, $n) => ['estado' => $e, 'cantidad' => $n], array_keys($rd['estados']), $rd['estados']);
        }

        jsOk(['periodo' => ['mes' => $mes ?: 'TODOS', 'anio' => $anio], 'redes' => array_values($redes)]);
        break;

    // ── PIEZAS POR TIPO/PESTAÑA ───────────────────────────────────────
    case 'piezas_por_tipo':
        $pestanas_list = sb_get('pestanas', 'activo=eq.1&select=id,nombre,slug,color&order=orden.asc');
        $all = sb_get('contenidos', 'deleted_at=is.null&' . base_filter($mes, $anio, null)
            . '&select=pestana_id,estado,enviar_postproduccion');

        $pmap = [];
        foreach ($pestanas_list as $p) $pmap[$p['id']] = ['pestana' => $p['nombre'], 'slug' => $p['slug'], 'color' => $p['color'], 'total' => 0, 'postproduccion' => 0, 'estados' => []];

        foreach ($all as $c) {
            $pid = $c['pestana_id'] ?? 0;
            if (!isset($pmap[$pid])) continue;
            $pmap[$pid]['total']++;
            if ($c['enviar_postproduccion']) $pmap[$pid]['postproduccion']++;
            $e = $c['estado'];
            $pmap[$pid]['estados'][$e] = ($pmap[$pid]['estados'][$e] ?? 0) + 1;
        }
        foreach ($pmap as &$pm) {
            $pm['estados'] = array_map(fn($e, $n) => ['estado' => $e, 'cantidad' => $n], array_keys($pm['estados']), $pm['estados']);
        }

        jsOk(['periodo' => ['mes' => $mes ?: 'TODOS', 'anio' => $anio], 'tabs' => array_values($pmap)]);
        break;

    default:
        jsErr('Acción no válida. Acciones disponibles: resumen, postproduccion, microtareas, piezas_por_mes, piezas_por_red, piezas_por_tipo');
}
