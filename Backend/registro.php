<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$nombre = trim($in['nombre'] ?? '');
$identificador = trim($in['identificador_institucional'] ?? '');
$correo = strtolower(trim($in['correo'] ?? ''));
$password = $in['password'] ?? '';

if ($nombre === '' || $identificador === '' || $correo === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Completa todos los campos']);
    exit;
}

$stmt = $conn->prepare(
    'SELECT id_usuario FROM usuarios
     WHERE correo = ? OR identificador_institucional = ?
     LIMIT 1'
);
$stmt->bind_param('ss', $correo, $identificador);
$stmt->execute();
$yaRegistrado = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($yaRegistrado) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'mensaje' => 'El correo o identificador institucional ya está registrado']);
    exit;
}

$stmt = $conn->prepare("SELECT id_rol FROM roles WHERE nombre_rol = 'Estudiante' LIMIT 1");
$stmt->execute();
$rol = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rol) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No está configurado el rol Estudiante. Ejecuta Script Base.sql']);
    exit;
}

$idRol = (int) $rol['id_rol'];
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    'INSERT INTO usuarios (id_rol, identificador_institucional, nombre_completo, correo, password_hash)
     VALUES (?, ?, ?, ?, ?)'
);
$stmt->bind_param('issss', $idRol, $identificador, $nombre, $correo, $passwordHash);

try {
    $stmt->execute();
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() !== 1062) {
        throw $e;
    }

    http_response_code(409);
    echo json_encode(['ok' => false, 'mensaje' => 'El correo o identificador institucional ya está registrado']);
    exit;
}

$nuevoId = $conn->insert_id;
$stmt->close();
http_response_code(201);
echo json_encode(['ok' => true, 'mensaje' => 'Cuenta creada correctamente',
    'usuario' => ['id' => $nuevoId, 'nombre' => $nombre, 'correo' => $correo]]);
