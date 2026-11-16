<?php
require_once __DIR__ . '/api_comun.php';
$usuario = requerirSesion(['Estudiante', 'Profesor', 'Administrador', 'Jefe de departamento']);
$id = parametroEntero('id');
if (!$id) {
    http_response_code(404);
    exit;
}
$sql = 'SELECT ev.ruta, ev.mime_type, r.id_usuario_creador, r.id_area_asignada
        FROM evidencias_reportes ev JOIN reportes r ON r.id_reporte = ev.id_reporte
        WHERE ev.id_evidencia = ? AND ev.tipo_evidencia = \'reporte\'';
$consulta = $conn->prepare($sql);
$consulta->execute([$id]);
$evidencia = $consulta->fetch();
$permitido = $evidencia !== false;
if ($permitido && in_array($usuario['nombre_rol'], ['Estudiante', 'Profesor'], true)) {
    $permitido = (int) $evidencia['id_usuario_creador'] === (int) $usuario['id_usuario'];
}
if ($permitido && $usuario['nombre_rol'] === 'Jefe de departamento') {
    $permitido = $usuario['id_area'] !== null && (int) $evidencia['id_area_asignada'] === (int) $usuario['id_area'];
}
$ruta = $permitido ? dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $evidencia['ruta']) : '';
if (!$permitido || !is_file($ruta)) {
    http_response_code(404);
    exit;
}
header_remove('Content-Type');
header('Content-Type: ' . $evidencia['mime_type']);
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
