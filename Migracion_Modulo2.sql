-- Migración de Módulo 2 para MySQL/MariaDB de XAMPP.
-- Ejecutar una sola vez en la BD existente cuya estructura corresponde a
-- Script Base.sql. No volver a importar el script base sobre datos existentes.
-- Hacer respaldo antes de aplicar: ALTER TABLE provoca commits implícitos.
USE Alerta_Campus;
SET NAMES utf8mb4;

-- Requisito: categorías, áreas, estados, reportes, historial_reportes,
-- notificaciones y usuarios deben existir. La tabla usuarios se agrega al
-- Script Base.sql para que la instalación limpia respete ese orden.

-- 1) Identificadores oficiales: 1 Pendiente, 2 En atención,
-- 3 En verificación, 4 Cerrado. Los estados antiguos se consolidan sin
-- perder el historial ni cambiar el reporte de estado equivalente.
UPDATE reportes SET id_estado_actual = 2 WHERE id_estado_actual = 3;
UPDATE reportes SET id_estado_actual = 3 WHERE id_estado_actual = 4;
UPDATE reportes SET id_estado_actual = 4 WHERE id_estado_actual = 5;
UPDATE historial_reportes SET id_estado_anterior = 2 WHERE id_estado_anterior = 3;
UPDATE historial_reportes SET id_estado_nuevo = 2 WHERE id_estado_nuevo = 3;
UPDATE historial_reportes SET id_estado_anterior = 3 WHERE id_estado_anterior = 4;
UPDATE historial_reportes SET id_estado_nuevo = 3 WHERE id_estado_nuevo = 4;
UPDATE historial_reportes SET id_estado_anterior = 4 WHERE id_estado_anterior = 5;
UPDATE historial_reportes SET id_estado_nuevo = 4 WHERE id_estado_nuevo = 5;
DELETE FROM estados WHERE id_estado = 5;
INSERT INTO estados (id_estado, nombre_estado) VALUES
    (1, 'Pendiente'), (2, 'En atención'), (3, 'En verificación'), (4, 'Cerrado')
ON DUPLICATE KEY UPDATE nombre_estado = VALUES(nombre_estado);
ALTER TABLE estados AUTO_INCREMENT = 5;

-- Si una instalación anterior tiene estados con otros IDs, revisar el catálogo
-- antes de ejecutar: el script base define los IDs 1 a 5 indicados arriba.

-- 2) Área y jefe pueden no estar definidos en el registro inicial.
ALTER TABLE reportes
    DROP FOREIGN KEY fk_reporte_area,
    DROP FOREIGN KEY fk_reporte_jefe;
ALTER TABLE reportes
    MODIFY id_area_asignada INT NULL,
    MODIFY id_jefe_verificador INT NULL,
    ADD CONSTRAINT fk_reporte_area FOREIGN KEY (id_area_asignada)
        REFERENCES areas_atencion(id_area) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT fk_reporte_jefe FOREIGN KEY (id_jefe_verificador)
        REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE;

-- 3) Mapeo automático. Red se asigna a Sistemas (Laboratorio de Cómputo);
-- no se usa el área Eléctrico, ausente del mapeo funcional.
ALTER TABLE categorias_problema ADD COLUMN id_area_responsable INT NULL;
ALTER TABLE categorias_problema
    ADD CONSTRAINT fk_categoria_area FOREIGN KEY (id_area_responsable)
        REFERENCES areas_atencion(id_area) ON DELETE SET NULL ON UPDATE CASCADE;
UPDATE categorias_problema c
JOIN areas_atencion a ON a.nombre_area = CASE c.nombre_categoria
    WHEN 'Infraestructura' THEN 'Recursos Materiales'
    WHEN 'Electricidad' THEN 'Recursos Materiales'
    WHEN 'Red' THEN 'Sistemas'
    WHEN 'Limpieza' THEN 'Intendencia'
    WHEN 'Seguridad' THEN 'Protección Civil'
    ELSE ''
END
SET c.id_area_responsable = a.id_area;

-- 4) Prioridad tiene valor predeterminado porque el alta no la solicita.
ALTER TABLE reportes MODIFY prioridad VARCHAR(20) NOT NULL DEFAULT 'Media';

-- 5) Folio reproducible y único basado en la llave del reporte.
ALTER TABLE reportes ADD COLUMN folio VARCHAR(32) NULL;
UPDATE reportes
SET folio = CONCAT('ALC-', YEAR(fecha_creacion), '-', LPAD(id_reporte, 6, '0'))
WHERE folio IS NULL;
ALTER TABLE reportes MODIFY folio VARCHAR(32) NOT NULL,
    ADD UNIQUE KEY uq_reportes_folio (folio);

-- 6) Evidencias extensibles: tipo=reporte ahora y tipo=solucion posteriormente.
CREATE TABLE evidencias_reportes (
    id_evidencia INT AUTO_INCREMENT PRIMARY KEY,
    id_reporte INT NOT NULL,
    tipo_evidencia ENUM('reporte', 'solucion') NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    tamano_bytes INT UNSIGNED NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_evidencia_reporte FOREIGN KEY (id_reporte)
        REFERENCES reportes(id_reporte) ON DELETE CASCADE ON UPDATE CASCADE,
    KEY idx_evidencia_reporte_tipo (id_reporte, tipo_evidencia)
) ENGINE=InnoDB;

-- Responsable visible sin exponer nombres personales de otros usuarios.
ALTER TABLE historial_reportes ADD COLUMN responsable_etiqueta VARCHAR(120) NULL;

-- 7) Estado y área de usuarios para sesión/RBAC y alcance del Jefe.
ALTER TABLE usuarios ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'Activo';
ALTER TABLE usuarios ADD COLUMN id_area INT NULL;
ALTER TABLE usuarios ADD CONSTRAINT fk_usuario_area FOREIGN KEY (id_area)
    REFERENCES areas_atencion(id_area) ON DELETE SET NULL ON UPDATE CASCADE;

-- Índices para propiedad, filtros recientes y cascadas de ubicación.
CREATE INDEX idx_reportes_usuario_fecha ON reportes (id_usuario_creador, fecha_creacion);
CREATE INDEX idx_reportes_area_fecha ON reportes (id_area_asignada, fecha_creacion);
CREATE INDEX idx_ubicaciones_cascada ON ubicaciones (edificio, piso, area_general, espacio_especifico);
CREATE INDEX idx_historial_reporte_fecha ON historial_reportes (id_reporte, fecha_hora);
