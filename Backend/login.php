<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
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
    'SELECT u.id_usuario AS id, u.nombre_completo AS nombre, u.correo,
            u.password_hash AS passwordHash, u.estado, u.id_area, r.nombre_rol
     FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
     WHERE u.correo = ? LIMIT 1'
);
$stmt->execute([$correo]);
$usuario = $stmt->fetch();

if (!$usuario || strcasecmp((string) $usuario['estado'], 'Activo') !== 0
    || !password_verify($password, $usuario['passwordHash'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Correo o contraseña incorrectos']);
    exit;
}

session_regenerate_id(true);
$_SESSION['usuario_id'] = $usuario['id'];
$_SESSION['ultima_actividad'] = time();
$_SESSION['nombre_rol'] = $usuario['nombre_rol'];
$_SESSION['id_area'] = $usuario['id_area'] === null ? null : (int) $usuario['id_area'];

echo json_encode(['ok' => true, 'mensaje' => 'Inicio de sesión exitoso',
    'usuario' => ['id' => $usuario['id'], 'nombre' => $usuario['nombre'], 'correo' => $usuario['correo'],
        'rol' => $usuario['nombre_rol']]]);
