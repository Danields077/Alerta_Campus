<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
session_start();

$usuario = null;
if (isset($_SESSION['usuario_id'], $_SESSION['ultima_actividad'])
    && time() - (int) $_SESSION['ultima_actividad'] <= 1800) {
    $usuarioId = (int) $_SESSION['usuario_id'];
    $stmt = $conn->prepare(
        'SELECT u.id_usuario AS id, u.nombre_completo AS nombre, u.correo,
                u.estado, u.id_area, r.nombre_rol AS rol
         FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
         WHERE u.id_usuario = ? LIMIT 1'
    );
    $stmt->execute([$usuarioId]);
    $usuario = $stmt->fetch();
}

if (!$usuario || strcasecmp((string) $usuario['estado'], 'Activo') !== 0) {
    $_SESSION = [];
    session_destroy();
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'La sesión expiró o no está activa']);
    exit;
}

$_SESSION['ultima_actividad'] = time();
echo json_encode(['ok' => true,
    'usuario' => ['id' => $usuario['id'], 'nombre' => $usuario['nombre'], 'correo' => $usuario['correo'],
        'rol' => $usuario['rol'], 'id_area' => $usuario['id_area']]]);
