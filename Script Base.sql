-- ALERTA CAMPUS SCRIPT BASE

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

-- La tabla de usuarios es requisito previo de autenticación y de las claves foráneas.
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_rol INT NOT NULL,
    identificador_institucional VARCHAR(50) NOT NULL UNIQUE,
    nombre_completo VARCHAR(150) NOT NULL,
    correo VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
        ON DELETE RESTRICT ON UPDATE CASCADE
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



USE Alerta_Campus;
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Inserciones Iniciales
-- --------------------------------------------------------

START TRANSACTION;

-- 1. roles
INSERT INTO roles (id_rol, nombre_rol) VALUES
(1, 'Estudiante'),
(2, 'Profesor'),
(3, 'Personal de atención'),
(4, 'Jefe de departamento'),
(5, 'Administrador');

-- 2. categorias_problema
INSERT INTO categorias_problema (id_categoria, nombre_categoria) VALUES
(1, 'Infraestructura'),
(2, 'Electricidad'),
(3, 'Red'),
(4, 'Limpieza'),
(5, 'Seguridad');

-- 3. areas_atencion
INSERT INTO areas_atencion (id_area, nombre_area) VALUES
(1, 'Sistemas'),
(2, 'Intendencia'),
(3, 'Recursos Materiales'),
(4, 'Protección Civil'),
(5, 'Eléctrico');

-- 4. estados
INSERT INTO estados (id_estado, nombre_estado) VALUES
(1, 'Pendiente'),
(2, 'Asignado'),
(3, 'En atención'),
(4, 'Solucionado'),
(5, 'Liberado/Cerrado');

