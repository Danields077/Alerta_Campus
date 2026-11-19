<?php
/**
 * Utilidades para validar y guardar fotografías de evidencia (JPG/PNG, máx. 5 MB).
 * Usadas por la evidencia de solución del módulo 3.3.
 */

const EVIDENCIA_TAMANO_MAXIMO = 5 * 1024 * 1024;

/**
 * Indica si el campo de archivo realmente trae un archivo (no es "ninguno").
 */
function hayArchivoSubido(string $campo): bool
{
    return isset($_FILES[$campo]) && is_array($_FILES[$campo])
        && ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

/**
 * Valida y mueve la imagen subida a uploads/evidencias.
 *
 * @return array{ruta:string,mime:string,tamano:int} ruta relativa al proyecto
 * @throws InvalidArgumentException si la imagen no cumple (mensaje apto para el usuario)
 * @throws RuntimeException si no se pudo guardar en disco
 */
function guardarImagenEvidencia(string $campo): array
{
    $archivo = $_FILES[$campo] ?? null;
    if (!is_array($archivo) || !isset($archivo['error'], $archivo['size'], $archivo['tmp_name'], $archivo['name'])
        || !is_int($archivo['error']) || !is_int($archivo['size'])) {
        throw new InvalidArgumentException('El archivo de evidencia no es válido.');
    }
    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE
        || $archivo['size'] > EVIDENCIA_TAMANO_MAXIMO) {
        throw new InvalidArgumentException('La evidencia debe pesar como máximo 5 MB.');
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK || $archivo['size'] < 1 || !is_uploaded_file($archivo['tmp_name'])) {
        throw new InvalidArgumentException('No se pudo recibir la fotografía de evidencia.');
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    $permitidas = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($permitidas[$mime]) || !in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        throw new InvalidArgumentException('Solo se permiten imágenes JPG o PNG válidas.');
    }

    $directorio = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'evidencias';
    if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
        throw new RuntimeException('No fue posible crear el directorio de evidencias.');
    }
    $nombre = bin2hex(random_bytes(24)) . '.' . $permitidas[$mime];
    if (!move_uploaded_file($archivo['tmp_name'], $directorio . DIRECTORY_SEPARATOR . $nombre)) {
        throw new RuntimeException('No se pudo guardar la evidencia.');
    }
    return ['ruta' => 'uploads/evidencias/' . $nombre, 'mime' => $mime, 'tamano' => (int) $archivo['size']];
}

/** Borra una evidencia ya guardada (por ejemplo, si la transacción falló). */
function borrarArchivoEvidencia(?string $ruta): void
{
    if ($ruta !== null && $ruta !== '') {
        @unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ruta));
    }
}
