<?php
require_once __DIR__ . '/usuarios_validacion.php';
header('Content-Type: application/json; charset=utf-8');

function responderRegistro(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderRegistro(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

$entrada = obtenerEntradaJson();
$nombre = trim((string) ($entrada['nombre'] ?? ''));
$identificador = trim((string) ($entrada['identificador_institucional'] ?? ''));
$correo = mb_strtolower(trim((string) ($entrada['correo'] ?? '')), 'UTF-8');
$password = (string) ($entrada['password'] ?? '');
$confirmar = (string) ($entrada['confirmar'] ?? '');
$perfil = trim((string) ($entrada['perfil'] ?? ''));
$perfilesPublicos = ['Estudiante', 'Profesor'];

if ($nombre === '' || $identificador === '' || $correo === '' || $password === '' || $confirmar === '' || $perfil === '') {
    responderRegistro(400, ['ok' => false, 'mensaje' => 'Completa todos los campos.']);
}
if (mb_strlen($nombre) > 150 || mb_strlen($identificador) > 50) {
    responderRegistro(400, ['ok' => false, 'mensaje' => 'El nombre o identificador supera la longitud permitida.']);
}
if (!in_array($perfil, $perfilesPublicos, true)) {
    responderRegistro(403, ['ok' => false, 'mensaje' => 'El perfil solicitado no está permitido para el registro público.']);
}
if (!validarCorreoInstitucional($correo)) {
    responderRegistro(400, ['ok' => false, 'mensaje' => 'Ingresa un correo válido del dominio institucional.']);
}
if (!validarPasswordUsuario($password)) {
    responderRegistro(400, ['ok' => false, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres, letras y números.']);
}
if ($password !== $confirmar) {
    responderRegistro(400, ['ok' => false, 'mensaje' => 'Las contraseñas no coinciden.']);
}

$duplicado = $conn->prepare('SELECT correo, identificador_institucional FROM usuarios WHERE correo = ? OR identificador_institucional = ? LIMIT 1');
$duplicado->execute([$correo, $identificador]);
$existente = $duplicado->fetch();
if ($existente) {
    $mensaje = strcasecmp((string) $existente['correo'], $correo) === 0
        ? 'El correo ya está registrado.'
        : 'El identificador institucional ya está registrado.';
    responderRegistro(409, ['ok' => false, 'mensaje' => $mensaje]);
}

$idRol = obtenerIdRol($conn, $perfil);
if ($idRol === null) {
    error_log('No está configurado el rol ' . $perfil . ' para el registro público.');
    responderRegistro(500, ['ok' => false, 'mensaje' => 'El usuario no se registró exitosamente']);
}

try {
    $insertar = $conn->prepare(
        'INSERT INTO usuarios (id_rol, identificador_institucional, nombre_completo, correo, password_hash)
         VALUES (?, ?, ?, ?, ?)'
    );
    $insertar->execute([$idRol, $identificador, $nombre, $correo, password_hash($password, PASSWORD_DEFAULT)]);
} catch (PDOException $error) {
    if (esErrorDuplicado($error)) {
        responderRegistro(409, ['ok' => false, 'mensaje' => 'El correo o identificador institucional ya está registrado.']);
    }
    error_log('Error al registrar usuario: ' . $error->getMessage());
    responderRegistro(500, ['ok' => false, 'mensaje' => 'El usuario no se registró exitosamente']);
}

http_response_code(201);
echo json_encode(['ok' => true, 'mensaje' => 'El usuario se registró exitosamente'],
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
