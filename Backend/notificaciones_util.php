<?php
/**
 * Utilidades compartidas para generar notificaciones internas (módulo 8.1).
 * Las funciones NO abren transacción: se llaman dentro de la del módulo que
 * cambia el estado del reporte, para que reporte y notificación sean atómicos.
 */
require_once __DIR__ . '/conexion.php';

/**
 * IDs de usuarios ACTIVOS con un perfil dado, opcionalmente de un área concreta.
 *
 * @return int[]
 */
function idsUsuariosActivos(PDO $conn, string $nombreRol, ?int $idArea = null): array
{
    $sql = 'SELECT u.id_usuario FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
            WHERE u.estado = \'Activo\' AND r.nombre_rol = ?';
    $valores = [$nombreRol];
    if ($idArea !== null) {
        $sql .= ' AND u.id_area = ?';
        $valores[] = $idArea;
    }
    $consulta = $conn->prepare($sql);
    $consulta->execute($valores);
    return array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Inserta una notificación para cada destinatario (sin repetir usuarios).
 *
 * @param int[] $idsDestino
 */
function crearNotificaciones(PDO $conn, array $idsDestino, int $idReporte, string $tipoEvento, string $mensaje): void
{
    $insertar = $conn->prepare(
        'INSERT INTO notificaciones (id_usuario_destino, id_reporte, tipo_evento, mensaje)
         VALUES (?, ?, ?, ?)'
    );
    foreach (array_unique(array_map('intval', $idsDestino)) as $idDestino) {
        $insertar->execute([$idDestino, $idReporte, $tipoEvento, $mensaje]);
    }
}
