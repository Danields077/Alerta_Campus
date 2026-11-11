<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$correo = strtolower(trim($in['correo'] ?? ''));
$password = $in['password'] ?? '';

if ($correo === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Ingresa tu correo y contraseña']);
    exit;
}

$stmt = $conn->prepare(
    'SELECT id_usuario AS id, nombre_completo AS nombre, correo, password_hash AS passwordHash
     FROM usuarios
     WHERE correo = ?
     LIMIT 1'
);
$stmt->bind_param('s', $correo);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario || !password_verify($password, $usuario['passwordHash'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Correo o contraseña incorrectos']);
    exit;
}

session_regenerate_id(true);
$_SESSION['usuario_id'] = $usuario['id'];

echo json_encode(['ok' => true, 'mensaje' => 'Inicio de sesión exitoso',
    'usuario' => ['id' => $usuario['id'], 'nombre' => $usuario['nombre'], 'correo' => $usuario['correo']]]);
