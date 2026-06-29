<?php
/**
 * Importador de datos desde Excel (datos desde Junio 2026)
 * Este script se ejecuta manualmente una sola vez
 * Los datos se insertan vía formulario con datos CSV pre-procesados
 */
require_once __DIR__ . '/../config/auth.php';

$user = requireAuth();
if ($user['rol'] !== 'admin') {
    die('Solo el administrador puede importar datos.');
}

$db = getDB();
$message = '';
$imported = 0;

// Process import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['data'])) {
    $lines = explode("\n", trim($_POST['data']));
    $pestana_slug = $_POST['pestana'] ?? 'pilares';
    
    // Get pestana ID
    $stmt = $db->prepare("SELECT id FROM pestanas WHERE slug = ?");
    $stmt->execute([$pestana_slug]);
    $pestana = $stmt->fetch();
    if (!$pestana) die('Pestaña no encontrada');
    
    $mesesMap = [
        'enero' => 'ENERO', 'febrero' => 'FEBRERO', 'marzo' => 'MARZO',
        'abril' => 'ABRIL', 'mayo' => 'MAYO', 'junio' => 'JUNIO',
        'julio' => 'JULIO', 'agosto' => 'AGOSTO', 'septiembre' => 'SEPTIEMBRE',
        'octubre' => 'OCTUBRE', 'noviembre' => 'NOVIEMBRE', 'diciembre' => 'DICIEMBRE',
    ];
    
    $stmtInsert = $db->prepare("INSERT INTO contenidos 
        (pestana_id, semana, mes, anio, fecha, buyer, pilar, atributo, etapa, aspecto, carrera, tema, idea, red_social, estado, formato, horario, enlace_contenido, enlace_publicado, observaciones, creado_por) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $currentWeek = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $cols = str_getcsv($line, "\t");
        if (count($cols) < 3) continue;
        
        // Check if it's a week separator
        if (preg_match('/^SEMANA\s+\d+\s+\w+$/i', trim($cols[0]))) {
            $currentWeek = trim($cols[0]);
            continue;
        }
        
        // Check if it's a month header
        if (preg_match('/^(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)$/i', trim($cols[0]))) {
            continue;
        }
        
        // Parse date
        $fecha = null;
        $mes = null;
        $dateStr = trim($cols[0]);
        
        if (!empty($dateStr)) {
            // Try to parse Spanish date format
            $dateStr = str_replace(
                ['lunes, ', 'martes, ', 'miércoles, ', 'jueves, ', 'viernes, ', 'sábado, ', 'domingo, '],
                '', strtolower($dateStr)
            );
            
            if (preg_match('/(\d+)\s+de\s+(\w+)/i', $dateStr, $m)) {
                $day = intval($m[1]);
                $monthName = strtolower($m[2]);
                $monthNumbers = [
                    'enero'=>1,'febrero'=>2,'marzo'=>3,'abril'=>4,'mayo'=>5,'junio'=>6,
                    'julio'=>7,'agosto'=>8,'septiembre'=>9,'octubre'=>10,'noviembre'=>11,'diciembre'=>12
                ];
                $monthNum = $monthNumbers[$monthName] ?? null;
                
                if ($monthNum) {
                    // Only import from June 2026 onwards
                    if ($monthNum < 6) continue;
                    
                    $fecha = sprintf('2026-%02d-%02d', $monthNum, $day);
                    $mes = $mesesMap[$monthName] ?? null;
                }
            }
        }
        
        if (!$fecha && !$currentWeek) continue; // Skip rows without date or week context
        
        // Map columns based on pestana
        $buyer = null; $pilar = null; $atributo = null; $etapa = null; $aspecto = null;
        $carrera = null; $tema = null; $idea = null; $red_social = null; $estado = 'Publicado';
        $formato = null; $horario = null; $enlace_contenido = null; $enlace_publicado = null;
        $observaciones = null;
        
        switch ($pestana_slug) {
            case 'pauta':
                $buyer = trim($cols[1] ?? ''); $pilar = trim($cols[2] ?? '');
                $atributo = trim($cols[3] ?? ''); $tema = trim($cols[4] ?? '');
                $red_social = trim($cols[5] ?? ''); $estado = trim($cols[6] ?? 'Publicado');
                $formato = trim($cols[9] ?? ''); $enlace_publicado = trim($cols[10] ?? '');
                $observaciones = trim($cols[11] ?? '');
                break;
            case 'pilares':
                $buyer = trim($cols[1] ?? ''); $pilar = trim($cols[2] ?? '');
                $atributo = trim($cols[3] ?? ''); $tema = trim($cols[4] ?? '');
                $red_social = trim($cols[5] ?? ''); $estado = trim($cols[6] ?? 'Publicado');
                $formato = trim($cols[7] ?? ''); $horario = trim($cols[8] ?? '');
                $enlace_publicado = trim($cols[9] ?? '');
                break;
            case 'campana':
                $etapa = trim($cols[1] ?? ''); $tema = trim($cols[2] ?? '');
                $red_social = trim($cols[3] ?? ''); $estado = trim($cols[4] ?? 'Publicado');
                $formato = trim($cols[5] ?? ''); $horario = trim($cols[6] ?? '');
                $enlace_publicado = trim($cols[7] ?? '');
                break;
            case 'carreras':
                $buyer = trim($cols[1] ?? ''); $aspecto = trim($cols[2] ?? '');
                $carrera = trim($cols[3] ?? ''); $tema = trim($cols[4] ?? '');
                $red_social = trim($cols[5] ?? ''); $estado = trim($cols[6] ?? 'Publicado');
                $enlace_contenido = trim($cols[7] ?? ''); $formato = trim($cols[8] ?? '');
                $enlace_publicado = trim($cols[9] ?? '');
                break;
            case 'salutaciones':
                $tema = trim($cols[1] ?? ''); $idea = trim($cols[2] ?? '');
                $red_social = trim($cols[3] ?? '');
                $enlace_contenido = trim($cols[4] ?? '');
                $enlace_publicado = trim($cols[5] ?? '');
                break;
            case 'inscripciones':
                $tema = trim($cols[1] ?? ''); $red_social = trim($cols[2] ?? '');
                $estado = trim($cols[3] ?? 'Publicado');
                $enlace_contenido = trim($cols[4] ?? ''); $formato = trim($cols[5] ?? '');
                $enlace_publicado = trim($cols[6] ?? '');
                break;
            case 'archivados':
                $buyer = trim($cols[1] ?? ''); $pilar = trim($cols[2] ?? '');
                $atributo = trim($cols[3] ?? ''); $tema = trim($cols[4] ?? '');
                $red_social = trim($cols[5] ?? ''); $estado = trim($cols[6] ?? 'Publicado');
                $enlace_contenido = trim($cols[7] ?? ''); $formato = trim($cols[8] ?? '');
                $enlace_publicado = trim($cols[9] ?? '');
                break;
            default:
                $tema = trim($cols[1] ?? '');
                break;
        }
        
        if (empty($tema) && empty($idea)) continue;
        
        try {
            $stmtInsert->execute([
                $pestana['id'], $currentWeek, $mes, 2026, $fecha,
                $buyer ?: null, $pilar ?: null, $atributo ?: null,
                $etapa ?: null, $aspecto ?: null, $carrera ?: null,
                $tema ?: null, $idea ?: null, $red_social ?: null,
                $estado ?: 'Publicado', $formato ?: null, $horario ?: null,
                $enlace_contenido ?: null, $enlace_publicado ?: null,
                $observaciones ?: null, $user['id']
            ]);
            $imported++;
        } catch (Exception $e) {
            $message .= "Error en línea: " . htmlspecialchars(substr($line, 0, 80)) . " - " . $e->getMessage() . "<br>";
        }
    }
    
    $message = "<svg class=\"svg-icon\" viewBox=\"0 0 24 24\" style=\"width:1.2em;height:1.2em;vertical-align:bottom;stroke:currentColor;fill:none;stroke-width:2;\"><path d=\"M20 6L9 17l-5-5\"></path></svg> Se importaron $imported contenidos." . ($message ? "<br><br>Errores:<br>$message" : '');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Datos | UNIFRANZ Calendar</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
