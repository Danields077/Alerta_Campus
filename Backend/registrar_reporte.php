<?php
require_once __DIR__ . '/api_comun.php';
$usuario = requerirSesion(['Estudiante', 'Profesor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

$categoria = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT);
$ubicacion = filter_input(INPUT_POST, 'id_ubicacion', FILTER_VALIDATE_INT);
$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
if (!$categoria || !$ubicacion || $descripcion === '') {
    responder(400, ['ok' => false, 'tipo' => 'warning', 'mensaje' => 'Categoría, ubicación y descripción son obligatorias.']);
}
if (mb_strlen($descripcion) > 5000) {
    responder(400, ['ok' => false, 'tipo' => 'warning', 'mensaje' => 'La descripción no puede superar 5,000 caracteres.']);
}

$categoriaQuery = $conn->prepare('SELECT id_area_responsable FROM categorias_problema WHERE id_categoria = ?');
$categoriaQuery->execute([$categoria]);
$categoriaRow = $categoriaQuery->fetch();
$ubicacionQuery = $conn->prepare('SELECT id_ubicacion FROM ubicaciones WHERE id_ubicacion = ?');
$ubicacionQuery->execute([$ubicacion]);
if (!$categoriaRow || !$ubicacionQuery->fetch()) {
    responder(400, ['ok' => false, 'tipo' => 'warning', 'mensaje' => 'La categoría o ubicación seleccionada no es válida.']);
}

$rutaGuardada = null;
$mimeGuardado = null;
$tamanoGuardado = null;
if (isset($_FILES['evidencia'])) {
    $archivo = $_FILES['evidencia'];
    if (!is_array($archivo) || !isset($archivo['error'])) {
        responder(400, ['ok' => false, 'tipo' => 'warning', 'mensaje' => 'El archivo de evidencia no es válido.']);
    }
    if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
        $archivo = null;
    }
}
if (isset($archivo) && $archivo !== null) {
    if (!isset($archivo['error'], $archivo['size'], $archivo['tmp_name'], $archivo['name'])
        || !is_int($archivo['error']) || !is_int($archivo['size'])
        || $archivo['error'] !== UPLOAD_ERR_OK || $archivo['size'] < 1
        || $archivo['size'] > 5 * 1024 * 1024 || !is_uploaded_file($archivo['tmp_name'])) {
        responder(400, ['ok' => false, 'tipo' => 'warning', 'mensaje' => 'La evidencia debe pesar como máximo 5 MB.']);
    }
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    $extensionesPermitidas = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($extensionesPermitidas[$mime]) || !in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        responder(400, ['ok' => false, 'tipo' => 'warning', 'mensaje' => 'Solo se permiten imágenes JPG o PNG válidas.']);
    }
    $directorio = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'evidencias';
    if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
        error_log('No fue posible crear el directorio de evidencias de AlertaCampus.');
        responder(500, ['ok' => false, 'tipo' => 'error', 'mensaje' => 'No se pudo guardar la evidencia.']);
    }
    $nombre = bin2hex(random_bytes(24)) . '.' . $extensionesPermitidas[$mime];
    if (!move_uploaded_file($archivo['tmp_name'], $directorio . DIRECTORY_SEPARATOR . $nombre)) {
        responder(500, ['ok' => false, 'tipo' => 'error', 'mensaje' => 'No se pudo guardar la evidencia.']);
    }
    $rutaGuardada = 'uploads/evidencias/' . $nombre;
    $mimeGuardado = $mime;
    $tamanoGuardado = (int) $archivo['size'];
}

