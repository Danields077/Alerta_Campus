<?php
require_once __DIR__ . '/api_comun.php';
$usuario = requerirSesion(['Estudiante', 'Profesor']);
$consulta = $conn->prepare(
    'SELECT r.folio, e.nombre_estado
     FROM reportes r JOIN estados e ON e.id_estado = r.id_estado_actual
     WHERE r.id_usuario_creador = ? ORDER BY r.fecha_creacion DESC'
);
$consulta->execute([(int) $usuario['id_usuario']]);
responder(200, ['ok' => true, 'datos' => $consulta->fetchAll()]);
