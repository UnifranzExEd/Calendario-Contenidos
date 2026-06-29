<?php
/**
 * API: Exportar a CSV
 */
require_once __DIR__ . '/../config/auth.php';

$user = requireAuth();
$perms = getPermissions($user['rol']);
if (!$perms['exportar']) {
    header('Content-Type: application/json');
    jsonResponse(['error' => 'No autorizado'], 403);
}

$db = getDB();
$pestana = $_GET['pestana'] ?? '';
$mes = $_GET['mes'] ?? '';
$anio = $_GET['anio'] ?? date('Y');

$where = "1=1";
$params = [];

if ($pestana) { $where .= " AND p.slug = ?"; $params[] = $pestana; }
if ($mes) { $where .= " AND c.mes = ?"; $params[] = $mes; }
if ($anio) { $where .= " AND c.anio = ?"; $params[] = $anio; }

$sql = "SELECT c.fecha, c.buyer, c.pilar, c.atributo, c.etapa, c.aspecto, c.carrera,
               c.tema, c.idea, c.red_social, c.estado, c.formato, c.horario,
               c.enlace_contenido, c.enlace_publicado, c.observaciones,
               c.espectadores, c.interacciones, c.semana, c.mes,
               p.nombre as pestana, pp.nombre as postproductor,
               m.likes, m.comentarios, m.compartidos, m.guardados, m.alcance, m.clics
        FROM contenidos c
        JOIN pestanas p ON c.pestana_id = p.id
        LEFT JOIN usuarios pp ON c.postproductor_id = pp.id
        LEFT JOIN metricas m ON c.id = m.contenido_id
        WHERE $where
        ORDER BY c.fecha ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Output CSV
$filename = 'unifranz_contenidos_' . ($pestana ?: 'todos') . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Headers
$headers = ['Fecha','Pestaña','Buyer','Pilar','Atributo','Etapa','Aspecto','Carrera',
            'Tema','Idea','Red Social','Estado','Formato','Horario',
            'Enlace Contenido','Enlace Publicado','Observaciones','Semana','Mes',
            'Post-Productor','Espectadores','Likes','Comentarios','Compartidos','Guardados','Alcance','Clics'];
fputcsv($output, $headers);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['fecha'], $row['pestana'], $row['buyer'], $row['pilar'], $row['atributo'],
        $row['etapa'], $row['aspecto'], $row['carrera'], $row['tema'], $row['idea'],
        $row['red_social'], $row['estado'], $row['formato'], $row['horario'],
        $row['enlace_contenido'], $row['enlace_publicado'], $row['observaciones'],
        $row['semana'], $row['mes'], $row['postproductor'],
        $row['espectadores'], $row['likes'], $row['comentarios'],
        $row['compartidos'], $row['guardados'], $row['alcance'], $row['clics']
    ]);
}

fclose($output);
exit;
