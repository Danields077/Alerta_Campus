<?php
require_once __DIR__ . '/api_comun.php';
require_once __DIR__ . '/pdf_util.php';
$usuario = requerirSesion(['Estudiante', 'Profesor']);
$filtros = [
    'folio' => trim((string) ($_GET['folio'] ?? '')),
    'estado' => $_GET['estado'] ?? '',
    'categoria' => $_GET['categoria'] ?? '',
    'desde' => $_GET['desde'] ?? '',
    'hasta' => $_GET['hasta'] ?? '',
];
[$where, $valores] = condicionesFiltro($filtros);
$where[] = 'r.id_usuario_creador = :creador';
$valores['creador'] = (int) $usuario['id_usuario'];
$consulta = $conn->prepare(
    'SELECT r.folio, r.fecha_creacion, c.nombre_categoria, e.nombre_estado,
            u.edificio, u.piso, u.espacio_especifico
     FROM reportes r
     JOIN categorias_problema c ON c.id_categoria = r.id_categoria
     JOIN estados e ON e.id_estado = r.id_estado_actual
     JOIN ubicaciones u ON u.id_ubicacion = r.id_ubicacion
     WHERE ' . implode(' AND ', $where) . ' ORDER BY r.fecha_creacion DESC'
);
$consulta->execute($valores);
$lineas = [];
foreach ($consulta->fetchAll() as $fila) {
    $lineas = array_merge($lineas, lineasReporte($fila), ['']);
}
emitirPdf('Historial de reportes del usuario', $lineas ?: ['No se encontraron reportes.'], 'historial_reportes.pdf');
