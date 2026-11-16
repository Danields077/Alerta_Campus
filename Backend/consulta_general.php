<?php
require_once __DIR__ . '/api_comun.php';
$usuario = requerirSesion(['Administrador', 'Jefe de departamento']);

$filtros = [
    'folio' => trim((string) ($_GET['folio'] ?? '')),
    'estado' => $_GET['estado'] ?? '',
    'categoria' => $_GET['categoria'] ?? '',
    'area' => $_GET['area'] ?? '',
    'ubicacion' => $_GET['ubicacion'] ?? '',
    'desde' => $_GET['desde'] ?? '',
    'hasta' => $_GET['hasta'] ?? '',
];
if ($usuario['nombre_rol'] === 'Jefe de departamento') {
    // El jefe no puede acotar ni cambiar el área de sesión desde el cliente.
    $filtros['area'] = '';
}
[$where, $valores] = condicionesFiltro($filtros);
if ($usuario['nombre_rol'] === 'Jefe de departamento') {
    // El alcance se fuerza con el área almacenada en la sesión validada contra BD.
    if ($usuario['id_area'] === null) {
        $where[] = '1 = 0';
    } else {
        $where[] = 'r.id_area_asignada = :area_sesion';
        $valores['area_sesion'] = (int) $usuario['id_area'];
    }
}

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$limite = 15;
$offset = ($pagina - 1) * $limite;
$base = ' FROM reportes r
    JOIN categorias_problema c ON c.id_categoria = r.id_categoria
    JOIN ubicaciones u ON u.id_ubicacion = r.id_ubicacion
    JOIN estados e ON e.id_estado = r.id_estado_actual
    LEFT JOIN areas_atencion a ON a.id_area = r.id_area_asignada';
$whereSql = ' WHERE ' . implode(' AND ', $where);
$conteo = $conn->prepare('SELECT COUNT(*)' . $base . $whereSql);
$conteo->execute($valores);
$total = (int) $conteo->fetchColumn();
$camposIdentidad = $usuario['nombre_rol'] === 'Administrador'
    ? ', creador.nombre_completo AS reportante, creador.correo AS correo_reportante'
    : '';
$joinIdentidad = $usuario['nombre_rol'] === 'Administrador'
    ? ' JOIN usuarios creador ON creador.id_usuario = r.id_usuario_creador'
    : '';
$consulta = $conn->prepare(
    'SELECT r.folio, r.fecha_creacion, c.nombre_categoria, e.nombre_estado,
            u.edificio, u.piso, u.espacio_especifico, a.nombre_area' . $camposIdentidad .
    $base . $joinIdentidad . $whereSql .
    ' ORDER BY r.fecha_creacion DESC, r.id_reporte DESC LIMIT :limite OFFSET :offset'
);
foreach ($valores as $clave => $valor) {
    $consulta->bindValue(':' . $clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
$consulta->bindValue(':offset', $offset, PDO::PARAM_INT);
$consulta->execute();
responder(200, [
    'ok' => true, 'datos' => $consulta->fetchAll(), 'total' => $total,
    'pagina' => $pagina, 'paginas' => (int) ceil($total / $limite),
    'mensaje' => $total === 0 ? 'No se encontraron reportes' : '',
]);
