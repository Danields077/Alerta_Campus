<?php
/**
 * Utilidades comunes para endpoints JSON: sesión expirable, RBAC y respuestas.
 */
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function requerirSesion(array $rolesPermitidos = []): array
{
    $limiteInactividad = 1800;
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();

    $ahora = time();
    if (empty($_SESSION['usuario_id']) || empty($_SESSION['ultima_actividad'])
        || ($ahora - (int) $_SESSION['ultima_actividad']) > $limiteInactividad) {
        $_SESSION = [];
        session_destroy();
        responder(401, ['ok' => false, 'mensaje' => 'La sesión expiró. Inicia sesión nuevamente.']);
    }

    global $conn;
    $consulta = $conn->prepare(
        'SELECT u.id_usuario, u.id_rol, u.id_area, u.estado, r.nombre_rol
         FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
         WHERE u.id_usuario = ? LIMIT 1'
    );
    $consulta->execute([(int) $_SESSION['usuario_id']]);
    $usuario = $consulta->fetch();
    if (!$usuario || strcasecmp((string) $usuario['estado'], 'Activo') !== 0) {
        $_SESSION = [];
        session_destroy();
        responder(401, ['ok' => false, 'mensaje' => 'La cuenta no tiene una sesión activa.']);
    }
    if ($rolesPermitidos && !in_array($usuario['nombre_rol'], $rolesPermitidos, true)) {
        responder(403, ['ok' => false, 'mensaje' => 'No tienes permisos para realizar esta acción.']);
    }

    $_SESSION['ultima_actividad'] = $ahora;
    $_SESSION['id_rol'] = (int) $usuario['id_rol'];
    $_SESSION['nombre_rol'] = $usuario['nombre_rol'];
    $_SESSION['id_area'] = $usuario['id_area'] === null ? null : (int) $usuario['id_area'];
    return $usuario;
}

function parametroEntero(string $nombre, int $minimo = 1): ?int
{
    $valor = filter_input(INPUT_GET, $nombre, FILTER_VALIDATE_INT);
    return $valor !== false && $valor !== null && $valor >= $minimo ? $valor : null;
}

function condicionesFiltro(array $filtros, bool $incluirArea = false): array
{
    $where = [];
    $valores = [];
    if (!empty($filtros['folio'])) {
        $where[] = 'r.folio LIKE :folio';
        $valores['folio'] = $filtros['folio'] . '%';
    }
    foreach (['estado' => 'r.id_estado_actual', 'categoria' => 'r.id_categoria', 'area' => 'r.id_area_asignada'] as $clave => $columna) {
        if (isset($filtros[$clave]) && $filtros[$clave] !== '') {
            $id = filter_var($filtros[$clave], FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                responder(400, ['ok' => false, 'mensaje' => 'Uno de los filtros no es válido.']);
            }
            $where[] = $columna . ' = :' . $clave;
            $valores[$clave] = $id;
        }
    }
    if (!empty($filtros['desde'])) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtros['desde']) || !checkdate((int) substr($filtros['desde'], 5, 2), (int) substr($filtros['desde'], 8, 2), (int) substr($filtros['desde'], 0, 4))) {
            responder(400, ['ok' => false, 'mensaje' => 'La fecha inicial no es válida.']);
        }
        $where[] = 'r.fecha_creacion >= :desde';
        $valores['desde'] = $filtros['desde'] . ' 00:00:00';
    }
    if (!empty($filtros['hasta'])) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtros['hasta']) || !checkdate((int) substr($filtros['hasta'], 5, 2), (int) substr($filtros['hasta'], 8, 2), (int) substr($filtros['hasta'], 0, 4))) {
            responder(400, ['ok' => false, 'mensaje' => 'La fecha final no es válida.']);
        }
        $where[] = 'r.fecha_creacion < DATE_ADD(:hasta, INTERVAL 1 DAY)';
        $valores['hasta'] = $filtros['hasta'];
    }
    if (!empty($filtros['ubicacion'])) {
        $ubicacion = filter_var($filtros['ubicacion'], FILTER_VALIDATE_INT);
        if ($ubicacion === false || $ubicacion < 1) {
            responder(400, ['ok' => false, 'mensaje' => 'La ubicación seleccionada no es válida.']);
        }
        $where[] = 'r.id_ubicacion = :ubicacion';
        $valores['ubicacion'] = $ubicacion;
    }
    if (!empty($filtros['desde']) && !empty($filtros['hasta']) && $filtros['desde'] > $filtros['hasta']) {
        responder(400, ['ok' => false, 'mensaje' => 'El rango de fechas no es válido.']);
    }
    return [$where, $valores];
}
