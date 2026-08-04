<?php
/**
 * import_excel.php — Importar filas del Planner 2.0 con lógica de upsert
 * Recibe JSON array de filas desde el frontend (parseadas con SheetJS)
 * Clave única de cada contenido: fecha + codigo_pieza (col A + col C)
 *
 * Estructura Supabase real:
 *   pestanas: id=1 → organicos, id=2 → pagados
 *   Columna P=Orgánico → pestana organicos
 *   Columna Q=Pauta    → pestana pagados
 *   Ambas=Sí           → pestana pagados + tipo_distribucion='pauta+organico'
 */
require_once __DIR__ . '/../config/supabase.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$input = getJsonInput();
$rows  = $input['rows'] ?? [];

if (empty($rows) || !is_array($rows)) {
    jsonResponse(['error' => 'No se recibieron filas válidas'], 400);
}

// ─── Mapa de meses ────────────────────────────────────────────────────
$MESES = [
    1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',
    7=>'JULIO',8=>'AGOSTO',9=>'SEPTIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE'
];
function mesStr($fecha) {
    global $MESES;
    if (!$fecha) return null;
    $n = intval(date('n', strtotime($fecha)));
    return $MESES[$n] ?? null;
}

// ─── Obtener IDs de pestañas reales ──────────────────────────────────
function getPestanaId($slug) {
    $res = sb_get('pestanas', 'slug=eq.' . urlencode($slug) . '&select=id&limit=1');
    return $res['data'][0]['id'] ?? null;
}

$organicosId = getPestanaId('organicos'); // id=1
$pagadosId   = getPestanaId('pagados');   // id=2

if (!$organicosId || !$pagadosId) {
    jsonResponse(['error' => 'No se pudieron obtener las pestañas organicos/pagados'], 500);
}

// ─── Obtener todos los contenidos existentes para hacer upsert ────────
// Clave: fecha + tema (codigo_pieza), no borrados
$existRes = sb_get('contenidos', 'deleted_at=is.null&select=id,fecha,tema,pilar,pestana_id');
$existing  = $existRes['data'] ?? [];

// Mapa: "fecha|tema" → {id, pilar, pestana_id}
$existMap = [];
foreach ($existing as $e) {
    if ($e['fecha'] && $e['tema']) {
        $key = $e['fecha'] . '|' . trim($e['tema']);
        $existMap[$key] = $e;
    }
}

// ─── Procesar filas ────────────────────────────────────────────────────
$results = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => []];

