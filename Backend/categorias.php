<?php
/**
 * Módulo 6 — Gestión de categorías (registrar, editar, eliminar y consultar).
 * Solo Administrador.
 *
 *   GET  ?accion=listar[&id=N][&nombre=texto]   (filtros opcionales)
 *   GET  ?accion=catalogos                      (áreas responsables)
 *   POST {accion:'crear'|'editar'|'eliminar', id_categoria, nombre_categoria, id_area_responsable}
 *
 * El área responsable de la categoría es la que usa la asignación automática
 * (módulo 3.1). Al editarla solo afecta a reportes registrados DESPUÉS, porque
 * cada reporte guarda su propia id_area_asignada al crearse.
 */
require_once __DIR__ . '/api_comun.php';
require_once __DIR__ . '/usuarios_validacion.php';

$administrador = requerirSesion(['Administrador']);

/** Devuelve el id de otra categoría con ese nombre (0 = ninguna). */
function categoriaConNombre(PDO $conn, string $nombre, int $excluirId = 0): int
{
    $consulta = $conn->prepare(
        'SELECT id_categoria FROM categorias_problema WHERE nombre_categoria = ? AND id_categoria <> ? LIMIT 1'
    );
    $consulta->execute([$nombre, $excluirId]);
    return (int) $consulta->fetchColumn();
}

function areaExiste(PDO $conn, int $idArea): bool
{
    $consulta = $conn->prepare('SELECT 1 FROM areas_atencion WHERE id_area = ?');
    $consulta->execute([$idArea]);
    return (bool) $consulta->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = (string) ($_GET['accion'] ?? 'listar');

    if ($accion === 'catalogos') {
        $areas = $conn->query('SELECT id_area, nombre_area FROM areas_atencion ORDER BY nombre_area')->fetchAll();
        responder(200, ['ok' => true, 'areas' => $areas]);
    }
    if ($accion !== 'listar') {
        responder(400, ['ok' => false, 'mensaje' => 'Consulta de categorías no válida.']);
    }

    $where = [];
    $valores = [];
    $idFiltro = trim((string) ($_GET['id'] ?? ''));
    if ($idFiltro !== '') {
        $id = filter_var($idFiltro, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            responder(400, ['ok' => false, 'mensaje' => 'El identificador de la categoría no es válido.']);
        }
        $where[] = 'c.id_categoria = :id';
        $valores['id'] = $id;
    }
    $nombreFiltro = trim((string) ($_GET['nombre'] ?? ''));
    if ($nombreFiltro !== '') {
        if (mb_strlen($nombreFiltro) > 100) {
            responder(400, ['ok' => false, 'mensaje' => 'El filtro supera la longitud permitida.']);
        }
        $literal = strtr($nombreFiltro, ['!' => '!!', '%' => '!%', '_' => '!_']);
        $where[] = "c.nombre_categoria LIKE :nombre ESCAPE '!'";
        $valores['nombre'] = '%' . $literal . '%';
    }

    $consulta = $conn->prepare(
        'SELECT c.id_categoria, c.nombre_categoria, c.id_area_responsable, a.nombre_area,
                (SELECT COUNT(*) FROM reportes r WHERE r.id_categoria = c.id_categoria) AS total_reportes
         FROM categorias_problema c
         LEFT JOIN areas_atencion a ON a.id_area = c.id_area_responsable' .
        ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
        ' ORDER BY c.nombre_categoria, c.id_categoria'
    );
    $consulta->execute($valores);
    $datos = $consulta->fetchAll();
    responder(200, [
        'ok' => true,
        'datos' => $datos,
        'total' => count($datos),
        'mensaje' => $datos ? '' : 'No se encontraron categorías',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

$entrada = obtenerEntradaJson();
$accion = trim((string) ($entrada['accion'] ?? ''));
if (!in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
    responder(400, ['ok' => false, 'mensaje' => 'Acción de categorías no válida.']);
}

$etiquetaFallo = [
    'crear' => 'La categoría no se registró exitosamente',
    'editar' => 'La categoría no se actualizó exitosamente',
    'eliminar' => 'La categoría no se eliminó exitosamente',
][$accion];

$idCategoria = filter_var($entrada['id_categoria'] ?? null, FILTER_VALIDATE_INT);
if ($accion !== 'crear' && ($idCategoria === false || $idCategoria === null || $idCategoria < 1)) {
    responder(400, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'El identificador de la categoría no es válido.']);
}

// ---------- ELIMINAR ----------
if ($accion === 'eliminar') {
    $existe = $conn->prepare('SELECT 1 FROM categorias_problema WHERE id_categoria = ?');
    $existe->execute([$idCategoria]);
    if (!$existe->fetchColumn()) {
        responder(404, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'La categoría no existe.']);
    }
    $enUso = $conn->prepare('SELECT COUNT(*) FROM reportes WHERE id_categoria = ?');
    $enUso->execute([$idCategoria]);
    if ((int) $enUso->fetchColumn() > 0) {
        responder(409, [
            'ok' => false,
            'mensaje' => $etiquetaFallo,
            'detalle' => 'La categoría tiene reportes asociados y no puede eliminarse por integridad de la información.',
        ]);
    }
    try {
        $eliminar = $conn->prepare('DELETE FROM categorias_problema WHERE id_categoria = ?');
        $eliminar->execute([$idCategoria]);
        responder(200, ['ok' => true, 'mensaje' => 'La categoría se eliminó exitosamente']);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') { // llave foránea: un reporte la acaba de usar
            responder(409, [
                'ok' => false,
                'mensaje' => $etiquetaFallo,
                'detalle' => 'La categoría tiene reportes asociados y no puede eliminarse por integridad de la información.',
            ]);
        }
        error_log('Error al eliminar categoría: ' . $error->getMessage());
        responder(500, ['ok' => false, 'mensaje' => $etiquetaFallo]);
    }
}

