<?php
require_once __DIR__ . '/api_comun.php';
require_once __DIR__ . '/pdf_util.php';
$usuario = requerirSesion(['Estudiante', 'Profesor']);
$folio = trim((string) ($_GET['folio'] ?? ''));
$consulta = $conn->prepare(
    'SELECT r.id_reporte, r.folio, r.descripcion, r.fecha_creacion, c.nombre_categoria,
            e.nombre_estado, u.edificio, u.piso, u.espacio_especifico, a.nombre_area
     FROM reportes r
     JOIN categorias_problema c ON c.id_categoria = r.id_categoria
     JOIN estados e ON e.id_estado = r.id_estado_actual
     JOIN ubicaciones u ON u.id_ubicacion = r.id_ubicacion
     LEFT JOIN areas_atencion a ON a.id_area = r.id_area_asignada
     WHERE r.folio = ? AND r.id_usuario_creador = ? AND e.nombre_estado = \'Cerrado\''
);
$consulta->execute([$folio, (int) $usuario['id_usuario']]);
$reporte = $consulta->fetch();
if (!$reporte) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Comprobante no disponible.');
}
$historia = $conn->prepare(
    'SELECT h.fecha_hora, e.nombre_estado, h.accion_realizada,
            COALESCE(h.responsable_etiqueta, r.nombre_rol, \'Sistema\') AS responsable
     FROM historial_reportes h
     JOIN estados e ON e.id_estado = h.id_estado_nuevo
     LEFT JOIN usuarios u ON u.id_usuario = h.id_usuario_accion
     LEFT JOIN roles r ON r.id_rol = u.id_rol
     WHERE h.id_reporte = ? ORDER BY h.fecha_hora, h.id_historial'
);
$historia->execute([(int) $reporte['id_reporte']]);
$lineas = lineasReporte($reporte);
$lineas[] = '';
$lineas[] = 'Línea de tiempo:';
foreach ($historia->fetchAll() as $evento) {
    $lineas[] = $evento['fecha_hora'] . ' | ' . $evento['nombre_estado'] . ' | ' . $evento['accion_realizada'] . ' | ' . $evento['responsable'];
}
emitirPdf('Comprobante de atención', $lineas, 'comprobante_' . preg_replace('/[^A-Za-z0-9-]/', '_', $folio) . '.pdf');