foreach ($rows as $idx => $row) {
    try {
        $fecha       = $row['fecha']        ?? null;  // 'YYYY-MM-DD'
        $codigoPieza = $row['codigo']        ?? null;  // Col C — id único de pieza
        $esOrganico  = !empty($row['es_organico']); // Col P == "Sí"
        $esPauta     = !empty($row['es_pauta']);    // Col Q == "Sí"
        $slides      = $row['slides']        ?? [];   // Array de {texto, notas}

        if (!$fecha || !$codigoPieza) {
            $results['errors'][] = "Fila #{$idx}: sin fecha o código de pieza";
            continue;
        }

        // Determinar pestaña y tipo de distribución
        // Pauta (sola o dual) → pagados | Solo orgánico → organicos
        if ($esPauta) {
            $pestanaId = $pagadosId;
        } else {
            $pestanaId = $organicosId;
        }

        $tipoDistribucion = 'organico';
        if ($esPauta && $esOrganico) $tipoDistribucion = 'pauta+organico';
        elseif ($esPauta)            $tipoDistribucion = 'pauta';

        // Cuerpo del contenido principal
        $body = [
            'pestana_id'      => $pestanaId,
            'fecha'           => $fecha,
            'mes'             => mesStr($fecha),
            'anio'            => intval(date('Y', strtotime($fecha))),
            'semana'          => $row['semana']          ?? null,
            'formato'         => $row['serie_editorial'] ?? null,  // Serie Editorial
            'tema'            => $codigoPieza,                     // Código Pieza → clave + campo TEMA
            'idea'            => $row['conversacion']    ?? null,  // Conversación
            'pilar'           => $row['tipo_pieza']      ?? null,  // Tipo Pieza (col N)
            'red_social'      => $row['red']             ?? null,  // Red (col M)
            'horario'         => $row['headline']        ?? null,  // Headline (col R)
            'observaciones'   => $row['insight']         ?? null,  // Insight (col G)
            'enlace_contenido'=> $row['url_destino']     ?? null,  // URL Destino (col AJ)
            'estado'          => 'En elaboración',
            'creado_por'      => $user['id'],
        ];

        $key = $fecha . '|' . trim($codigoPieza);

        if (isset($existMap[$key])) {
            // ── EXISTE: verificar si hay cambios ──────────────────────
            $existente = $existMap[$key];
            $existId   = $existente['id'];

            // Detectar cambios: fecha, pestaña o pilar
            $hasChanges = false;
            if (($existente['fecha']      ?? '') !== $fecha)    $hasChanges = true;
            if (($existente['pilar']      ?? '') !== ($row['tipo_pieza'] ?? '')) $hasChanges = true;
            if (($existente['pestana_id'] ?? 0)  !== $pestanaId) $hasChanges = true;

            // Comprobar si los slides cambiaron (comparar texto de slide 1)
            $existSlides = sb_get('contenido_slides', 'contenido_id=eq.' . $existId . '&order=orden.asc');
            $existSlide1 = $existSlides['data'][0]['texto'] ?? '';
            $newSlide1   = $slides[0]['texto'] ?? '';
            if (!empty($slides) && $existSlide1 !== $newSlide1) $hasChanges = true;

            if ($hasChanges) {
                $updateBody = $body;
                unset($updateBody['creado_por']);
                $updateBody['actualizado_por'] = $user['id'];
                sb_patch('contenidos', 'id=eq.' . $existId, $updateBody);

                // Actualizar slides
                if (!empty($slides)) {
                    sb_delete('contenido_slides', 'contenido_id=eq.' . $existId);
                    foreach ($slides as $si => $slide) {
                        sb_post('contenido_slides', [
                            'contenido_id' => $existId,
                            'orden'        => $si + 1,
                            'texto'        => $slide['texto'] ?? '',
                            'notas'        => $slide['notas'] ?? null,
                        ]);
                    }
                }

                // Actualizar detalle
                sb_delete('contenido_detalle', 'contenido_id=eq.' . $existId);
                $detBody = buildDetalle($existId, $row, $tipoDistribucion);
                if ($detBody) sb_post('contenido_detalle', $detBody);

                $results['updated']++;
            } else {
                $results['unchanged']++;
            }

        } else {
            // ── NO EXISTE: crear nuevo ────────────────────────────────
            $cRes = sb_post('contenidos', $body);
            $cid  = $cRes['data'][0]['id'] ?? null;

            if (!$cid) {
                $results['errors'][] = "Fila #{$idx} ({$codigoPieza}): Error al insertar. " . json_encode($cRes);
                continue;
            }

            // Slides
            foreach ($slides as $si => $slide) {
                sb_post('contenido_slides', [
                    'contenido_id' => $cid,
                    'orden'        => $si + 1,
                    'texto'        => $slide['texto'] ?? '',
                    'notas'        => $slide['notas'] ?? null,
                ]);
            }

            // Detalle
            $detBody = buildDetalle($cid, $row, $tipoDistribucion);
            if ($detBody) sb_post('contenido_detalle', $detBody);

            // Historial
            sb_post('historial_estado', [
                'contenido_id' => $cid,
                'estado_nuevo' => 'En elaboración',
                'usuario_id'   => $user['id'],
                'comentario'   => 'Importado desde Planner 2.0 XLS',
            ]);

            $results['created']++;
        }

    } catch (Exception $ex) {
        $results['errors'][] = "Fila #{$idx}: " . $ex->getMessage();
    }
}

jsonResponse(['success' => true, 'results' => $results]);

// ─── Helper: construir array de contenido_detalle ─────────────────────
function buildDetalle($cid, $row, $tipoDistribucion) {
    $detBody = [];

    if (!empty($row['headline'])) {
        $detBody[] = ['contenido_id' => $cid, 'campo' => 'titulo_post',     'valor' => $row['headline']];
    }
    if (!empty($row['cta'])) {
        $detBody[] = ['contenido_id' => $cid, 'campo' => 'cta',             'valor' => $row['cta']];
    }
    if (!empty($row['copy_completo'])) {
        // copy raw completo por si se necesita (copy_facebook como respaldo)
        $detBody[] = ['contenido_id' => $cid, 'campo' => 'copy_facebook',   'valor' => $row['copy_completo']];
        $detBody[] = ['contenido_id' => $cid, 'campo' => 'copy_instagram',  'valor' => $row['copy_completo']];
    }
    if ($tipoDistribucion) {
        $detBody[] = ['contenido_id' => $cid, 'campo' => 'tipo_distribucion', 'valor' => $tipoDistribucion];
    }
    if (!empty($row['creative_notes'])) {
        $detBody[] = ['contenido_id' => $cid, 'campo' => 'creative_notes',  'valor' => $row['creative_notes']];
    }

    return $detBody;
}