-- 5. ubicaciones
INSERT INTO ubicaciones (id_ubicacion, edificio, piso, area_general, espacio_especifico) VALUES
(1, 'Edificio 1', 'Piso 1', 'Baños', 'Baño de mujeres'),
(2, 'Edificio 1', 'Piso 1', 'Baños', 'Baño de hombres'),
(3, 'Edificio 1', 'Piso 1', 'Aulas', 'Aula 1'),
(4, 'Edificio 1', 'Piso 1', 'Aulas', 'Aula 2'),
(5, 'Edificio 1', 'Piso 1', 'Aulas', 'Aula 3'),
(6, 'Edificio 1', 'Piso 1', 'Aulas', 'Aula 4'),
(7, 'Edificio 1', 'Piso 1', 'Aulas', 'Aula 5'),
(8, 'Edificio 1', 'Piso 1', 'Aulas', 'Aula 6'),
(9, 'Edificio 1', 'Piso 1', 'Pasillos', 'Entrada'),
(10, 'Edificio 1', 'Piso 1', 'Pasillos', 'Escaleras'),
(11, 'Edificio 1', 'Piso 1', 'Pasillos', 'Pasillo'),
(12, 'Edificio 1', 'Piso 1', 'Administración', 'Oficina administrativa'),
(13, 'Edificio 1', 'Piso 2', 'Baños', 'Baño de mujeres'),
(14, 'Edificio 1', 'Piso 2', 'Baños', 'Baño de hombres'),
(15, 'Edificio 1', 'Piso 2', 'Aulas', 'Aula 7'),
(16, 'Edificio 1', 'Piso 2', 'Aulas', 'Aula 8'),
(17, 'Edificio 1', 'Piso 2', 'Aulas', 'Aula 9'),
(18, 'Edificio 1', 'Piso 2', 'Aulas', 'Aula 10'),
(19, 'Edificio 1', 'Piso 2', 'Aulas', 'Aula 11'),
(20, 'Edificio 1', 'Piso 2', 'Aulas', 'Aula 12'),
(21, 'Edificio 1', 'Piso 2', 'Pasillos', 'Escaleras'),
(22, 'Edificio 1', 'Piso 2', 'Pasillos', 'Pasillo'),
(23, 'Edificio 1', 'Piso 2', 'Administración', 'Sala de maestros'),
(24, 'Edificio 2', 'Piso 1', 'Baños', 'Baño de mujeres'),
(25, 'Edificio 2', 'Piso 1', 'Baños', 'Baño de hombres'),
(26, 'Edificio 2', 'Piso 1', 'Aulas', 'Aula 1'),
(27, 'Edificio 2', 'Piso 1', 'Aulas', 'Aula 2'),
(28, 'Edificio 2', 'Piso 1', 'Aulas', 'Aula 3'),
(29, 'Edificio 2', 'Piso 1', 'Aulas', 'Aula 4'),
(30, 'Edificio 2', 'Piso 1', 'Aulas', 'Aula 5'),
(31, 'Edificio 2', 'Piso 1', 'Aulas', 'Aula 6'),
(32, 'Edificio 2', 'Piso 1', 'Pasillos', 'Entrada'),
(33, 'Edificio 2', 'Piso 1', 'Pasillos', 'Escaleras'),
(34, 'Edificio 2', 'Piso 1', 'Pasillos', 'Pasillo'),
(35, 'Edificio 2', 'Piso 1', 'Administración', 'Oficina administrativa'),
(36, 'Edificio 2', 'Piso 2', 'Baños', 'Baño de mujeres'),
(37, 'Edificio 2', 'Piso 2', 'Baños', 'Baño de hombres'),
(38, 'Edificio 2', 'Piso 2', 'Aulas', 'Aula 7'),
(39, 'Edificio 2', 'Piso 2', 'Aulas', 'Aula 8'),
(40, 'Edificio 2', 'Piso 2', 'Aulas', 'Aula 9'),
(41, 'Edificio 2', 'Piso 2', 'Aulas', 'Aula 10'),
(42, 'Edificio 2', 'Piso 2', 'Aulas', 'Aula 11'),
(43, 'Edificio 2', 'Piso 2', 'Aulas', 'Aula 12'),
(44, 'Edificio 2', 'Piso 2', 'Pasillos', 'Escaleras'),
(45, 'Edificio 2', 'Piso 2', 'Pasillos', 'Pasillo'),
(46, 'Edificio 2', 'Piso 2', 'Administración', 'Sala de maestros'),
(47, 'Edificio 3', 'Piso 1', 'Baños', 'Baño de mujeres'),
(48, 'Edificio 3', 'Piso 1', 'Baños', 'Baño de hombres'),
(49, 'Edificio 3', 'Piso 1', 'Aulas', 'Aula 1'),
(50, 'Edificio 3', 'Piso 1', 'Aulas', 'Aula 2'),
(51, 'Edificio 3', 'Piso 1', 'Aulas', 'Aula 3'),
(52, 'Edificio 3', 'Piso 1', 'Aulas', 'Aula 4'),
(53, 'Edificio 3', 'Piso 1', 'Aulas', 'Aula 5'),
(54, 'Edificio 3', 'Piso 1', 'Aulas', 'Aula 6'),
(55, 'Edificio 3', 'Piso 1', 'Pasillos', 'Entrada'),
(56, 'Edificio 3', 'Piso 1', 'Pasillos', 'Escaleras'),
(57, 'Edificio 3', 'Piso 1', 'Pasillos', 'Pasillo'),
(58, 'Edificio 3', 'Piso 1', 'Administración', 'Oficina administrativa'),
(59, 'Edificio 3', 'Piso 2', 'Baños', 'Baño de mujeres'),
(60, 'Edificio 3', 'Piso 2', 'Baños', 'Baño de hombres'),
(61, 'Edificio 3', 'Piso 2', 'Aulas', 'Aula 7'),
(62, 'Edificio 3', 'Piso 2', 'Aulas', 'Aula 8'),
(63, 'Edificio 3', 'Piso 2', 'Aulas', 'Aula 9'),
(64, 'Edificio 3', 'Piso 2', 'Aulas', 'Aula 10'),
(65, 'Edificio 3', 'Piso 2', 'Aulas', 'Aula 11'),
(66, 'Edificio 3', 'Piso 2', 'Aulas', 'Aula 12'),
(67, 'Edificio 3', 'Piso 2', 'Pasillos', 'Escaleras'),
(68, 'Edificio 3', 'Piso 2', 'Pasillos', 'Pasillo'),
(69, 'Edificio 3', 'Piso 2', 'Administración', 'Sala de maestros'),
(70, 'Edificio 4', 'Piso 1', 'Baños', 'Baño de mujeres'),
(71, 'Edificio 4', 'Piso 1', 'Baños', 'Baño de hombres'),
(72, 'Edificio 4', 'Piso 1', 'Aulas', 'Aula 1'),
(73, 'Edificio 4', 'Piso 1', 'Aulas', 'Aula 2'),
(74, 'Edificio 4', 'Piso 1', 'Aulas', 'Aula 3'),
(75, 'Edificio 4', 'Piso 1', 'Aulas', 'Aula 4'),
(76, 'Edificio 4', 'Piso 1', 'Aulas', 'Aula 5'),
(77, 'Edificio 4', 'Piso 1', 'Aulas', 'Aula 6'),
(78, 'Edificio 4', 'Piso 1', 'Pasillos', 'Entrada'),
(79, 'Edificio 4', 'Piso 1', 'Pasillos', 'Escaleras'),
(80, 'Edificio 4', 'Piso 1', 'Pasillos', 'Pasillo'),
(81, 'Edificio 4', 'Piso 1', 'Administración', 'Oficina administrativa'),
(82, 'Edificio 4', 'Piso 2', 'Baños', 'Baño de mujeres'),
(83, 'Edificio 4', 'Piso 2', 'Baños', 'Baño de hombres'),
(84, 'Edificio 4', 'Piso 2', 'Aulas', 'Aula 7'),
(85, 'Edificio 4', 'Piso 2', 'Aulas', 'Aula 8'),
(86, 'Edificio 4', 'Piso 2', 'Aulas', 'Aula 9'),
(87, 'Edificio 4', 'Piso 2', 'Aulas', 'Aula 10'),
(88, 'Edificio 4', 'Piso 2', 'Aulas', 'Aula 11'),
(89, 'Edificio 4', 'Piso 2', 'Aulas', 'Aula 12'),
(90, 'Edificio 4', 'Piso 2', 'Pasillos', 'Escaleras'),
(91, 'Edificio 4', 'Piso 2', 'Pasillos', 'Pasillo'),
(92, 'Edificio 4', 'Piso 2', 'Administración', 'Sala de maestros'),
(93, 'Edificio 5', 'Piso 1', 'Baños', 'Baño de mujeres'),
(94, 'Edificio 5', 'Piso 1', 'Baños', 'Baño de hombres'),
(95, 'Edificio 5', 'Piso 1', 'Aulas', 'Aula 1'),
(96, 'Edificio 5', 'Piso 1', 'Aulas', 'Aula 2'),
(97, 'Edificio 5', 'Piso 1', 'Aulas', 'Aula 3'),
(98, 'Edificio 5', 'Piso 1', 'Aulas', 'Aula 4'),
(99, 'Edificio 5', 'Piso 1', 'Aulas', 'Aula 5'),
(100, 'Edificio 5', 'Piso 1', 'Aulas', 'Aula 6');

