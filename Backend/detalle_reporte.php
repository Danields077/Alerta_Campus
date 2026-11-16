<?php
require_once __DIR__ . '/api_comun.php';
$usuario = requerirSesion(['Estudiante', 'Profesor', 'Administrador', 'Jefe de departamento']);
$folio = trim((string) ($_GET['folio'] ?? ''));
if ($folio === '' || mb_strlen($folio) > 32) {
    responder(404, ['ok' => false, 'mensaje' => 'Reporte no encontrado.']);
}

$sql = 'SELECT r.id_reporte, r.folio, r.descripcion, r.fecha_creacion, r.id_area_asignada,
               c.nombre_categoria, e.nombre_estado, u.edificio, u.piso, u.area_general, u.espacio_especifico,
               a.nombre_area
        FROM reportes r
        JOIN categorias_problema c ON c.id_categoria = r.id_categoria
        JOIN estados e ON e.id_estado = r.id_estado_actual
        JOIN ubicaciones u ON u.id_ubicacion = r.id_ubicacion
        LEFT JOIN areas_atencion a ON a.id_area = r.id_area_asignada
        WHERE r.folio = ?';
$parametros = [$folio];
if (in_array($usuario['nombre_rol'], ['Estudiante', 'Profesor'], true)) {
    $sql .= ' AND r.id_usuario_creador = ?';
    $parametros[] = (int) $usuario['id_usuario'];
} elseif ($usuario['nombre_rol'] === 'Jefe de departamento') {
    $sql .= ' AND r.id_area_asignada = ?';
    $parametros[] = $usuario['id_area'] === null ? 0 : (int) $usuario['id_area'];
}
$detalleQuery = $conn->prepare($sql);
$detalleQuery->execute($parametros);
$reporte = $detalleQuery->fetch();
if (!$reporte) {
    responder(404, ['ok' => false, 'mensaje' => 'Reporte no encontrado.']);
}

$historiaQuery = $conn->prepare(
    'SELECT h.fecha_hora, e.nombre_estado, h.accion_realizada,
            COALESCE(h.responsable_etiqueta, r.nombre_rol, \'Sistema\') AS responsable
     FROM historial_reportes h
     JOIN estados e ON e.id_estado = h.id_estado_nuevo
     LEFT JOIN usuarios u ON u.id_usuario = h.id_usuario_accion
     LEFT JOIN roles r ON r.id_rol = u.id_rol
     WHERE h.id_reporte = ? ORDER BY h.fecha_hora, h.id_historial'
);
$historiaQuery->execute([(int) $reporte['id_reporte']]);
$evidenciaQuery = $conn->prepare(
    'SELECT id_evidencia, tipo_evidencia, mime_type
     FROM evidencias_reportes WHERE id_reporte = ? ORDER BY id_evidencia'
);
$evidenciaQuery->execute([(int) $reporte['id_reporte']]);
$datos = ['reporte' => $reporte, 'historial' => $historiaQuery->fetchAll(), 'evidencias' => $evidenciaQuery->fetchAll()];
if ($usuario['nombre_rol'] === 'Administrador') {
    $creador = $conn->prepare('SELECT nombre_completo, correo FROM usuarios WHERE id_usuario = (SELECT id_usuario_creador FROM reportes WHERE id_reporte = ?)');
    $creador->execute([(int) $reporte['id_reporte']]);
    $datos['reportante'] = $creador->fetch();
}
responder(200, ['ok' => true, 'datos' => $datos]);
