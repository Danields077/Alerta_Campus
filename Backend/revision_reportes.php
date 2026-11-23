<?php
/**
 * Módulo 4.1 — Control de calidad: validación del Jefe de departamento.
 *
 * Solo el Jefe de departamento, y solo sobre reportes de SU área que estén EN VERIFICACIÓN.
 *
 *   GET  ?accion=listar[&folio=ALC-...]      reportes de su área en verificación
 *   GET  ?accion=detalle&folio=ALC-...        comentarios y evidencias de la solución + línea de tiempo
 *   POST {folio, evaluacion:'Aprobado'|'Rechazado', observaciones}
 *
 *   Aprobado  -> CERRADO (ya no puede modificarse) y se notifica al reportante.
 *   Rechazado -> Gestión de Rechazos: el reporte se reabre en PENDIENTE con las
 *                observaciones visibles para el Personal de atención, que es notificado.
 *
 * Privacidad: nunca se expone la identidad del reportante (solo el Administrador la ve).
 */
require_once __DIR__ . '/api_comun.php';
require_once __DIR__ . '/notificaciones_util.php';

const PREFIJO_SOLUCION = 'Solución enviada a verificación: ';
const PREFIJO_RECHAZO = 'Reporte rechazado: ';

$jefe = requerirSesion(['Jefe de departamento']);
$idArea = $jefe['id_area'] === null ? null : (int) $jefe['id_area'];
if ($idArea === null) {
    responder(403, ['ok' => false, 'mensaje' => 'Tu cuenta no tiene un área asignada.']);
}

function idEstadoPorNombre(PDO $conn, string $nombre): int
{
    $consulta = $conn->prepare('SELECT id_estado FROM estados WHERE nombre_estado = ? LIMIT 1');
    $consulta->execute([$nombre]);
    $id = $consulta->fetchColumn();
    if (!$id) {
        throw new RuntimeException('Falta el estado ' . $nombre . ' en el catálogo.');
    }
    return (int) $id;
}