INSERT INTO ubicaciones (id_ubicacion, edificio, piso, area_general, espacio_especifico) VALUES
(101, 'Edificio 5', 'Piso 1', 'Pasillos', 'Entrada'),
(102, 'Edificio 5', 'Piso 1', 'Pasillos', 'Escaleras'),
(103, 'Edificio 5', 'Piso 1', 'Pasillos', 'Pasillo'),
(104, 'Edificio 5', 'Piso 1', 'Administración', 'Oficina administrativa'),
(105, 'Edificio 5', 'Piso 2', 'Baños', 'Baño de mujeres'),
(106, 'Edificio 5', 'Piso 2', 'Baños', 'Baño de hombres'),
(107, 'Edificio 5', 'Piso 2', 'Aulas', 'Aula 7'),
(108, 'Edificio 5', 'Piso 2', 'Aulas', 'Aula 8'),
(109, 'Edificio 5', 'Piso 2', 'Aulas', 'Aula 9'),
(110, 'Edificio 5', 'Piso 2', 'Aulas', 'Aula 10'),
(111, 'Edificio 5', 'Piso 2', 'Aulas', 'Aula 11'),
(112, 'Edificio 5', 'Piso 2', 'Aulas', 'Aula 12'),
(113, 'Edificio 5', 'Piso 2', 'Pasillos', 'Escaleras'),
(114, 'Edificio 5', 'Piso 2', 'Pasillos', 'Pasillo'),
(115, 'Edificio 5', 'Piso 2', 'Administración', 'Sala de maestros'),
(116, 'Edificio 6', 'Piso 1', 'Baños', 'Baño de mujeres'),
(117, 'Edificio 6', 'Piso 1', 'Baños', 'Baño de hombres'),
(118, 'Edificio 6', 'Piso 1', 'Aulas', 'Aula 1'),
(119, 'Edificio 6', 'Piso 1', 'Aulas', 'Aula 2'),
(120, 'Edificio 6', 'Piso 1', 'Aulas', 'Aula 3'),
(121, 'Edificio 6', 'Piso 1', 'Aulas', 'Aula 4'),
(122, 'Edificio 6', 'Piso 1', 'Aulas', 'Aula 5'),
(123, 'Edificio 6', 'Piso 1', 'Aulas', 'Aula 6'),
(124, 'Edificio 6', 'Piso 1', 'Pasillos', 'Entrada'),
(125, 'Edificio 6', 'Piso 1', 'Pasillos', 'Escaleras'),
(126, 'Edificio 6', 'Piso 1', 'Pasillos', 'Pasillo'),
(127, 'Edificio 6', 'Piso 1', 'Administración', 'Oficina administrativa'),
(128, 'Edificio 6', 'Piso 2', 'Baños', 'Baño de mujeres'),
(129, 'Edificio 6', 'Piso 2', 'Baños', 'Baño de hombres'),
(130, 'Edificio 6', 'Piso 2', 'Aulas', 'Aula 7'),
(131, 'Edificio 6', 'Piso 2', 'Aulas', 'Aula 8'),
(132, 'Edificio 6', 'Piso 2', 'Aulas', 'Aula 9'),
(133, 'Edificio 6', 'Piso 2', 'Aulas', 'Aula 10'),
(134, 'Edificio 6', 'Piso 2', 'Aulas', 'Aula 11'),
(135, 'Edificio 6', 'Piso 2', 'Aulas', 'Aula 12'),
(136, 'Edificio 6', 'Piso 2', 'Pasillos', 'Escaleras'),
(137, 'Edificio 6', 'Piso 2', 'Pasillos', 'Pasillo'),
(138, 'Edificio 6', 'Piso 2', 'Administración', 'Sala de maestros'),
(139, 'Edificio 7', 'Piso 1', 'Baños', 'Baño de mujeres'),
(140, 'Edificio 7', 'Piso 1', 'Baños', 'Baño de hombres'),
(141, 'Edificio 7', 'Piso 1', 'Aulas', 'Aula 1'),
(142, 'Edificio 7', 'Piso 1', 'Aulas', 'Aula 2'),
(143, 'Edificio 7', 'Piso 1', 'Aulas', 'Aula 3'),
(144, 'Edificio 7', 'Piso 1', 'Aulas', 'Aula 4'),
(145, 'Edificio 7', 'Piso 1', 'Aulas', 'Aula 5'),
(146, 'Edificio 7', 'Piso 1', 'Aulas', 'Aula 6'),
(147, 'Edificio 7', 'Piso 1', 'Pasillos', 'Entrada'),
(148, 'Edificio 7', 'Piso 1', 'Pasillos', 'Escaleras'),
(149, 'Edificio 7', 'Piso 1', 'Pasillos', 'Pasillo'),
(150, 'Edificio 7', 'Piso 1', 'Administración', 'Oficina administrativa'),
(151, 'Edificio 7', 'Piso 2', 'Baños', 'Baño de mujeres'),
(152, 'Edificio 7', 'Piso 2', 'Baños', 'Baño de hombres'),
(153, 'Edificio 7', 'Piso 2', 'Aulas', 'Aula 7'),
(154, 'Edificio 7', 'Piso 2', 'Aulas', 'Aula 8'),
(155, 'Edificio 7', 'Piso 2', 'Aulas', 'Aula 9'),
(156, 'Edificio 7', 'Piso 2', 'Aulas', 'Aula 10'),
(157, 'Edificio 7', 'Piso 2', 'Aulas', 'Aula 11'),
(158, 'Edificio 7', 'Piso 2', 'Aulas', 'Aula 12'),
(159, 'Edificio 7', 'Piso 2', 'Pasillos', 'Escaleras'),
(160, 'Edificio 7', 'Piso 2', 'Pasillos', 'Pasillo'),
(161, 'Edificio 7', 'Piso 2', 'Administración', 'Sala de maestros'),
(162, 'Edificio 8', 'Piso 1', 'Baños', 'Baño de mujeres'),
(163, 'Edificio 8', 'Piso 1', 'Baños', 'Baño de hombres'),
(164, 'Edificio 8', 'Piso 1', 'Aulas', 'Aula 1'),
(165, 'Edificio 8', 'Piso 1', 'Aulas', 'Aula 2'),
(166, 'Edificio 8', 'Piso 1', 'Aulas', 'Aula 3'),
(167, 'Edificio 8', 'Piso 1', 'Aulas', 'Aula 4'),
(168, 'Edificio 8', 'Piso 1', 'Aulas', 'Aula 5'),
(169, 'Edificio 8', 'Piso 1', 'Aulas', 'Aula 6'),
(170, 'Edificio 8', 'Piso 1', 'Pasillos', 'Entrada'),
(171, 'Edificio 8', 'Piso 1', 'Pasillos', 'Escaleras'),
(172, 'Edificio 8', 'Piso 1', 'Pasillos', 'Pasillo'),
(173, 'Edificio 8', 'Piso 1', 'Administración', 'Oficina administrativa'),
(174, 'Edificio 8', 'Piso 2', 'Baños', 'Baño de mujeres'),
(175, 'Edificio 8', 'Piso 2', 'Baños', 'Baño de hombres'),
(176, 'Edificio 8', 'Piso 2', 'Aulas', 'Aula 7'),
(177, 'Edificio 8', 'Piso 2', 'Aulas', 'Aula 8'),
(178, 'Edificio 8', 'Piso 2', 'Aulas', 'Aula 9'),
(179, 'Edificio 8', 'Piso 2', 'Aulas', 'Aula 10'),
(180, 'Edificio 8', 'Piso 2', 'Aulas', 'Aula 11'),
(181, 'Edificio 8', 'Piso 2', 'Aulas', 'Aula 12'),
(182, 'Edificio 8', 'Piso 2', 'Pasillos', 'Escaleras'),
(183, 'Edificio 8', 'Piso 2', 'Pasillos', 'Pasillo'),
(184, 'Edificio 8', 'Piso 2', 'Administración', 'Sala de maestros'),
(185, 'Edificio 9', 'Piso 1', 'Baños', 'Baño de mujeres'),
(186, 'Edificio 9', 'Piso 1', 'Baños', 'Baño de hombres'),
(187, 'Edificio 9', 'Piso 1', 'Aulas', 'Aula 1'),
(188, 'Edificio 9', 'Piso 1', 'Aulas', 'Aula 2'),
(189, 'Edificio 9', 'Piso 1', 'Aulas', 'Aula 3'),
(190, 'Edificio 9', 'Piso 1', 'Aulas', 'Aula 4'),
(191, 'Edificio 9', 'Piso 1', 'Aulas', 'Aula 5'),
(192, 'Edificio 9', 'Piso 1', 'Aulas', 'Aula 6'),
(193, 'Edificio 9', 'Piso 1', 'Pasillos', 'Entrada'),
(194, 'Edificio 9', 'Piso 1', 'Pasillos', 'Escaleras'),
(195, 'Edificio 9', 'Piso 1', 'Pasillos', 'Pasillo'),
(196, 'Edificio 9', 'Piso 1', 'Administración', 'Oficina administrativa'),
(197, 'Edificio 9', 'Piso 2', 'Baños', 'Baño de mujeres'),
(198, 'Edificio 9', 'Piso 2', 'Baños', 'Baño de hombres'),
(199, 'Edificio 9', 'Piso 2', 'Aulas', 'Aula 7'),
(200, 'Edificio 9', 'Piso 2', 'Aulas', 'Aula 8');