try {
    $conn->beginTransaction();
    $estadoQuery = $conn->prepare("SELECT id_estado FROM estados WHERE nombre_estado = 'Pendiente' LIMIT 1");
    $estadoQuery->execute();
    $idPendiente = $estadoQuery->fetchColumn();
    if (!$idPendiente) {
        throw new RuntimeException('Falta el estado Pendiente en el catálogo.');
    }

    $insertar = $conn->prepare(
        'INSERT INTO reportes
            (id_usuario_creador, id_categoria, id_ubicacion, id_estado_actual,
             id_area_asignada, id_jefe_verificador, prioridad, descripcion)
         VALUES (?, ?, ?, ?, ?, NULL, \'Media\', ?)'
    );
    $area = $categoriaRow['id_area_responsable'] === null ? null : (int) $categoriaRow['id_area_responsable'];
    $insertar->bindValue(1, (int) $usuario['id_usuario'], PDO::PARAM_INT);
    $insertar->bindValue(2, (int) $categoria, PDO::PARAM_INT);
    $insertar->bindValue(3, (int) $ubicacion, PDO::PARAM_INT);
    $insertar->bindValue(4, (int) $idPendiente, PDO::PARAM_INT);
    $insertar->bindValue(5, $area, $area === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $insertar->bindValue(6, $descripcion, PDO::PARAM_STR);
    $insertar->execute();
    $idReporte = (int) $conn->lastInsertId();
    $anioQuery = $conn->prepare('SELECT YEAR(fecha_creacion) FROM reportes WHERE id_reporte = ?');
    $anioQuery->execute([$idReporte]);
    $anio = (int) $anioQuery->fetchColumn();
    $folio = sprintf('ALC-%04d-%06d', $anio, $idReporte);
    $actualizarFolio = $conn->prepare('UPDATE reportes SET folio = ? WHERE id_reporte = ?');
    $actualizarFolio->execute([$folio, $idReporte]);

    $responsable = $area === null ? 'Sistema · Sin área asignada' : 'Sistema · Área asignada';
    $historial = $conn->prepare(
        'INSERT INTO historial_reportes
            (id_reporte, id_estado_anterior, id_estado_nuevo, id_usuario_accion,
             accion_realizada, responsable_etiqueta)
         VALUES (?, NULL, ?, ?, \'Reporte creado\', ?)'
    );
    $historial->execute([$idReporte, $idPendiente, (int) $usuario['id_usuario'], 'Reportante']);
    if ($area !== null) {
        $historialAsignacion = $conn->prepare(
            'INSERT INTO historial_reportes
                (id_reporte, id_estado_anterior, id_estado_nuevo, id_usuario_accion,
                 accion_realizada, responsable_etiqueta)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $historialAsignacion->execute([
            $idReporte, $idPendiente, $idPendiente, (int) $usuario['id_usuario'],
            'Área asignada automáticamente', $responsable,
        ]);
    }
    if ($rutaGuardada !== null) {
        $evidencia = $conn->prepare(
            'INSERT INTO evidencias_reportes (id_reporte, tipo_evidencia, ruta, mime_type, tamano_bytes)
             VALUES (?, \'reporte\', ?, ?, ?)'
        );
        $evidencia->execute([$idReporte, $rutaGuardada, $mimeGuardado, $tamanoGuardado]);
    }

    if ($area !== null) {
        $destinatarios = $conn->prepare(
            'SELECT id_usuario FROM usuarios WHERE id_area = ? AND estado = \'Activo\'
             AND id_rol = (SELECT id_rol FROM roles WHERE nombre_rol = \'Personal de atención\' LIMIT 1)'
        );
        $destinatarios->execute([$area]);
        $mensaje = 'Nuevo reporte asignado: ' . $folio;
        $tipo = 'nuevo reporte asignado';
    } else {
        $destinatarios = $conn->prepare(
            'SELECT u.id_usuario FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.estado = \'Activo\' AND r.nombre_rol = \'Administrador\''
        );
        $destinatarios->execute();
        $mensaje = 'Reporte sin área asignada: ' . $folio;
        $tipo = 'reporte sin área asignada';
    }
    $notificacion = $conn->prepare(
        'INSERT INTO notificaciones (id_usuario_destino, id_reporte, tipo_evento, mensaje)
         VALUES (?, ?, ?, ?)'
    );
    foreach ($destinatarios->fetchAll(PDO::FETCH_COLUMN) as $destinatario) {
        $notificacion->execute([(int) $destinatario, $idReporte, $tipo, $mensaje]);
    }
    $conn->commit();
    responder(201, ['ok' => true, 'tipo' => 'success', 'mensaje' => 'Reporte registrado exitosamente con folio ' . $folio, 'folio' => $folio]);
} catch (PDOException $error) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    if ($rutaGuardada !== null) {
        @unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rutaGuardada));
    }
    error_log('Error al registrar reporte: ' . $error->getMessage());
    responder(500, ['ok' => false, 'tipo' => 'error', 'mensaje' => 'No se pudo registrar el reporte. Intenta nuevamente.']);
} catch (RuntimeException $error) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    if ($rutaGuardada !== null) {
        @unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rutaGuardada));
    }
    error_log('Error de configuración al registrar reporte: ' . $error->getMessage());
    responder(500, ['ok' => false, 'tipo' => 'error', 'mensaje' => 'No se pudo completar el registro. Contacta al administrador.']);
}
