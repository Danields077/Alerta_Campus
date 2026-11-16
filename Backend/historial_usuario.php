<?php
require_once __DIR__ . '/api_comun.php';
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

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$limite = 10;
$offset = ($pagina - 1) * $limite;
$base = ' FROM reportes r
    JOIN categorias_problema c ON c.id_categoria = r.id_categoria
    JOIN ubicaciones u ON u.id_ubicacion = r.id_ubicacion
    JOIN estados e ON e.id_estado = r.id_estado_actual
    WHERE ' . implode(' AND ', $where);
$conteo = $conn->prepare('SELECT COUNT(*)' . $base);
$conteo->execute($valores);
$total = (int) $conteo->fetchColumn();
$consulta = $conn->prepare(
    'SELECT r.folio, r.fecha_creacion, c.nombre_categoria, e.nombre_estado,
            u.edificio, u.piso, u.espacio_especifico
     ' . $base . ' ORDER BY r.fecha_creacion DESC, r.id_reporte DESC LIMIT :limite OFFSET :offset'
);
foreach ($valores as $clave => $valor) {
    $consulta->bindValue(':' . $clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
$consulta->bindValue(':offset', $offset, PDO::PARAM_INT);
$consulta->execute();
responder(200, [
    'ok' => true,
    'datos' => $consulta->fetchAll(),
    'total' => $total,
    'pagina' => $pagina,
    'paginas' => (int) ceil($total / $limite),
    'mensaje' => $total === 0 ? 'No se encontraron reportes' : '',
]);
