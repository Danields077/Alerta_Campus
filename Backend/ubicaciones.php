<?php
/**
 * Módulo 7 — Gestión de ubicaciones (registrar, editar, eliminar y consultar).
 * Solo Administrador.
 *
 * Una ubicación se identifica por edificio + piso + área general + espacio
 * específico (la estructura que ya usa el formulario de reportes en cascada).
 * La combinación completa no puede repetirse.
 *
 *   GET  ?accion=listar[&edificio=texto][&espacio=texto][&pagina=N]
 *   GET  ?accion=catalogos                      (valores existentes, para sugerencias)
 *   POST {accion:'crear'|'editar'|'eliminar', id_ubicacion, edificio, piso, area_general, espacio_especifico}
 */
require_once __DIR__ . '/api_comun.php';
require_once __DIR__ . '/usuarios_validacion.php';

$administrador = requerirSesion(['Administrador']);

const CAMPOS_UBICACION = [
    'edificio' => ['Edificio', 100],
    'piso' => ['Piso', 50],
    'area_general' => ['Área general', 100],
    'espacio_especifico' => ['Aula o espacio', 100],
];

/** Devuelve el id de OTRA ubicación con exactamente la misma combinación (0 = ninguna). */
function ubicacionDuplicada(PDO $conn, array $datos, int $excluirId = 0): int
{
    $consulta = $conn->prepare(
        'SELECT id_ubicacion FROM ubicaciones
         WHERE edificio = ? AND piso = ? AND area_general = ? AND espacio_especifico = ?
           AND id_ubicacion <> ? LIMIT 1'
    );
    $consulta->execute([
        $datos['edificio'], $datos['piso'], $datos['area_general'], $datos['espacio_especifico'], $excluirId,
    ]);
    return (int) $consulta->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = (string) ($_GET['accion'] ?? 'listar');

    if ($accion === 'catalogos') {
        responder(200, [
            'ok' => true,
            'edificios' => $conn->query('SELECT DISTINCT edificio FROM ubicaciones ORDER BY edificio')->fetchAll(PDO::FETCH_COLUMN),
            'pisos' => $conn->query('SELECT DISTINCT piso FROM ubicaciones ORDER BY piso')->fetchAll(PDO::FETCH_COLUMN),
            'areas' => $conn->query('SELECT DISTINCT area_general FROM ubicaciones ORDER BY area_general')->fetchAll(PDO::FETCH_COLUMN),
        ]);
    }
    if ($accion !== 'listar') {
        responder(400, ['ok' => false, 'mensaje' => 'Consulta de ubicaciones no válida.']);
    }

    $where = [];
    $valores = [];
    foreach (['edificio' => 'u.edificio', 'espacio' => 'u.espacio_especifico'] as $parametro => $columna) {
        $texto = trim((string) ($_GET[$parametro] ?? ''));
        if ($texto === '') {
            continue;
        }
        if (mb_strlen($texto) > 100) {
            responder(400, ['ok' => false, 'mensaje' => 'El filtro supera la longitud permitida.']);
        }
        $literal = strtr($texto, ['!' => '!!', '%' => '!%', '_' => '!_']);
        $where[] = $columna . " LIKE :$parametro ESCAPE '!'";
        $valores[$parametro] = '%' . $literal . '%';
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $conteo = $conn->prepare('SELECT COUNT(*) FROM ubicaciones u' . $whereSql);
    $conteo->execute($valores);
    $total = (int) $conteo->fetchColumn();

    $limite = 15;
    $paginas = (int) ceil($total / $limite);
    $paginaEntrada = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
    $pagina = $paginaEntrada === false || $paginaEntrada < 1 ? 1 : $paginaEntrada;
    if ($paginas > 0) {
        $pagina = min($pagina, $paginas);
    }

    $consulta = $conn->prepare(
        'SELECT u.id_ubicacion, u.edificio, u.piso, u.area_general, u.espacio_especifico,
                (SELECT COUNT(*) FROM reportes r WHERE r.id_ubicacion = u.id_ubicacion) AS total_reportes
         FROM ubicaciones u' . $whereSql .
        ' ORDER BY u.edificio, u.piso, u.area_general, u.espacio_especifico, u.id_ubicacion
          LIMIT :limite OFFSET :offset'
    );
    foreach ($valores as $clave => $valor) {
        $consulta->bindValue(':' . $clave, $valor, PDO::PARAM_STR);
    }
    $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
    $consulta->bindValue(':offset', ($pagina - 1) * $limite, PDO::PARAM_INT);
    $consulta->execute();

    responder(200, [
        'ok' => true,
        'datos' => $consulta->fetchAll(),
        'total' => $total,
        'pagina' => $pagina,
        'paginas' => $paginas,
        'mensaje' => $total === 0 ? 'No se encontraron ubicaciones' : '',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

$entrada = obtenerEntradaJson();
$accion = trim((string) ($entrada['accion'] ?? ''));
if (!in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
    responder(400, ['ok' => false, 'mensaje' => 'Acción de ubicaciones no válida.']);
}

$etiquetaFallo = [
    'crear' => 'La ubicación no se registró exitosamente',
    'editar' => 'La ubicación no se actualizó exitosamente',
    'eliminar' => 'La ubicación no se eliminó exitosamente',
][$accion];

$idUbicacion = filter_var($entrada['id_ubicacion'] ?? null, FILTER_VALIDATE_INT);
if ($accion !== 'crear' && ($idUbicacion === false || $idUbicacion === null || $idUbicacion < 1)) {
    responder(400, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'El identificador de la ubicación no es válido.']);
}

if ($accion !== 'crear') {
    $existe = $conn->prepare('SELECT 1 FROM ubicaciones WHERE id_ubicacion = ?');
    $existe->execute([$idUbicacion]);
    if (!$existe->fetchColumn()) {
        responder(404, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'La ubicación no existe.']);
    }
}

// ---------- ELIMINAR ----------
if ($accion === 'eliminar') {
    $enUso = $conn->prepare('SELECT COUNT(*) FROM reportes WHERE id_ubicacion = ?');
    $enUso->execute([$idUbicacion]);
    if ((int) $enUso->fetchColumn() > 0) {
        responder(409, [
            'ok' => false,
            'mensaje' => $etiquetaFallo,
            'detalle' => 'La ubicación tiene reportes asociados y no puede eliminarse por integridad de la información.',
        ]);
    }
    try {
        $eliminar = $conn->prepare('DELETE FROM ubicaciones WHERE id_ubicacion = ?');
        $eliminar->execute([$idUbicacion]);
        responder(200, ['ok' => true, 'mensaje' => 'La ubicación se eliminó exitosamente']);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') { // llave foránea: un reporte la acaba de usar
            responder(409, [
                'ok' => false,
                'mensaje' => $etiquetaFallo,
                'detalle' => 'La ubicación tiene reportes asociados y no puede eliminarse por integridad de la información.',
            ]);
        }
        error_log('Error al eliminar ubicación: ' . $error->getMessage());
        responder(500, ['ok' => false, 'mensaje' => $etiquetaFallo]);
    }
}

// ---------- CREAR / EDITAR ----------
$datos = [];
foreach (CAMPOS_UBICACION as $campo => [$etiqueta, $maximo]) {
    $valor = trim((string) ($entrada[$campo] ?? ''));
    if ($valor === '') {
        responder(400, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'Completa el campo «' . $etiqueta . '».']);
    }
    if (mb_strlen($valor) > $maximo) {
        responder(400, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'El campo «' . $etiqueta . '» supera los ' . $maximo . ' caracteres.']);
    }
    $datos[$campo] = $valor;
}
if (ubicacionDuplicada($conn, $datos, $accion === 'editar' ? (int) $idUbicacion : 0) > 0) {
    responder(409, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'La ubicación ya existe.']);
}