<div style="max-width: 800px; margin: 40px auto; padding: 20px; position: relative; z-index: 1;">
    <h1 style="color: var(--text-accent); margin-bottom: 24px;"><svg class="svg-icon" viewBox="0 0 24 24" style="width:1.2em;height:1.2em;vertical-align:bottom;stroke:currentColor;fill:none;stroke-width:2;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Importar Datos del Excel</h1>
    
    <?php if ($message): ?>
    <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); color: #86efac; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
        <?= $message ?>
    </div>
    <?php endif; ?>

    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px;">
        <h3>Instrucciones</h3>
        <ol style="color: var(--text-secondary); margin: 12px 0 24px; padding-left: 20px; line-height: 2;">
            <li>Abre el Excel en Google Sheets o LibreOffice</li>
            <li>Selecciona las filas que quieres importar (desde Junio 2026)</li>
            <li>Copia (Ctrl+C) las filas seleccionadas</li>
            <li>Selecciona la pestaña destino abajo</li>
            <li>Pega (Ctrl+V) en el campo de texto</li>
            <li>Click en "Importar"</li>
        </ol>

        <form method="POST">
            <div class="form-group">
                <label>Pestaña destino</label>
                <select name="pestana" class="form-control">
                    <option value="pauta">PAUTA</option>
                    <option value="pilares" selected>PILARES</option>
                    <option value="campana">CAMPAÑA</option>
                    <option value="otros">OTROS</option>
                    <option value="carreras">CARRERAS</option>
                    <option value="salutaciones">SALUTACIONES</option>
                    <option value="inscripciones">INSCRIPCIONES</option>
                    <option value="archivados">ARCHIVADOS</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Datos (pegar del Excel - formato tab-separated)</label>
                <textarea name="data" class="form-control" rows="15" placeholder="Pega aquí los datos copiados del Excel...&#10;&#10;Cada fila del Excel se convierte en una línea.&#10;Las columnas se separan por tabs automáticamente."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg"><svg class="svg-icon" viewBox="0 0 24 24" style="width:1.2em;height:1.2em;vertical-align:bottom;stroke:currentColor;fill:none;stroke-width:2;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Importar</button>
            <a href="../views/dashboard.php" class="btn btn-secondary btn-lg" style="margin-left: 8px;">← Volver al Dashboard</a>
        </form>
    </div>
</div>
</body>
</html>
