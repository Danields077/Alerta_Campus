<?php
/**
 * Generador mínimo de PDF con la fuente estándar Helvetica, sin dependencia
 * externa. Recibe texto ya autorizado por el endpoint que consulta los datos.
 */
function emitirPdf(string $titulo, array $lineas, string $archivo): void
{
    $lineas = array_merge([$titulo, str_repeat('=', 72), ''], $lineas);
    $lineasEnvuelto = [];
    foreach ($lineas as $linea) {
        foreach (explode("\n", wordwrap((string) $linea, 100, "\n", true)) as $renglon) {
            $lineasEnvuelto[] = $renglon;
        }
    }
    $lineas = $lineasEnvuelto;
    $paginas = array_chunk($lineas, 48);
    if (!$paginas) {
        $paginas = [['Sin datos']];
    }

    $objetos = [];
    $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $referencias = [];
    $siguiente = 4;
    foreach ($paginas as $lineasPagina) {
        $idPagina = $siguiente++;
        $idContenido = $siguiente++;
        $referencias[] = $idPagina . ' 0 R';
        $contenido = "BT\n/F1 9 Tf\n50 790 Td\n12 TL\n";
        foreach ($lineasPagina as $linea) {
            $texto = iconv('UTF-8', 'Windows-1252//TRANSLIT', (string) $linea);
            if ($texto === false) {
                $texto = '[Texto no representable]';
            }
            $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto);
            $texto = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
            $contenido .= '(' . $texto . ") Tj\nT*\n";
        }
        $contenido .= "ET";
        $objetos[$idPagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $idContenido . ' 0 R >>';
        $objetos[$idContenido] = '<< /Length ' . strlen($contenido) . " >>\nstream\n" . $contenido . "\nendstream";
    }
    $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $referencias) . '] /Count ' . count($paginas) . ' >>';
    ksort($objetos);
    $pdf = "%PDF-1.4\n";
    $desplazamientos = [0];
    foreach ($objetos as $id => $objeto) {
        $desplazamientos[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $objeto . "\nendobj\n";
    }
    $inicioXref = strlen($pdf);
    $maximo = max(array_keys($objetos));
    $pdf .= "xref\n0 " . ($maximo + 1) . "\n0000000000 65535 f \n";
    for ($id = 1; $id <= $maximo; $id++) {
        $pdf .= sprintf("%010d 00000 n \n", $desplazamientos[$id] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . ($maximo + 1) . " /Root 1 0 R >>\nstartxref\n" . $inicioXref . "\n%%EOF";
    header_remove('Content-Type');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $archivo) . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

function lineasReporte(array $fila): array
{
    $lineas = [
        'Folio: ' . $fila['folio'],
        'Categoría: ' . $fila['nombre_categoria'],
        'Ubicación: ' . $fila['edificio'] . ' / ' . $fila['piso'] . ' / ' . $fila['espacio_especifico'],
        'Área: ' . ($fila['nombre_area'] ?: 'Sin área asignada'),
        'Estado: ' . $fila['nombre_estado'],
        'Fecha: ' . $fila['fecha_creacion'],
    ];
    if (isset($fila['reportante'])) {
        $lineas[] = 'Reportante: ' . $fila['reportante'] . ' (' . $fila['correo_reportante'] . ')';
    }
    if (isset($fila['descripcion'])) {
        $lineas[] = '';
        $lineas[] = 'Descripción:';
        foreach (explode("\n", wordwrap((string) $fila['descripcion'], 92, "\n", true)) as $linea) {
            $lineas[] = $linea;
        }
    }
    return $lineas;
}