function folioValido(string $folio): bool
{
    return $folio !== '' && mb_strlen($folio) <= 32;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = (string) ($_GET['accion'] ?? 'listar');
    $folio = trim((string) ($_GET['folio'] ?? ''));

    if ($accion === 'listar') {
        if (mb_strlen($folio) > 32) {
            responder(400, ['ok' => false, 'mensaje' => 'Folio no válido.']);
        }
        $valores = ['area' => $idArea, 'estado' => 'En verificación'];
        $filtroFolio = '';
        if ($folio !== '') {
            $literal = strtr($folio, ['!' => '!!', '%' => '!%', '_' => '!_']);
            $filtroFolio = " AND r.folio LIKE :folio ESCAPE '!'";
            $valores['folio'] = $literal . '%';
        }
        $consulta = $conn->prepare(
            'SELECT r.folio, r.fecha_creacion, c.nombre_categoria, e.nombre_estado,
                    u.edificio, u.piso, u.area_general, u.espacio_especifico,
                    (SELECT MAX(h.fecha_hora) FROM historial_reportes h
                      WHERE h.id_reporte = r.id_reporte AND h.id_estado_nuevo = r.id_estado_actual) AS fecha_envio
             FROM reportes r
             JOIN categorias_problema c ON c.id_categoria = r.id_categoria
             JOIN ubicaciones u ON u.id_ubicacion = r.id_ubicacion
             JOIN estados e ON e.id_estado = r.id_estado_actual
             WHERE r.id_area_asignada = :area AND e.nombre_estado = :estado' . $filtroFolio . '
             ORDER BY fecha_envio ASC, r.id_reporte ASC'
        );
        $consulta->execute($valores);
        $datos = $consulta->fetchAll();
        responder(200, [
            'ok' => true,
            'datos' => $datos,
            'total' => count($datos),
            'mensaje' => $datos ? '' : 'No hay reportes pendientes de validación',
        ]);
    }

    if ($accion === 'detalle') {
        if (!folioValido($folio)) {
            responder(404, ['ok' => false, 'mensaje' => 'Reporte no encontrado.']);
        }
        $consulta = $conn->prepare(
            'SELECT r.id_reporte, r.folio, r.descripcion, r.fecha_creacion, c.nombre_categoria, e.nombre_estado,
                    u.edificio, u.piso, u.area_general, u.espacio_especifico, a.nombre_area
             FROM reportes r
             JOIN categorias_problema c ON c.id_categoria = r.id_categoria
             JOIN estados e ON e.id_estado = r.id_estado_actual
             JOIN ubicaciones u ON u.id_ubicacion = r.id_ubicacion
             LEFT JOIN areas_atencion a ON a.id_area = r.id_area_asignada
             WHERE r.folio = ? AND r.id_area_asignada = ?'
        );
        $consulta->execute([$folio, $idArea]);
        $reporte = $consulta->fetch();
        if (!$reporte) { // otra área o folio inexistente: misma respuesta, sin filtrar información
            responder(404, ['ok' => false, 'mensaje' => 'Reporte no encontrado.']);
        }
        $idReporte = (int) $reporte['id_reporte'];

        $historial = $conn->prepare(
            'SELECT h.id_historial, h.fecha_hora, e.nombre_estado, h.accion_realizada,
                    COALESCE(h.responsable_etiqueta, \'Sistema\') AS responsable
             FROM historial_reportes h JOIN estados e ON e.id_estado = h.id_estado_nuevo
             WHERE h.id_reporte = ? ORDER BY h.fecha_hora, h.id_historial'
        );
        $historial->execute([$idReporte]);
        $lineaTiempo = $historial->fetchAll();

        // Comentarios del trabajo: la última solución enviada a verificación.
        $comentario = null;
        foreach (array_reverse($lineaTiempo) as $evento) {
            if (strncmp((string) $evento['accion_realizada'], PREFIJO_SOLUCION, strlen(PREFIJO_SOLUCION)) === 0) {
                $comentario = [
                    'texto' => substr((string) $evento['accion_realizada'], strlen(PREFIJO_SOLUCION)),
                    'fecha_hora' => $evento['fecha_hora'],
                ];
                break;
            }
        }

        $evidencias = $conn->prepare(
            'SELECT id_evidencia, tipo_evidencia, mime_type, fecha_creacion
             FROM evidencias_reportes WHERE id_reporte = ? ORDER BY id_evidencia'
        );
        $evidencias->execute([$idReporte]);

        unset($reporte['id_reporte']);
        responder(200, ['ok' => true, 'datos' => [
            'reporte' => $reporte,
            'comentario_solucion' => $comentario,
            'evidencias' => $evidencias->fetchAll(),
            'historial' => $lineaTiempo,
        ]]);
    }

    responder(400, ['ok' => false, 'mensaje' => 'Consulta de control de calidad no válida.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    $entrada = $_POST;
}
$folio = trim((string) ($entrada['folio'] ?? ''));
$evaluacion = trim((string) ($entrada['evaluacion'] ?? ''));
$observaciones = trim((string) ($entrada['observaciones'] ?? ''));

if (!folioValido($folio)) {
    responder(400, ['ok' => false, 'mensaje' => 'Folio no válido.']);
}
if (!in_array($evaluacion, ['Aprobado', 'Rechazado'], true)) {
    responder(400, ['ok' => false, 'mensaje' => 'Selecciona una evaluación válida (Aprobado o Rechazado).']);
}
if ($evaluacion === 'Rechazado' && $observaciones === '') {
    responder(400, [
        'ok' => false,
        'tipo' => 'warning',
        'mensaje' => 'Las observaciones son obligatorias cuando el reporte se rechaza.',
    ]);
}
if (mb_strlen($observaciones) > 1000) {
    responder(400, ['ok' => false, 'mensaje' => 'Las observaciones no pueden superar 1,000 caracteres.']);
}

// Solo se puede evaluar un reporte de SU área en EN VERIFICACIÓN.
$consulta = $conn->prepare(
    'SELECT r.id_reporte, r.id_usuario_creador, r.id_estado_actual, e.nombre_estado
     FROM reportes r JOIN estados e ON e.id_estado = r.id_estado_actual
     WHERE r.folio = ? AND r.id_area_asignada = ? LIMIT 1'
);
$consulta->execute([$folio, $idArea]);
$reporte = $consulta->fetch();
if (!$reporte) {
    responder(404, ['ok' => false, 'mensaje' => 'Reporte no encontrado en tu área.']);
}
if ($reporte['nombre_estado'] !== 'En verificación') {
    responder(409, ['ok' => false, 'mensaje' => 'Solo se pueden validar reportes que estén en verificación.']);
}

$aprobado = $evaluacion === 'Aprobado';
$estadoDestino = $aprobado ? 'Cerrado' : 'Pendiente';

try {
    $conn->beginTransaction();
    $idDestino = idEstadoPorNombre($conn, $estadoDestino);

    // La condición sobre el estado evita evaluar dos veces el mismo reporte.
    $actualizar = $conn->prepare(
        'UPDATE reportes SET id_estado_actual = ?, id_jefe_verificador = ?
         WHERE id_reporte = ? AND id_area_asignada = ? AND id_estado_actual = ?'
    );
    $actualizar->execute([
        $idDestino, (int) $jefe['id_usuario'], (int) $reporte['id_reporte'], $idArea, (int) $reporte['id_estado_actual'],
    ]);
    if ($actualizar->rowCount() !== 1) {
        $conn->rollBack();
        responder(409, ['ok' => false, 'mensaje' => 'El reporte ya fue evaluado. Actualiza la lista.']);
    }

    $accionHistorial = $aprobado
        ? 'Reporte cerrado: solución aprobada por el Jefe de departamento' . ($observaciones !== '' ? '. Observaciones: ' . $observaciones : '')
        : PREFIJO_RECHAZO . $observaciones;
    $historial = $conn->prepare(
        'INSERT INTO historial_reportes
            (id_reporte, id_estado_anterior, id_estado_nuevo, id_usuario_accion,
             accion_realizada, responsable_etiqueta)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $historial->execute([
        (int) $reporte['id_reporte'], (int) $reporte['id_estado_actual'], $idDestino,
        (int) $jefe['id_usuario'], $accionHistorial, 'Jefe de departamento',
    ]);

    // Módulo 8.1: avisos de la evaluación.
    if ($aprobado) {
        crearNotificaciones(
            $conn, [(int) $reporte['id_usuario_creador']], (int) $reporte['id_reporte'],
            'reporte cerrado', 'Tu reporte ' . $folio . ' fue atendido y se cerró'
        );
    } else {
        crearNotificaciones(
            $conn, idsUsuariosActivos($conn, 'Personal de atención', $idArea), (int) $reporte['id_reporte'],
            'reporte devuelto', 'El reporte ' . $folio . ' fue devuelto a Pendiente. Observaciones: ' . $observaciones
        );
        crearNotificaciones(
            $conn, [(int) $reporte['id_usuario_creador']], (int) $reporte['id_reporte'],
            'actualizacion reporte', 'El reporte ' . $folio . ' cambió a Pendiente'
        );
    }

    $conn->commit();
    responder(200, [
        'ok' => true,
        'mensaje' => $aprobado ? 'El reporte se cerró exitosamente' : 'El reporte fue rechazado y devuelto al área',
        'estado' => $estadoDestino,
    ]);
} catch (Throwable $error) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Error en control de calidad: ' . $error->getMessage());
    responder(500, ['ok' => false, 'mensaje' => 'No se pudo registrar la evaluación del reporte.']);
}
