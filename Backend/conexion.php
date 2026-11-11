<?php
/**
 * Conexión a Base de Datos - GestionGym
 * 
 * Archivo responsable de establecer la conexión MySQL
 */

// Incluir archivo de configuración
require_once __DIR__ . '/config.php';

// Crear conexión con parámetros correctos
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Verificar si hay error de conexión
if ($conn->connect_error) {
    // Lanzar excepción en lugar de die() para permitir manejo
    throw new Exception("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Establecer charset UTF-8 para caracteres especiales
$conn->set_charset(DB_CHARSET);

// Verificar que el charset se estableció correctamente
if ($conn->error) {
    throw new Exception("Error al establecer charset: " . $conn->error);
}

