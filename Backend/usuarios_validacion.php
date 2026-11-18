<?php
require_once __DIR__ . '/conexion.php';

function validarCorreoInstitucional(string $correo): bool
{
    $correo = mb_strtolower(trim($correo), 'UTF-8');
    $dominio = mb_strtolower(INSTITUTIONAL_EMAIL_DOMAIN, 'UTF-8');
    return filter_var($correo, FILTER_VALIDATE_EMAIL) !== false
        && substr($correo, -strlen('@' . $dominio)) === '@' . $dominio;
}

function validarPasswordUsuario(string $password): bool
{
    return mb_strlen($password, 'UTF-8') >= 8
        && preg_match('/\p{L}/u', $password) === 1
        && preg_match('/[0-9]/', $password) === 1;
}

function obtenerIdRol(PDO $conn, string $nombreRol): ?int
{
    $consulta = $conn->prepare('SELECT id_rol FROM roles WHERE nombre_rol = ? LIMIT 1');
    $consulta->execute([$nombreRol]);
    $id = $consulta->fetchColumn();
    return $id === false ? null : (int) $id;
}

function obtenerEntradaJson(): array
{
    $entrada = json_decode(file_get_contents('php://input'), true);
    return is_array($entrada) ? $entrada : $_POST;
}

function esErrorDuplicado(PDOException $error): bool
{
    return $error->getCode() === '23000' && (int) ($error->errorInfo[1] ?? 0) === 1062;
}