// ---------- CREAR / EDITAR ----------
$nombre = trim((string) ($entrada['nombre_categoria'] ?? ''));
$areaEntrada = filter_var($entrada['id_area_responsable'] ?? null, FILTER_VALIDATE_INT);

if ($nombre === '' || $areaEntrada === false || $areaEntrada === null) {
    responder(400, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'Completa el nombre de la categoría y el área responsable.']);
}
if (mb_strlen($nombre) > 100) {
    responder(400, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'El nombre de la categoría supera los 100 caracteres.']);
}
if (!areaExiste($conn, (int) $areaEntrada)) {
    responder(400, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'Selecciona un área responsable válida.']);
}
if ($accion === 'editar') {
    $existe = $conn->prepare('SELECT 1 FROM categorias_problema WHERE id_categoria = ?');
    $existe->execute([$idCategoria]);
    if (!$existe->fetchColumn()) {
        responder(404, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'La categoría no existe.']);
    }
}
if (categoriaConNombre($conn, $nombre, $accion === 'editar' ? (int) $idCategoria : 0) > 0) {
    responder(409, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'La categoría ya existe.']);
}

try {
    if ($accion === 'crear') {
        $insertar = $conn->prepare('INSERT INTO categorias_problema (nombre_categoria, id_area_responsable) VALUES (?, ?)');
        $insertar->execute([$nombre, (int) $areaEntrada]);
        responder(201, ['ok' => true, 'mensaje' => 'La categoría se registró exitosamente']);
    }
    $actualizar = $conn->prepare(
        'UPDATE categorias_problema SET nombre_categoria = ?, id_area_responsable = ? WHERE id_categoria = ?'
    );
    $actualizar->execute([$nombre, (int) $areaEntrada, (int) $idCategoria]);
    responder(200, ['ok' => true, 'mensaje' => 'La categoría se actualizó exitosamente']);
} catch (PDOException $error) {
    if (esErrorDuplicado($error)) {
        responder(409, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'La categoría ya existe.']);
    }
    error_log('Error al guardar categoría: ' . $error->getMessage());
    responder(500, ['ok' => false, 'mensaje' => $etiquetaFallo]);
}
