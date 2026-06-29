<?php
/**
 * API: Contenidos (CRUD principal)
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDB();

// Auto-migration for enlace_diseno and deleted_at
try { $db->exec("ALTER TABLE contenidos ADD COLUMN enlace_diseno TEXT DEFAULT NULL"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE contenidos ADD COLUMN deleted_at DATETIME DEFAULT NULL"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE contenidos ADD COLUMN error_ortografico_detalle TEXT DEFAULT NULL"); } catch(Exception $e) {}
try {
    $pestanas = $db->query("SELECT id FROM pestanas")->fetchAll(PDO::FETCH_COLUMN);
    $stmtM = $db->prepare("SELECT COUNT(*) FROM pestana_campos WHERE pestana_id = ? AND nombre_campo = 'enlace_diseno'");
    $insertM = $db->prepare("INSERT INTO pestana_campos (pestana_id, nombre_campo, nombre_display, tipo_campo, orden, visible, ancho) VALUES (?, 'enlace_diseno', 'DISEÑO FINAL (DRIVE)', 'url', 99, 1, '180px')");
    foreach ($pestanas as $pId) {
        $stmtM->execute([$pId]);
        if ($stmtM->fetchColumn() == 0) {
            $insertM->execute([$pId]);
        }
    }
} catch(Exception $e) {}


switch ($action) {

    // ── LIST CONTENTS ──
    case 'list':
        $pestana = $_GET['pestana'] ?? '';
        $mes = $_GET['mes'] ?? '';
        $anio = $_GET['anio'] ?? date('Y');
        $estado = $_GET['estado'] ?? '';
        $red_social = $_GET['red_social'] ?? '';
        $buyer = $_GET['buyer'] ?? '';
        $pilar = $_GET['pilar'] ?? '';
        $search = $_GET['search'] ?? '';

        $where = ["c.pestana_id = p.id", "c.deleted_at IS NULL"];
        $params = [];

        if ($pestana) {
            $where[] = "p.slug = ?";
            $params[] = $pestana;
        }
        if ($mes) {
            $where[] = "c.mes = ?";
            $params[] = $mes;
        }
        if ($anio) {
            $where[] = "c.anio = ?";
            $params[] = $anio;
        }
        if ($estado) {
            $where[] = "c.estado = ?";
            $params[] = $estado;
        }
        if ($red_social) {
            $where[] = "c.red_social = ?";
            $params[] = $red_social;
        }
        if ($buyer) {
            $where[] = "c.buyer = ?";
            $params[] = $buyer;
        }
        if ($pilar) {
            $where[] = "c.pilar = ?";
            $params[] = $pilar;
        }
        if ($search) {
            $where[] = "(c.tema LIKE ? OR c.idea LIKE ? OR c.observaciones LIKE ?)";
            $s = "%$search%";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereStr = implode(' AND ', $where);

        $sql = "SELECT c.*, p.slug as pestana_slug, p.nombre as pestana_nombre, p.color as pestana_color,
                       u.nombre as creador_nombre, pp.nombre as postproductor_nombre,
                       cd.titulo_post, cd.copy_facebook, cd.copy_instagram, cd.copy_tiktok
                FROM contenidos c
                JOIN pestanas p ON c.pestana_id = p.id
                LEFT JOIN usuarios u ON c.creado_por = u.id
                LEFT JOIN usuarios pp ON c.postproductor_id = pp.id
                LEFT JOIN contenido_detalle cd ON c.id = cd.contenido_id
                WHERE $whereStr
                ORDER BY c.fecha IS NULL, c.fecha ASC, c.id ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $contenidos = $stmt->fetchAll();

        jsonResponse(['data' => $contenidos, 'total' => count($contenidos)]);
        break;

    // ── GET SINGLE ──
    case 'get':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        $stmt = $db->prepare("SELECT c.*, p.slug as pestana_slug, p.nombre as pestana_nombre,
                                     u.nombre as creador_nombre, pp.nombre as postproductor_nombre
                              FROM contenidos c
                              JOIN pestanas p ON c.pestana_id = p.id
                              LEFT JOIN usuarios u ON c.creado_por = u.id
                              LEFT JOIN usuarios pp ON c.postproductor_id = pp.id
                              WHERE c.id = ?");
        $stmt->execute([$id]);
        $contenido = $stmt->fetch();

        if (!$contenido) jsonResponse(['error' => 'Contenido no encontrado'], 404);

        // Get detail
        $stmt2 = $db->prepare("SELECT * FROM contenido_detalle WHERE contenido_id = ?");
        $stmt2->execute([$id]);
        $contenido['detalle'] = $stmt2->fetch() ?: null;

        // Get slides
        $stmt3 = $db->prepare("SELECT * FROM contenido_slides WHERE contenido_id = ? ORDER BY numero_slide ASC");
        $stmt3->execute([$id]);
        $contenido['slides'] = $stmt3->fetchAll();

        // Get hashtags
        $stmt4 = $db->prepare("SELECT h.* FROM hashtags h JOIN contenido_hashtags ch ON h.id = ch.hashtag_id WHERE ch.contenido_id = ?");
        $stmt4->execute([$id]);
        $contenido['hashtags'] = $stmt4->fetchAll();

        // Get metrics
        $stmt5 = $db->prepare("SELECT * FROM metricas WHERE contenido_id = ? ORDER BY fecha_registro DESC LIMIT 1");
        $stmt5->execute([$id]);
        $contenido['metricas'] = $stmt5->fetch() ?: null;

        // Get history
        $stmt6 = $db->prepare("SELECT h.*, u.nombre as usuario_nombre FROM historial_estado h 
                               LEFT JOIN usuarios u ON h.usuario_id = u.id 
                               WHERE h.contenido_id = ? ORDER BY h.created_at DESC LIMIT 20");
        $stmt6->execute([$id]);
        $contenido['historial'] = $stmt6->fetchAll();

        // Get captura
        $stmt7 = $db->prepare("SELECT filename FROM contenido_imagenes WHERE contenido_id = ? AND tipo = 'captura_post' ORDER BY id DESC LIMIT 1");
        $stmt7->execute([$id]);
        $contenido['captura'] = $stmt7->fetchColumn() ?: null;

        jsonResponse(['data' => $contenido]);
        break;

    // ── CREATE ──
    case 'create':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        
        $perms = getPermissions($user['rol']);
        if (!$perms['crear_contenido']) {
            jsonResponse(['error' => 'No tienes permisos para crear contenido'], 403);
        }

        $input = getJsonInput();
        
        $pestana_slug = $input['pestana'] ?? '';
        $stmt = $db->prepare("SELECT id FROM pestanas WHERE slug = ?");
        $stmt->execute([$pestana_slug]);
        $pestana = $stmt->fetch();
        if (!$pestana) jsonResponse(['error' => 'Pestaña no válida'], 400);

        // Extract month from date
        $fecha = $input['fecha'] ?? null;
        $mes = null;
        if ($fecha) {
            $meses = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
            $monthNum = intval(date('n', strtotime($fecha)));
            $mes = $meses[$monthNum - 1] ?? null;
        }

        $stmt = $db->prepare("INSERT INTO contenidos 
            (pestana_id, semana, mes, anio, fecha, buyer, pilar, atributo, etapa, aspecto, carrera, 
             tema, idea, red_social, estado, error_ortografico, error_ortografico_detalle, formato, horario, enlace_contenido, enlace_publicado, enlace_diseno, 
             observaciones, enviar_postproduccion, creado_por) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $pestana['id'],
            $input['semana'] ?? null,
            $mes,
            $input['anio'] ?? date('Y'),
            $fecha,
            $input['buyer'] ?? null,
            $input['pilar'] ?? null,
            $input['atributo'] ?? null,
            $input['etapa'] ?? null,
            $input['aspecto'] ?? null,
            $input['carrera'] ?? null,
            $input['tema'] ?? null,
            $input['idea'] ?? null,
            $input['red_social'] ?? null,
            $input['estado'] ?? 'En elaboración',
            isset($input['error_ortografico']) ? ($input['error_ortografico'] ? 1 : 0) : 0,
            $input['error_ortografico_detalle'] ?? null,
            $input['formato'] ?? null,
            $input['horario'] ?? null,
            $input['enlace_contenido'] ?? null,
            $input['enlace_publicado'] ?? null,
            $input['enlace_diseno'] ?? null,
            $input['observaciones'] ?? null,
            isset($input['enviar_postproduccion']) ? ($input['enviar_postproduccion'] ? 1 : 0) : 0,
            $user['id']
        ]);

        $contenidoId = $db->lastInsertId();

        // Create detail
        if (isset($input['titulo_post']) || isset($input['copy_facebook'])) {
            $stmt = $db->prepare("INSERT INTO contenido_detalle (contenido_id, titulo_post, copy_facebook, copy_instagram, copy_tiktok) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $contenidoId,
                $input['titulo_post'] ?? null,
                $input['copy_facebook'] ?? null,
                $input['copy_instagram'] ?? null,
                $input['copy_tiktok'] ?? null,
            ]);
        }

        // Create slides
        if (!empty($input['slides'])) {
            $stmtSlide = $db->prepare("INSERT INTO contenido_slides (contenido_id, numero_slide, texto, notas) VALUES (?, ?, ?, ?)");
            foreach ($input['slides'] as $i => $slide) {
                $stmtSlide->execute([$contenidoId, $i + 1, $slide['texto'] ?? '', $slide['notas'] ?? null]);
            }
        }

        // Log history
        $stmtH = $db->prepare("INSERT INTO historial_estado (contenido_id, estado_nuevo, usuario_id, comentario) VALUES (?, ?, ?, ?)");
        $stmtH->execute([$contenidoId, $input['estado'] ?? 'En elaboración', $user['id'], 'Contenido creado']);

        // Notify postproductors about new content
        notificarRol('postproductor', 'nuevo_contenido', 
            $user['nombre'] . ' creó nuevo contenido: ' . ($input['tema'] ?? 'Sin tema'), 
            $contenidoId);

        jsonResponse(['success' => true, 'id' => $contenidoId], 201);
        break;

    // ── UPDATE ──
    case 'update':
        if ($method !== 'POST' && $method !== 'PUT') jsonResponse(['error' => 'Método no permitido'], 405);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        // Check permissions
        $perms = getPermissions($user['rol']);
        
        // Get current content
        $stmt = $db->prepare("SELECT * FROM contenidos WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch();
        if (!$current) jsonResponse(['error' => 'Contenido no encontrado'], 404);

        // Permission checks
        if (!$perms['editar_cualquier'] && !$perms['editar_contenido']) {
            // PostProductor can only update link and status
            if ($user['rol'] === 'postproductor') {
                $allowedFields = ['estado', 'enlace_contenido', 'enlace_diseno', 'postproductor_id'];
                $input = array_intersect_key($input, array_flip($allowedFields));
                $input['id'] = $id;
            } else {
                jsonResponse(['error' => 'No tienes permisos para editar'], 403);
            }
        }
        
        if (!$perms['editar_cualquier'] && $perms['editar_contenido'] && $current['creado_por'] != $user['id']) {
            // Community can only edit own content
            if ($user['rol'] === 'community') {
                // Allow but check
            }
        }

        // Build update query
        $fields = ['buyer','pilar','atributo','etapa','aspecto','carrera','tema','idea',
                    'red_social','estado','error_ortografico','error_ortografico_detalle','formato','horario','enlace_contenido','enlace_publicado','enlace_diseno',
                    'observaciones','semana','fecha','espectadores','interacciones','postproductor_id', 'enviar_postproduccion'];
        
        $updates = [];
        $params = [];
        
        foreach ($fields as $f) {
            if (array_key_exists($f, $input)) {
                $updates[] = "$f = ?";
                $params[] = $input[$f];
            }
        }

        // Update month from date if changed
        if (isset($input['fecha']) && $input['fecha']) {
            $meses = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
            $monthNum = intval(date('n', strtotime($input['fecha'])));
            $updates[] = "mes = ?";
            $params[] = $meses[$monthNum - 1] ?? null;
            $updates[] = "anio = ?";
            $params[] = intval(date('Y', strtotime($input['fecha'])));
        }

        $updates[] = "actualizado_por = ?";
        $params[] = $user['id'];
        $params[] = $id;

        if (!empty($updates)) {
            $sql = "UPDATE contenidos SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }

        // Log state change
        if (isset($input['estado']) && $input['estado'] !== $current['estado']) {
            $stmtH = $db->prepare("INSERT INTO historial_estado (contenido_id, estado_anterior, estado_nuevo, usuario_id) VALUES (?, ?, ?, ?)");
            $stmtH->execute([$id, $current['estado'], $input['estado'], $user['id']]);

            // Notifications on state change
            if ($input['estado'] === 'En revisión') {
                notificarRol('admin', 'estado_cambio', 
                    'Contenido "' . ($current['tema'] ?? '#'.$id) . '" enviado a revisión por ' . $user['nombre'], $id);
            }
            if ($input['estado'] === 'Diseñado') {
                notificarRol('postproductor', 'estado_cambio', 
                    'Contenido "' . ($current['tema'] ?? '#'.$id) . '" listo para diseño / producción', $id);
            }
            if ($input['estado'] === 'Aprobado') {
                if ($current['creado_por']) {
                    crearNotificacion($current['creado_por'], 'estado_cambio', 
                        'Tu contenido "' . ($current['tema'] ?? '#'.$id) . '" fue aprobado', $id);
                }
            }
            if ($input['estado'] === 'Publicado') {
                notificarRol('admin', 'estado_cambio', 
                    'Contenido "' . ($current['tema'] ?? '#'.$id) . '" publicado', $id);
            }
        }

        // Update detail
        if (isset($input['titulo_post']) || isset($input['copy_facebook']) || isset($input['copy_instagram']) || isset($input['copy_tiktok'])) {
            $detExists = $db->prepare("SELECT id FROM contenido_detalle WHERE contenido_id = ?");
            $detExists->execute([$id]);
            
            if ($detExists->fetch()) {
                $detUpdates = [];
                $detParams = [];
                foreach (['titulo_post','copy_facebook','copy_instagram','copy_tiktok'] as $df) {
                    if (array_key_exists($df, $input)) {
                        $detUpdates[] = "$df = ?";
                        $detParams[] = $input[$df];
                    }
                }
                if ($detUpdates) {
                    $detParams[] = $id;
                    $db->prepare("UPDATE contenido_detalle SET " . implode(', ', $detUpdates) . " WHERE contenido_id = ?")->execute($detParams);
                }
            } else {
                $db->prepare("INSERT INTO contenido_detalle (contenido_id, titulo_post, copy_facebook, copy_instagram, copy_tiktok) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$id, $input['titulo_post'] ?? null, $input['copy_facebook'] ?? null, $input['copy_instagram'] ?? null, $input['copy_tiktok'] ?? null]);
            }
        }

        // Update slides
        if (isset($input['slides'])) {
            $db->prepare("DELETE FROM contenido_slides WHERE contenido_id = ?")->execute([$id]);
            $stmtSlide = $db->prepare("INSERT INTO contenido_slides (contenido_id, numero_slide, texto, notas) VALUES (?, ?, ?, ?)");
            foreach ($input['slides'] as $i => $slide) {
                $stmtSlide->execute([$id, $i + 1, $slide['texto'] ?? '', $slide['notas'] ?? null]);
            }
        }

        jsonResponse(['success' => true]);
        break;

    // ── PURGE (hard delete, admin only) ──
    case 'purge':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);

        if ($user['rol'] !== 'admin') {
            jsonResponse(['error' => 'Solo el administrador puede eliminar contenidos permanentemente'], 403);
        }

        $input = getJsonInput();
        $id = intval($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        $db->prepare("DELETE FROM contenidos WHERE id = ?")->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    case 'duplicate':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        $stmt = $db->prepare("SELECT * FROM contenidos WHERE id = ?");
        $stmt->execute([$id]);
        $orig = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orig) jsonResponse(['error' => 'No encontrado'], 404);

        unset($orig['id']);
        $orig['creado_por'] = $user['id'];
        $orig['estado'] = 'En elaboración';
        $orig['postproductor_id'] = null;
        $orig['enlace_contenido'] = null;
        $orig['enlace_publicado'] = null;
        $orig['enlace_diseno'] = null;

        $keys = array_keys($orig);
        $fields = implode(", ", $keys);
        $placeholders = implode(", ", array_fill(0, count($orig), "?"));

        $insert = $db->prepare("INSERT INTO contenidos ($fields) VALUES ($placeholders)");
        $insert->execute(array_values($orig));
        $newId = $db->lastInsertId();

        $stmtD = $db->prepare("SELECT * FROM contenido_detalle WHERE contenido_id = ?");
        $stmtD->execute([$id]);
        $det = $stmtD->fetch(PDO::FETCH_ASSOC);
        if ($det) {
            $db->prepare("INSERT INTO contenido_detalle (contenido_id, titulo_post, copy_facebook, copy_instagram, copy_tiktok) VALUES (?, ?, ?, ?, ?)")
                ->execute([$newId, $det['titulo_post'], $det['copy_facebook'], $det['copy_instagram'], $det['copy_tiktok']]);
        }

        $stmtS = $db->prepare("SELECT * FROM contenido_slides WHERE contenido_id = ?");
        $stmtS->execute([$id]);
        $slides = $stmtS->fetchAll(PDO::FETCH_ASSOC);
        if ($slides) {
            $insertS = $db->prepare("INSERT INTO contenido_slides (contenido_id, numero_slide, texto, notas) VALUES (?, ?, ?, ?)");
            foreach ($slides as $s) {
                $insertS->execute([$newId, $s['numero_slide'], $s['texto'], $s['notas']]);
            }
        }
        
        jsonResponse(['success' => true, 'id' => $newId]);
        break;

    case 'link_pauta':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        $stmt = $db->prepare("SELECT * FROM contenidos WHERE id = ?");
        $stmt->execute([$id]);
        $orig = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orig) jsonResponse(['error' => 'No encontrado'], 404);

        $stmtP = $db->prepare("SELECT id FROM pestanas WHERE slug = 'pauta'");
        $stmtP->execute();
        $pautaId = $stmtP->fetchColumn();
        if (!$pautaId) jsonResponse(['error' => 'No existe la pestaña Pauta'], 400);

        unset($orig['id']);
        $orig['creado_por'] = $user['id'];
        $orig['estado'] = 'En elaboración';
        $orig['postproductor_id'] = null;
        $orig['enlace_contenido'] = null;
        $orig['enlace_publicado'] = null;
        $orig['enlace_diseno'] = null;
        $orig['pestana_id'] = $pautaId;

        $keys = array_keys($orig);
        $fields = implode(", ", $keys);
        $placeholders = implode(", ", array_fill(0, count($orig), "?"));

        $insert = $db->prepare("INSERT INTO contenidos ($fields) VALUES ($placeholders)");
        $insert->execute(array_values($orig));
        $newId = $db->lastInsertId();

        $stmtD = $db->prepare("SELECT * FROM contenido_detalle WHERE contenido_id = ?");
        $stmtD->execute([$id]);
        $det = $stmtD->fetch(PDO::FETCH_ASSOC);
        if ($det) {
            $db->prepare("INSERT INTO contenido_detalle (contenido_id, titulo_post, copy_facebook, copy_instagram, copy_tiktok) VALUES (?, ?, ?, ?, ?)")
                ->execute([$newId, $det['titulo_post'], $det['copy_facebook'], $det['copy_instagram'], $det['copy_tiktok']]);
        }

        $stmtS = $db->prepare("SELECT * FROM contenido_slides WHERE contenido_id = ?");
        $stmtS->execute([$id]);
        $slides = $stmtS->fetchAll(PDO::FETCH_ASSOC);
        if ($slides) {
            $insertS = $db->prepare("INSERT INTO contenido_slides (contenido_id, numero_slide, texto, notas) VALUES (?, ?, ?, ?)");
            foreach ($slides as $s) {
                $insertS->execute([$newId, $s['numero_slide'], $s['texto'], $s['notas']]);
            }
        }
        
        jsonResponse(['success' => true, 'id' => $newId]);
        break;

    case 'shift_date':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        $stmt = $db->prepare("SELECT fecha FROM contenidos WHERE id = ?");
        $stmt->execute([$id]);
        $fechaStr = $stmt->fetchColumn();
        if ($fechaStr) {
            $newFecha = date('Y-m-d', strtotime($fechaStr . ' +1 day'));
            $meses = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
            $monthNum = intval(date('n', strtotime($newFecha)));
            $mes = $meses[$monthNum - 1] ?? null;
            $anio = date('Y', strtotime($newFecha));
            
            $db->prepare("UPDATE contenidos SET fecha = ?, mes = ?, anio = ? WHERE id = ?")
               ->execute([$newFecha, $mes, $anio, $id]);
        }
        jsonResponse(['success' => true]);
        break;

    // ── INLINE UPDATE (quick edit from table) ──
    case 'inline':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        $field = $input['field'] ?? '';
        $value = $input['value'] ?? '';

        if (!$id || !$field) jsonResponse(['error' => 'ID y campo requeridos'], 400);

        $allowedFields = ['buyer','pilar','atributo','etapa','aspecto','carrera','tema','idea',
                         'red_social','estado','error_ortografico','error_ortografico_detalle','formato','horario','enlace_contenido','enlace_publicado','enlace_diseno',
                         'observaciones','fecha','espectadores','interacciones','semana','postproductor_id'];

        if (!in_array($field, $allowedFields)) {
            jsonResponse(['error' => 'Campo no permitido'], 400);
        }

        // State change checks
        if ($field === 'estado') {
            $perms = getPermissions($user['rol']);
            $allowedStates = $perms['cambiar_estado'];
            if (is_array($allowedStates) && !in_array($value, $allowedStates)) {
                jsonResponse(['error' => 'No puedes cambiar a este estado'], 403);
            }

            // Get current state for history
            $stmtCur = $db->prepare("SELECT estado, tema FROM contenidos WHERE id = ?");
            $stmtCur->execute([$id]);
            $cur = $stmtCur->fetch();
            if ($cur) {
                $db->prepare("INSERT INTO historial_estado (contenido_id, estado_anterior, estado_nuevo, usuario_id) VALUES (?, ?, ?, ?)")
                    ->execute([$id, $cur['estado'], $value, $user['id']]);
            }
        }

        // Update month if date changed
        $extra = "";
        $extraParams = [];
        if ($field === 'fecha' && $value) {
            $meses = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
            $monthNum = intval(date('n', strtotime($value)));
            $extra = ", mes = ?, anio = ?";
            $extraParams[] = $meses[$monthNum - 1] ?? null;
            $extraParams[] = intval(date('Y', strtotime($value)));
        }

        $sql = "UPDATE contenidos SET $field = ?, actualizado_por = ? $extra WHERE id = ?";
        $allParams = array_merge([$value, $user['id']], $extraParams, [$id]);
        $db->prepare($sql)->execute($allParams);

        jsonResponse(['success' => true]);
        break;

    // ── STATS ──
    case 'stats':
        $pestana = $_GET['pestana'] ?? '';
        $mes = $_GET['mes'] ?? '';
        
        $where = "1=1";
        $params = [];
        
        if ($pestana) {
            $where .= " AND p.slug = ?";
            $params[] = $pestana;
        }
        if ($mes) {
            $where .= " AND c.mes = ?";
            $params[] = $mes;
        }

        $sql = "SELECT c.estado, COUNT(*) as total FROM contenidos c JOIN pestanas p ON c.pestana_id = p.id WHERE $where GROUP BY c.estado";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetchAll();

        $total = array_sum(array_column($stats, 'total'));

        jsonResponse(['stats' => $stats, 'total' => $total]);
        break;

    // ── DELETE (SOFT) ──
    case 'delete':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $perms = getPermissions($user['rol']);
        if (!$perms['archivar'] && $user['rol'] !== 'admin') jsonResponse(['error' => 'No autorizado para archivar'], 403);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        
        $stmt = $db->prepare("UPDATE contenidos SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    // ── RESTORE (UNDO DELETE) ──
    case 'restore':
        if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
        $perms = getPermissions($user['rol']);
        if (!$perms['archivar'] && $user['rol'] !== 'admin') jsonResponse(['error' => 'No autorizado para restaurar'], 403);
        
        $input = getJsonInput();
        $id = intval($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        
        $stmt = $db->prepare("UPDATE contenidos SET deleted_at = NULL WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    case 'notifications':
        // Count items pending attention: ready to publish but not yet published
        $today = date('Y-m-d');
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM contenidos c
            JOIN pestanas p ON c.pestana_id = p.id
            WHERE c.deleted_at IS NULL
              AND c.estado IN ('Listo', 'Por publicar', 'Aprobado')
              AND (c.fecha IS NULL OR c.fecha <= ?)
        ");
        $stmt->execute([$today]);
        $count = (int)$stmt->fetchColumn();
        jsonResponse(['success' => true, 'count' => $count]);
        break;

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
