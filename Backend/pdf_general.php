<?php
require_once __DIR__ . '/api_comun.php';
require_once __DIR__ . '/pdf_util.php';
$usuario = requerirSesion(['Administrador']);
$filtros = [
    'folio' => trim((string) ($_GET['folio'] ?? '')),
    'estado' => $_GET['estado'] ?? '',
    'categoria' => $_GET['categoria'] ?? '',
    'area' => $_GET['area'] ?? '',
    'ubicacion' => $_GET['ubicacion'] ?? '',
    'desde' => $_GET['desde'] ?? '',
    'hasta' => $_GET['hasta'] ?? '',
];
[$where, $valores] = condicionesFiltro($filtros);
$consulta = $conn->prepare(
    'SELECT r.folio, r.fecha_creacion, c.nombre_categoria, e.nombre_estado,
            u.edificio, u.piso, u.espacio_especifico, a.nombre_area,
            creador.nombre_completo AS reportante, creador.correo AS correo_reportante
     FROM reportes r
     JOIN categorias_problema c ON c.id_categoria = r.id_categoria
     JOIN estados e ON e.id_estado = r.id_estado_actual
     JOIN ubicaciones u ON u.id_ubicacion = r.id_ubicacion
     LEFT JOIN areas_atencion a ON a.id_area = r.id_area_asignada
     JOIN usuarios creador ON creador.id_usuario = r.id_usuario_creador
     WHERE ' . ($where ? implode(' AND ', $where) : '1 = 1') .
    ' ORDER BY r.fecha_creacion DESC'
);
$consulta->execute($valores);
$lineas = [];
foreach ($consulta->fetchAll() as $fila) {
    $lineas = array_merge($lineas, lineasReporte($fila), ['']);
}
emitirPdf('Consulta global de reportes', $lineas ?: ['No se encontraron reportes.'], 'reportes_globales.pdf');
