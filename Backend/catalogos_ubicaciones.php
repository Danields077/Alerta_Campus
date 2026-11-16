<?php
require_once __DIR__ . '/api_comun.php';
$usuario = requerirSesion(['Estudiante', 'Profesor', 'Administrador', 'Jefe de departamento']);

$accion = $_GET['accion'] ?? '';
if ($accion === 'categorias') {
    $categorias = $conn->query(
        'SELECT id_categoria, nombre_categoria FROM categorias_problema ORDER BY nombre_categoria'
    )->fetchAll();
    responder(200, ['ok' => true, 'datos' => $categorias]);
}
if ($accion === 'estados') {
    $datos = $conn->query('SELECT id_estado, nombre_estado FROM estados ORDER BY id_estado')->fetchAll();
    responder(200, ['ok' => true, 'datos' => $datos]);
}
if ($accion === 'areas_atencion' && $usuario['nombre_rol'] === 'Administrador') {
    $datos = $conn->query('SELECT id_area, nombre_area FROM areas_atencion ORDER BY nombre_area')->fetchAll();
    responder(200, ['ok' => true, 'datos' => $datos]);
}

$campos = [
    'edificios' => ['SELECT DISTINCT edificio AS etiqueta FROM ubicaciones ORDER BY edificio', []],
    'pisos' => ['SELECT DISTINCT piso AS etiqueta FROM ubicaciones WHERE edificio = ? ORDER BY piso', ['edificio']],
    'areas' => ['SELECT DISTINCT area_general AS etiqueta FROM ubicaciones WHERE edificio = ? AND piso = ? ORDER BY area_general', ['edificio', 'piso']],
    'espacios' => ['SELECT id_ubicacion, espacio_especifico AS etiqueta FROM ubicaciones WHERE edificio = ? AND piso = ? AND area_general = ? ORDER BY espacio_especifico', ['edificio', 'piso', 'area']],
];
if (!isset($campos[$accion])) {
    responder(400, ['ok' => false, 'mensaje' => 'Consulta de catálogo no válida.']);
}

[$sql, $parametros] = $campos[$accion];
$valores = [];
foreach ($parametros as $nombre) {
    $valor = trim((string) ($_GET[$nombre] ?? ''));
    if ($valor === '' || mb_strlen($valor) > 100) {
        responder(400, ['ok' => false, 'mensaje' => 'Completa los niveles de ubicación anteriores.']);
    }
    $valores[] = $valor;
}
$consulta = $conn->prepare($sql);
$consulta->execute($valores);
responder(200, ['ok' => true, 'datos' => $consulta->fetchAll()]);