INSERT INTO ubicaciones (id_ubicacion, edificio, piso, area_general, espacio_especifico) VALUES
(201, 'Edificio 9', 'Piso 2', 'Aulas', 'Aula 9'),
(202, 'Edificio 9', 'Piso 2', 'Aulas', 'Aula 10'),
(203, 'Edificio 9', 'Piso 2', 'Aulas', 'Aula 11'),
(204, 'Edificio 9', 'Piso 2', 'Aulas', 'Aula 12'),
(205, 'Edificio 9', 'Piso 2', 'Pasillos', 'Escaleras'),
(206, 'Edificio 9', 'Piso 2', 'Pasillos', 'Pasillo'),
(207, 'Edificio 9', 'Piso 2', 'Administración', 'Sala de maestros'),
(208, 'Edificio 10', 'Piso 1', 'Baños', 'Baño de mujeres'),
(209, 'Edificio 10', 'Piso 1', 'Baños', 'Baño de hombres'),
(210, 'Edificio 10', 'Piso 1', 'Aulas', 'Aula 1'),
(211, 'Edificio 10', 'Piso 1', 'Aulas', 'Aula 2'),
(212, 'Edificio 10', 'Piso 1', 'Aulas', 'Aula 3'),
(213, 'Edificio 10', 'Piso 1', 'Aulas', 'Aula 4'),
(214, 'Edificio 10', 'Piso 1', 'Aulas', 'Aula 5'),
(215, 'Edificio 10', 'Piso 1', 'Aulas', 'Aula 6'),
(216, 'Edificio 10', 'Piso 1', 'Pasillos', 'Entrada'),
(217, 'Edificio 10', 'Piso 1', 'Pasillos', 'Escaleras'),
(218, 'Edificio 10', 'Piso 1', 'Pasillos', 'Pasillo'),
(219, 'Edificio 10', 'Piso 1', 'Administración', 'Oficina administrativa'),
(220, 'Edificio 10', 'Piso 2', 'Baños', 'Baño de mujeres'),
(221, 'Edificio 10', 'Piso 2', 'Baños', 'Baño de hombres'),
(222, 'Edificio 10', 'Piso 2', 'Aulas', 'Aula 7'),
(223, 'Edificio 10', 'Piso 2', 'Aulas', 'Aula 8'),
(224, 'Edificio 10', 'Piso 2', 'Aulas', 'Aula 9'),
(225, 'Edificio 10', 'Piso 2', 'Aulas', 'Aula 10'),
(226, 'Edificio 10', 'Piso 2', 'Aulas', 'Aula 11'),
(227, 'Edificio 10', 'Piso 2', 'Aulas', 'Aula 12'),
(228, 'Edificio 10', 'Piso 2', 'Pasillos', 'Escaleras'),
(229, 'Edificio 10', 'Piso 2', 'Pasillos', 'Pasillo'),
(230, 'Edificio 10', 'Piso 2', 'Administración', 'Sala de maestros');

commit;