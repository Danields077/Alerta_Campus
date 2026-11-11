<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

$usuario = null;
if (isset($_SESSION['usuario_id'])) {
    $usuarioId = (int) $_SESSION['usuario_id'];
    $stmt = $conn->prepare(
        'SELECT id_usuario AS id, nombre_completo AS nombre, correo
         FROM usuarios
         WHERE id_usuario = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$usuario) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No has iniciado sesión']);
    exit;
}

echo json_encode(['ok' => true,
    'usuario' => ['id' => $usuario['id'], 'nombre' => $usuario['nombre'], 'correo' => $usuario['correo']]]);
