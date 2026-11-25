<?php
require_once __DIR__ . '/api_comun.php';

$usuario = requerirSesion(['Administrador', 'Jefe de departamento', 'Personal de atención']);
$idArea = $usuario['id_area'] === null ? null : (int) $usuario['id_area'];
if ($usuario['nombre_rol'] !== 'Administrador' && $idArea === null) {
    responder(403, ['ok' => false, 'mensaje' => 'Tu cuenta no tiene un área asignada.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filtro = trim((string) ($_GET['estado'] ?? ''));
    $where = [];
    $valores = [];

    if ($usuario['nombre_rol'] !== 'Administrador') {
        $where[] = 'r.id_area_asignada = :area';
        $valores['area'] = $idArea;
    } else {
        $filtroArea = filter_var($_GET['area'] ?? null, FILTER_VALIDATE_INT);
        if ($filtroArea !== false && $filtroArea !== null && $filtroArea > 0) {
            $where[] = 'r.id_area_asignada = :area';
            $valores['area'] = $filtroArea;
        }
    }

    if ($filtro !== '') {
        $estadoQuery = $conn->prepare('SELECT id_estado FROM estados WHERE nombre_estado = ? LIMIT 1');
        $estadoQuery->execute([$filtro]);
        $idEstado = $estadoQuery->fetchColumn();
        if (!$idEstado) {
            responder(400, ['ok' => false, 'mensaje' => 'El estado solicitado no existe.']);
        }
        $where[] = 'r.id_estado_actual = :estado';
        $valores['estado'] = (int) $idEstado;
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $consulta = $conn->prepare(
        'SELECT r.id_reporte, r.folio, r.descripcion, r.fecha_creacion, r.prioridad,
                c.nombre_categoria, e.nombre_estado,
                u.edificio, u.piso, u.area_general, u.espacio_especifico,
                creador.nombre_completo AS reportante,
                creador.identificador_institucional AS identificador,
                a.nombre_area
         FROM reportes r
         JOIN categorias_problema c ON c.id_categoria = r.id_categoria
         JOIN ubicaciones u ON u.id_ubicacion = r.id_ubicacion
         JOIN estados e ON e.id_estado = r.id_estado_actual
         JOIN usuarios creador ON creador.id_usuario = r.id_usuario_creador
         LEFT JOIN areas_atencion a ON a.id_area = r.id_area_asignada
         ' . $whereSql . '
         ORDER BY FIELD(e.nombre_estado, \'Pendiente\', \'En atención\', \'En verificación\', \'Cerrado\'),
                  r.fecha_creacion ASC'
    );
    $consulta->execute($valores);
    responder(200, ['ok' => true, 'datos' => $consulta->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}
if ($usuario['nombre_rol'] === 'Administrador') {
    responder(403, ['ok' => false, 'mensaje' => 'El Administrador puede consultar, pero no modificar reportes.']);
}

$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    $entrada = $_POST;
}

$folio = trim((string) ($entrada['folio'] ?? ''));
$accion = trim((string) ($entrada['accion'] ?? ''));
$nota = trim((string) ($entrada['nota'] ?? ''));

if ($folio === '' || mb_strlen($folio) > 32) {
    responder(400, ['ok' => false, 'mensaje' => 'Folio no válido.']);
}
if (!in_array($accion, ['atender', 'mas_tiempo', 'verificacion', 'cerrar'], true)) {
    responder(400, ['ok' => false, 'mensaje' => 'Acción no válida.']);
}
if ($accion === 'cerrar' && $usuario['nombre_rol'] !== 'Jefe de departamento') {
    responder(403, ['ok' => false, 'mensaje' => 'Solo el Jefe de departamento puede cerrar reportes.']);
}
if (mb_strlen($nota) > 1000) {
    responder(400, ['ok' => false, 'mensaje' => 'La nota no puede superar 1,000 caracteres.']);
}

$consulta = $conn->prepare(
    'SELECT r.id_reporte, r.id_estado_actual, e.nombre_estado
     FROM reportes r JOIN estados e ON e.id_estado = r.id_estado_actual
     WHERE r.folio = ? AND r.id_area_asignada = ? LIMIT 1'
);
$consulta->execute([$folio, $idArea]);
$reporte = $consulta->fetch();
if (!$reporte) {
    responder(404, ['ok' => false, 'mensaje' => 'Reporte no encontrado en tu área.']);
}

$estadoActual = (string) $reporte['nombre_estado'];
$estadoDestino = null;
$descripcionAccion = '';

if ($accion === 'atender' && $estadoActual === 'Pendiente') {
    $estadoDestino = 'En atención';
    $descripcionAccion = 'Reporte marcado como En atención';
} elseif ($accion === 'mas_tiempo' && $estadoActual === 'En atención') {
    $estadoDestino = 'En atención';
    $descripcionAccion = 'Actualización de avance: ' . ($nota !== '' ? $nota : 'Se requiere más tiempo.');
} elseif ($accion === 'verificacion' && $estadoActual === 'En atención') {
    $estadoDestino = 'En verificación';
    $descripcionAccion = 'Solución enviada a verificación' . ($nota !== '' ? ': ' . $nota : '');
} elseif ($accion === 'cerrar' && $estadoActual === 'En verificación') {
    $estadoDestino = 'Cerrado';
    $descripcionAccion = 'Reporte cerrado y finalizado' . ($nota !== '' ? ': ' . $nota : '');
} else {
    responder(409, ['ok' => false, 'mensaje' => 'La acción no corresponde al estado actual del reporte.']);
}

try {
    $conn->beginTransaction();
    $estadoDestinoQuery = $conn->prepare('SELECT id_estado FROM estados WHERE nombre_estado = ? LIMIT 1');
    $estadoDestinoQuery->execute([$estadoDestino]);
    $idDestino = $estadoDestinoQuery->fetchColumn();
    if (!$idDestino) {
        throw new RuntimeException('No existe el estado ' . $estadoDestino);
    }
    $idDestino = (int) $idDestino;

    if ($estadoDestino !== $estadoActual) {
        $actualizar = $conn->prepare(
            'UPDATE reportes SET id_estado_actual = ? WHERE id_reporte = ? AND id_area_asignada = ?'
        );
        $actualizar->execute([$idDestino, (int) $reporte['id_reporte'], $idArea]);
    }

    $historial = $conn->prepare(
        'INSERT INTO historial_reportes
            (id_reporte, id_estado_anterior, id_estado_nuevo, id_usuario_accion,
             accion_realizada, responsable_etiqueta)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $historial->execute([
        (int) $reporte['id_reporte'],
        (int) $reporte['id_estado_actual'],
        $idDestino,
        (int) $usuario['id_usuario'],
        $descripcionAccion,
        $usuario['nombre_rol'],
    ]);

    $notificar = $conn->prepare(
        'INSERT INTO notificaciones (id_usuario_destino, id_reporte, tipo_evento, mensaje)
         VALUES (?, ?, ?, ?)'
    );
    $creador = $conn->prepare('SELECT id_usuario_creador FROM reportes WHERE id_reporte = ?');
    $creador->execute([(int) $reporte['id_reporte']]);
    $idCreador = (int) $creador->fetchColumn();
    $notificar->execute([
        $idCreador,
        (int) $reporte['id_reporte'],
        'actualizacion reporte',
        'El reporte ' . $folio . ' cambió a ' . $estadoDestino,
    ]);

    $conn->commit();
    responder(200, ['ok' => true, 'mensaje' => 'Reporte actualizado a ' . $estadoDestino, 'estado' => $estadoDestino]);
} catch (Throwable $error) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Error al actualizar reporte de área: ' . $error->getMessage());
    responder(500, ['ok' => false, 'mensaje' => 'No se pudo actualizar el reporte.']);
}
