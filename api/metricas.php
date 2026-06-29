<?php
/**
 * API: Métricas de contenido
 */
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'get';
$db = getDB();

switch ($action) {
    case 'get':
        $contenido_id = intval($_GET['contenido_id'] ?? 0);
        if (!$contenido_id) jsonResponse(['error' => 'ID contenido requerido'], 400);
        
        $stmt = $db->prepare("SELECT m.*, u.nombre as registrado_nombre FROM metricas m 
                              LEFT JOIN usuarios u ON m.registrado_por = u.id 
                              WHERE m.contenido_id = ? ORDER BY m.fecha_registro DESC");
        $stmt->execute([$contenido_id]);
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'save':
        if ($method !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);
        
        $perms = getPermissions($user['rol']);
        if (!$perms['registrar_metricas']) jsonResponse(['error' => 'No autorizado'], 403);
        
        $input = getJsonInput();
        $contenido_id = intval($input['contenido_id'] ?? 0);
        if (!$contenido_id) jsonResponse(['error' => 'ID contenido requerido'], 400);

        $today = date('Y-m-d');
        $check = $db->prepare("SELECT id FROM metricas WHERE contenido_id = ? AND fecha_registro = ?");
        $check->execute([$contenido_id, $today]);
        $existing = $check->fetch();

        $fields = ['espectadores','likes','comentarios','compartidos','guardados','alcance','clics'];

        if ($existing) {
            $updates = [];
            $params = [];
            foreach ($fields as $f) {
                if (isset($input[$f])) {
                    $updates[] = "$f = ?";
                    $params[] = intval($input[$f]);
                }
            }
            if ($updates) {
                $params[] = $existing['id'];
                $db->prepare("UPDATE metricas SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
            }
        } else {
            $stmt = $db->prepare("INSERT INTO metricas (contenido_id, espectadores, likes, comentarios, compartidos, guardados, alcance, clics, registrado_por, fecha_registro) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $contenido_id,
                intval($input['espectadores'] ?? 0),
                intval($input['likes'] ?? 0),
                intval($input['comentarios'] ?? 0),
                intval($input['compartidos'] ?? 0),
                intval($input['guardados'] ?? 0),
                intval($input['alcance'] ?? 0),
                intval($input['clics'] ?? 0),
                $user['id'],
                $today
            ]);
        }

        jsonResponse(['success' => true]);
        break;

    case 'report':
        $pestana = $_GET['pestana'] ?? '';
        $semana_start = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
        $semana_end = $_GET['to'] ?? date('Y-m-d');

        $sql = "SELECT c.id, c.tema, c.red_social, c.estado, c.fecha,
                       p.nombre as pestana, p.color as pestana_color,
                       m.espectadores, m.likes, m.comentarios, m.compartidos, m.guardados, m.alcance, m.clics,
                       m.fecha_registro
                FROM contenidos c
                JOIN pestanas p ON c.pestana_id = p.id
                LEFT JOIN metricas m ON c.id = m.contenido_id
                WHERE c.estado = 'Publicado'
                AND c.fecha BETWEEN ? AND ?";
        $params = [$semana_start, $semana_end];
        
        if ($pestana) {
            $sql .= " AND p.slug = ?";
            $params[] = $pestana;
        }
        $sql .= " ORDER BY c.fecha ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        $totals = ['espectadores' => 0, 'likes' => 0, 'comentarios' => 0, 'compartidos' => 0, 'guardados' => 0, 'alcance' => 0, 'clics' => 0];
        foreach ($data as $row) {
            foreach ($totals as $k => &$v) {
                $v += intval($row[$k] ?? 0);
            }
        }

        jsonResponse(['data' => $data, 'totals' => $totals, 'period' => ['from' => $semana_start, 'to' => $semana_end]]);
        break;

    case 'analytics':
        // Migration: ensure tipo column exists before querying
        try { $db->exec("ALTER TABLE contenido_imagenes ADD COLUMN tipo VARCHAR(50) DEFAULT NULL"); } catch(Exception $e) {}

        $fecha_desde    = $_GET['desde']      ?? date('Y-m-01');
        $fecha_hasta    = $_GET['hasta']      ?? date('Y-m-d');
        $filtro_pestana = $_GET['pestana']    ?? '';
        $filtro_red     = $_GET['red_social'] ?? '';
        $filtro_formato = $_GET['formato']    ?? '';

        $baseWhere  = "c.estado = 'Publicado' AND c.fecha BETWEEN :desde AND :hasta";
        $baseParams = [':desde' => $fecha_desde, ':hasta' => $fecha_hasta];

        if ($filtro_pestana) { $baseWhere .= " AND p.slug = :pestana"; $baseParams[':pestana'] = $filtro_pestana; }
        if ($filtro_red)     { $baseWhere .= " AND c.red_social = :red"; $baseParams[':red'] = $filtro_red; }
        if ($filtro_formato) { $baseWhere .= " AND c.formato = :formato"; $baseParams[':formato'] = $filtro_formato; }

        // Totals KPIs — use latest metric per content to avoid daily double-counting
        $s = $db->prepare("SELECT COUNT(DISTINCT c.id) as total_publicaciones,
            COALESCE(SUM(m.likes),0) as total_likes,
            COALESCE(SUM(m.comentarios),0) as total_comentarios,
            COALESCE(SUM(m.compartidos),0) as total_compartidos,
            COALESCE(SUM(m.guardados),0) as total_guardados,
            COALESCE(SUM(m.alcance),0) as total_alcance,
            COALESCE(SUM(m.clics),0) as total_clics,
            COALESCE(SUM(m.espectadores),0) as total_espectadores
            FROM contenidos c JOIN pestanas p ON c.pestana_id = p.id
            LEFT JOIN metricas m ON m.id = (
                SELECT id FROM metricas WHERE contenido_id = c.id ORDER BY fecha_registro DESC LIMIT 1
            ) WHERE $baseWhere");
        $s->execute($baseParams);
        $totals = $s->fetch();

        // By Red Social — latest metric per content
        $s = $db->prepare("SELECT c.red_social,
            COUNT(DISTINCT c.id) as publicaciones,
            COALESCE(SUM(m.likes),0) as likes,
            COALESCE(SUM(m.comentarios),0) as comentarios,
            COALESCE(SUM(m.compartidos),0) as compartidos,
            COALESCE(SUM(m.alcance),0) as alcance,
            COALESCE(SUM(m.clics),0) as clics
            FROM contenidos c JOIN pestanas p ON c.pestana_id = p.id
            LEFT JOIN metricas m ON m.id = (
                SELECT id FROM metricas WHERE contenido_id = c.id ORDER BY fecha_registro DESC LIMIT 1
            )
            WHERE $baseWhere GROUP BY c.red_social ORDER BY likes DESC");
        $s->execute($baseParams);
        $byRed = $s->fetchAll();

        // By Formato — latest metric per content
        $s = $db->prepare("SELECT c.formato,
            COUNT(DISTINCT c.id) as publicaciones,
            COALESCE(SUM(m.likes),0) as likes,
            COALESCE(SUM(m.alcance),0) as alcance
            FROM contenidos c JOIN pestanas p ON c.pestana_id = p.id
            LEFT JOIN metricas m ON m.id = (
                SELECT id FROM metricas WHERE contenido_id = c.id ORDER BY fecha_registro DESC LIMIT 1
            )
            WHERE $baseWhere GROUP BY c.formato ORDER BY publicaciones DESC");
        $s->execute($baseParams);
        $byFormato = $s->fetchAll();

        // By Pestana — latest metric per content
        $s = $db->prepare("SELECT p.nombre as pestana, p.color,
            COUNT(DISTINCT c.id) as publicaciones,
            COALESCE(SUM(m.likes),0) as likes,
            COALESCE(SUM(m.comentarios),0) as comentarios,
            COALESCE(SUM(m.alcance),0) as alcance
            FROM contenidos c JOIN pestanas p ON c.pestana_id = p.id
            LEFT JOIN metricas m ON m.id = (
                SELECT id FROM metricas WHERE contenido_id = c.id ORDER BY fecha_registro DESC LIMIT 1
            )
            WHERE $baseWhere GROUP BY p.id, p.nombre, p.color ORDER BY likes DESC");
        $s->execute($baseParams);
        $byPestana = $s->fetchAll();

        // Time Series daily — latest metric per content per date group
        $s = $db->prepare("SELECT c.fecha,
            COALESCE(SUM(m.likes),0) as likes,
            COALESCE(SUM(m.alcance),0) as alcance,
            COALESCE(SUM(m.comentarios),0) as comentarios,
            COALESCE(SUM(m.compartidos),0) as compartidos,
            COUNT(DISTINCT c.id) as publicaciones
            FROM contenidos c JOIN pestanas p ON c.pestana_id = p.id
            LEFT JOIN metricas m ON m.id = (
                SELECT id FROM metricas WHERE contenido_id = c.id ORDER BY fecha_registro DESC LIMIT 1
            )
            WHERE $baseWhere GROUP BY c.fecha ORDER BY c.fecha ASC");
        $s->execute($baseParams);
        $timeSeries = $s->fetchAll();

        // Top 10 by likes — latest metric per content
        $s = $db->prepare("SELECT c.id, c.tema, c.red_social, c.formato, c.fecha,
            p.nombre as pestana, p.color as pestana_color,
            ci.filename as captura_filename,
            COALESCE(m.likes,0) as likes,
            COALESCE(m.comentarios,0) as comentarios,
            COALESCE(m.compartidos,0) as compartidos,
            COALESCE(m.guardados,0) as guardados,
            COALESCE(m.alcance,0) as alcance,
            COALESCE(m.clics,0) as clics,
            COALESCE(m.espectadores,0) as espectadores,
            c.enlace_publicado
            FROM contenidos c JOIN pestanas p ON c.pestana_id = p.id
            LEFT JOIN metricas m ON m.id = (
                SELECT id FROM metricas WHERE contenido_id = c.id ORDER BY fecha_registro DESC LIMIT 1
            )
            LEFT JOIN contenido_imagenes ci ON c.id = ci.contenido_id AND ci.tipo = 'captura_post'
            WHERE $baseWhere ORDER BY likes DESC LIMIT 10");
        $s->execute($baseParams);
        $topContent = $s->fetchAll();

        $baseUrl = getBaseUrl();
        foreach ($topContent as &$row) {
            $row['captura_url'] = $row['captura_filename'] ? $baseUrl . 'uploads/' . $row['captura_filename'] : null;
        }

        jsonResponse([
            'period'      => ['desde' => $fecha_desde, 'hasta' => $fecha_hasta],
            'totals'      => $totals,
            'by_red'      => $byRed,
            'by_formato'  => $byFormato,
            'by_pestana'  => $byPestana,
            'time_series' => $timeSeries,
            'top_content' => $topContent,
        ]);
        break;

    case 'save_captura':
        if ($method !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);
        $perms = getPermissions($user['rol']);
        if (!$perms['registrar_metricas']) jsonResponse(['error' => 'No autorizado'], 403);

        $input = json_decode(file_get_contents('php://input'), true);
        $contenido_id = intval($input['contenido_id'] ?? 0);
        $image_data   = $input['image_data'] ?? '';

        if (!$contenido_id) jsonResponse(['error' => 'ID requerido'], 400);
        if (!$image_data)   jsonResponse(['error' => 'Imagen requerida'], 400);

        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        if (!preg_match('/^data:image\/(\w+);base64,/', $image_data, $matches)) {
            jsonResponse(['error' => 'Formato de imagen invalido'], 400);
        }
        $ext  = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $data = base64_decode(substr($image_data, strpos($image_data, ',') + 1));
        $filename = 'captura_' . $contenido_id . '_' . time() . '.' . $ext;
        file_put_contents($uploadDir . $filename, $data);

        // Migration: add tipo column if missing
        try { $db->exec("ALTER TABLE contenido_imagenes ADD COLUMN tipo VARCHAR(50) DEFAULT NULL"); } catch(Exception $e) {}

        // Remove previous captura
        $prev = $db->prepare("SELECT filename FROM contenido_imagenes WHERE contenido_id = ? AND tipo = 'captura_post'");
        $prev->execute([$contenido_id]);
        if ($pRow = $prev->fetch()) {
            @unlink($uploadDir . $pRow['filename']);
            $db->prepare("DELETE FROM contenido_imagenes WHERE contenido_id = ? AND tipo = 'captura_post'")->execute([$contenido_id]);
        }

        $ins = $db->prepare("INSERT INTO contenido_imagenes (contenido_id, filename, tipo, subido_por) VALUES (?, ?, 'captura_post', ?)");
        $ins->execute([$contenido_id, $filename, $user['id']]);

        jsonResponse(['success' => true, 'url' => getBaseUrl() . 'uploads/' . $filename]);
        break;

    case 'get_captura':
        $contenido_id = intval($_GET['contenido_id'] ?? 0);
        if (!$contenido_id) jsonResponse(['url' => null]);
        try {
            $stmt = $db->prepare("SELECT filename FROM contenido_imagenes WHERE contenido_id = ? AND tipo = 'captura_post' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$contenido_id]);
            $row = $stmt->fetch();
            jsonResponse(['url' => $row ? getBaseUrl() . 'uploads/' . $row['filename'] : null]);
        } catch(Exception $e) {
            jsonResponse(['url' => null]);
        }
        break;

    case 'delete_captura':
        if ($method !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);
        $perms = getPermissions($user['rol']);
        if (!$perms['registrar_metricas']) jsonResponse(['error' => 'No autorizado'], 403);

        $input = json_decode(file_get_contents('php://input'), true);
        $contenido_id = intval($input['contenido_id'] ?? 0);
        if (!$contenido_id) jsonResponse(['error' => 'ID requerido'], 400);

        $uploadDir = __DIR__ . '/../uploads/';
        $prev = $db->prepare("SELECT filename FROM contenido_imagenes WHERE contenido_id = ? AND tipo = 'captura_post'");
        $prev->execute([$contenido_id]);
        while ($pRow = $prev->fetch()) {
            @unlink($uploadDir . $pRow['filename']);
        }
        $db->prepare("DELETE FROM contenido_imagenes WHERE contenido_id = ? AND tipo = 'captura_post'")->execute([$contenido_id]);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Accion no valida'], 400);
}

