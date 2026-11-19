<?php
/**
 * Módulo 8.1 — Consultar notificaciones y marcarlas como leídas.
 *
 * Cada usuario SOLO ve y modifica sus propias notificaciones (id_usuario_destino
 * sale siempre de la sesión, nunca del cliente).
 *
 *   GET  ?accion=contar            -> { no_leidas }                 (insignia de la barra)
 *   GET  ?accion=listar[&estado=no_leidas|leidas][&pagina=N]
 *   POST {accion:'marcar_leida', id_notificacion}
 *   POST {accion:'marcar_todas'}
 *
 * Con &sondeo=1 (actualización automática por AJAX) NO se renueva la sesión,
 * para respetar los 30 minutos de inactividad.
 */
require_once __DIR__ . '/api_comun.php';
require_once __DIR__ . '/usuarios_validacion.php';

$esSondeo = $_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['sondeo'] ?? '') === '1';
$usuario = requerirSesion([], !$esSondeo);
$idUsuario = (int) $usuario['id_usuario'];

function contarNoLeidas(PDO $conn, int $idUsuario): int
{
    $consulta = $conn->prepare('SELECT COUNT(*) FROM notificaciones WHERE id_usuario_destino = ? AND leida = 0');
    $consulta->execute([$idUsuario]);
    return (int) $consulta->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = (string) ($_GET['accion'] ?? 'listar');

    if ($accion === 'contar') {
        responder(200, ['ok' => true, 'no_leidas' => contarNoLeidas($conn, $idUsuario)]);
    }
    if ($accion !== 'listar') {
        responder(400, ['ok' => false, 'mensaje' => 'Consulta de notificaciones no válida.']);
    }

    $estado = trim((string) ($_GET['estado'] ?? ''));
    $where = 'n.id_usuario_destino = :usuario';
    if ($estado === 'no_leidas') {
        $where .= ' AND n.leida = 0';
    } elseif ($estado === 'leidas') {
        $where .= ' AND n.leida = 1';
    } elseif ($estado !== '') {
        responder(400, ['ok' => false, 'mensaje' => 'El filtro seleccionado no es válido.']);
    }

    $conteo = $conn->prepare('SELECT COUNT(*) FROM notificaciones n WHERE ' . $where);
    $conteo->execute(['usuario' => $idUsuario]);
    $total = (int) $conteo->fetchColumn();

    $limite = 10;
    $paginas = (int) ceil($total / $limite);
    $paginaEntrada = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
    $pagina = $paginaEntrada === false || $paginaEntrada < 1 ? 1 : $paginaEntrada;
    if ($paginas > 0) {
        $pagina = min($pagina, $paginas);
    }

    $consulta = $conn->prepare(
        'SELECT n.id_notificacion, n.id_reporte, r.folio, n.tipo_evento, n.mensaje,
                n.leida, n.fecha_generacion
         FROM notificaciones n JOIN reportes r ON r.id_reporte = n.id_reporte
         WHERE ' . $where . '
         ORDER BY n.fecha_generacion DESC, n.id_notificacion DESC
         LIMIT :limite OFFSET :offset'
    );
    $consulta->bindValue(':usuario', $idUsuario, PDO::PARAM_INT);
    $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
    $consulta->bindValue(':offset', ($pagina - 1) * $limite, PDO::PARAM_INT);
    $consulta->execute();

    responder(200, [
        'ok' => true,
        'datos' => $consulta->fetchAll(),
        'total' => $total,
        'pagina' => $pagina,
        'paginas' => $paginas,
        'no_leidas' => contarNoLeidas($conn, $idUsuario),
        'mensaje' => $total === 0 ? 'No tienes notificaciones' : '',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

$entrada = obtenerEntradaJson();
$accion = trim((string) ($entrada['accion'] ?? ''));

if ($accion === 'marcar_todas') {
    $actualizar = $conn->prepare('UPDATE notificaciones SET leida = 1 WHERE id_usuario_destino = ? AND leida = 0');
    $actualizar->execute([$idUsuario]);
    responder(200, ['ok' => true, 'mensaje' => 'Todas las notificaciones se marcaron como leídas', 'no_leidas' => 0]);
}

if ($accion === 'marcar_leida') {
    $idNotificacion = filter_var($entrada['id_notificacion'] ?? null, FILTER_VALIDATE_INT);
    if ($idNotificacion === false || $idNotificacion === null || $idNotificacion < 1) {
        responder(400, ['ok' => false, 'mensaje' => 'La notificación seleccionada no es válida.']);
    }
    // El filtro por destinatario impide marcar notificaciones ajenas.
    $existe = $conn->prepare('SELECT 1 FROM notificaciones WHERE id_notificacion = ? AND id_usuario_destino = ?');
    $existe->execute([$idNotificacion, $idUsuario]);
    if (!$existe->fetchColumn()) {
        responder(404, ['ok' => false, 'mensaje' => 'Notificación no encontrada.']);
    }
    $actualizar = $conn->prepare(
        'UPDATE notificaciones SET leida = 1 WHERE id_notificacion = ? AND id_usuario_destino = ?'
    );
    $actualizar->execute([$idNotificacion, $idUsuario]);
    responder(200, ['ok' => true, 'no_leidas' => contarNoLeidas($conn, $idUsuario)]);
}

responder(400, ['ok' => false, 'mensaje' => 'Acción de notificaciones no válida.']);
