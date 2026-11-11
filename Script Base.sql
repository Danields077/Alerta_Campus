ALERTA CAMPUS SCRIPT BASE

-- --------------------------------------------------------
-- Creación de la base de datos
-- --------------------------------------------------------
CREATE DATABASE IF NOT EXISTS Alerta_Campus
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE Alerta_Campus;

-- --------------------------------------------------------
-- 1. TABLAS DE AUTENTICACIÓN Y USUARIOS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL COMMENT 'Estudiante, Profesor, Personal de atención, Jefe de departamento, Administrador'
) ENGINE=InnoDB;


-- --------------------------------------------------------
-- 2. CATÁLOGOS ESTÁTICOS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS categorias_problema (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL COMMENT 'Infraestructura, Electricidad, Red, Limpieza, Seguridad'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS areas_atencion (
    id_area INT AUTO_INCREMENT PRIMARY KEY,
    nombre_area VARCHAR(100) NOT NULL COMMENT 'Sistemas, Intendencia, Recursos Materiales, Protección Civil, Eléctrico'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ubicaciones (
    id_ubicacion INT AUTO_INCREMENT PRIMARY KEY,
    edificio VARCHAR(100) NOT NULL,
    piso VARCHAR(50) NOT NULL,
    area_general VARCHAR(100) NOT NULL,
    espacio_especifico VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS estados (
    id_estado INT AUTO_INCREMENT PRIMARY KEY,
    nombre_estado VARCHAR(50) NOT NULL COMMENT 'Pendiente, Asignado, En atención, Solucionado, En verificación, Liberado/Cerrado'
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 4. TABLAS OPERATIVAS (Flujo de Reportes)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS reportes (
    id_reporte INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario_creador INT NOT NULL,
    id_categoria INT NOT NULL,
    id_ubicacion INT NOT NULL,
    id_estado_actual INT NOT NULL,
    id_area_asignada INT NOT NULL,
    id_jefe_verificador INT NOT NULL,
    prioridad VARCHAR(20) NOT NULL COMMENT 'Baja, Media, Alta, Crítica',
    descripcion TEXT NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reporte_usuario FOREIGN KEY (id_usuario_creador) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reporte_categoria FOREIGN KEY (id_categoria) REFERENCES categorias_problema(id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reporte_ubicacion FOREIGN KEY (id_ubicacion) REFERENCES ubicaciones(id_ubicacion) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reporte_estado FOREIGN KEY (id_estado_actual) REFERENCES estados(id_estado) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reporte_area FOREIGN KEY (id_area_asignada) REFERENCES areas_atencion(id_area) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reporte_jefe FOREIGN KEY (id_jefe_verificador) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historial_reportes (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,
    id_reporte INT NOT NULL,
    id_estado_anterior INT NULL COMMENT 'Puede ser nulo en la creación del reporte',
    id_estado_nuevo INT NOT NULL,
    id_usuario_accion INT NOT NULL,
    accion_realizada VARCHAR(255) NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_historial_reporte FOREIGN KEY (id_reporte) REFERENCES reportes(id_reporte) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_historial_estado_ant FOREIGN KEY (id_estado_anterior) REFERENCES estados(id_estado) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_historial_estado_nuevo FOREIGN KEY (id_estado_nuevo) REFERENCES estados(id_estado) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_historial_usuario FOREIGN KEY (id_usuario_accion) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notificaciones (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario_destino INT NOT NULL,
    id_reporte INT NOT NULL,
    tipo_evento VARCHAR(100) NOT NULL,
    mensaje TEXT NOT NULL,
    leida TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0: No leída, 1: Leída',
    fecha_generacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_usuario FOREIGN KEY (id_usuario_destino) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notif_reporte FOREIGN KEY (id_reporte) REFERENCES reportes(id_reporte) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;