try {
    if ($accion === 'crear') {
        $insertar = $conn->prepare(
            'INSERT INTO ubicaciones (edificio, piso, area_general, espacio_especifico) VALUES (?, ?, ?, ?)'
        );
        $insertar->execute([$datos['edificio'], $datos['piso'], $datos['area_general'], $datos['espacio_especifico']]);
        responder(201, ['ok' => true, 'mensaje' => 'La ubicación se registró exitosamente']);
    }
    $actualizar = $conn->prepare(
        'UPDATE ubicaciones SET edificio = ?, piso = ?, area_general = ?, espacio_especifico = ? WHERE id_ubicacion = ?'
    );
    $actualizar->execute([
        $datos['edificio'], $datos['piso'], $datos['area_general'], $datos['espacio_especifico'], (int) $idUbicacion,
    ]);
    responder(200, ['ok' => true, 'mensaje' => 'La ubicación se actualizó exitosamente']);
} catch (PDOException $error) {
    if (esErrorDuplicado($error)) {
        responder(409, ['ok' => false, 'mensaje' => $etiquetaFallo, 'detalle' => 'La ubicación ya existe.']);
    }
    error_log('Error al guardar ubicación: ' . $error->getMessage());
    responder(500, ['ok' => false, 'mensaje' => $etiquetaFallo]);
}
