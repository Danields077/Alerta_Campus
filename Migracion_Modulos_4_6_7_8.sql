-- Migración de los módulos 4, 6, 7 y 8 para MySQL/MariaDB de XAMPP.
-- Ejecutar UNA sola vez, DESPUÉS de Script Base.sql y Migracion_Modulo2.sql.
-- Hacer respaldo antes: ALTER TABLE provoca commits implícitos.
USE Alerta_Campus;
SET NAMES utf8mb4;

-- Antes de correrla, estas dos consultas deben devolver 0 filas (si devuelven
-- algo, hay duplicados que corregir a mano o el paso 2/3 fallará):
--   SELECT nombre_categoria, COUNT(*) FROM categorias_problema GROUP BY nombre_categoria HAVING COUNT(*) > 1;
--   SELECT edificio, piso, area_general, espacio_especifico, COUNT(*) FROM ubicaciones
--     GROUP BY edificio, piso, area_general, espacio_especifico HAVING COUNT(*) > 1;

-- 1) El historial guarda comentarios de solución y observaciones del Jefe
--    (hasta 1,000 caracteres). VARCHAR(255) las truncaría o daría error.
ALTER TABLE historial_reportes MODIFY accion_realizada TEXT NOT NULL;

-- 2) Módulo 6: el nombre de una categoría no puede repetirse.
ALTER TABLE categorias_problema
    ADD UNIQUE KEY uq_categoria_nombre (nombre_categoria);

-- 3) Módulo 7: la combinación edificio + piso + área + espacio no puede repetirse.
ALTER TABLE ubicaciones
    ADD UNIQUE KEY uq_ubicacion_completa (edificio, piso, area_general, espacio_especifico);

-- 4) Módulo 8: consulta rápida de notificaciones por usuario y estado de lectura.
CREATE INDEX idx_notif_usuario_leida
    ON notificaciones (id_usuario_destino, leida, fecha_generacion);
