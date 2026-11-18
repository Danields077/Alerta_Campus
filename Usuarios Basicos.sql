USE Alerta_Campus;
SET NAMES utf8mb4;

-- Usuarios básicos: contraseña 12345
-- Hash generado con password_hash() de PHP (PASSWORD_DEFAULT).
INSERT INTO usuarios
    (id_rol, identificador_institucional, nombre_completo, correo, password_hash, estado, id_area)
VALUES
    ((SELECT id_rol FROM roles WHERE nombre_rol = 'Estudiante'),
     'ROL_ESTUDIANTE', 'rol', 'estudiante@zacatepec.tecnm.mx',
     '$2y$10$xZSlBu/Kf5tGz8neA3P0TOSIDcVV9BwtPPwXswJk1kcYWfJ4lWgAi',
     'Activo', NULL),
    ((SELECT id_rol FROM roles WHERE nombre_rol = 'Profesor'),
     'ROL_PROFESOR', 'rol', 'profesor@zacatepec.tecnm.mx',
     '$2y$10$xZSlBu/Kf5tGz8neA3P0TOSIDcVV9BwtPPwXswJk1kcYWfJ4lWgAi',
     'Activo', NULL),
    ((SELECT id_rol FROM roles WHERE nombre_rol = 'Personal de atención'),
     'ROL_PERSONAL', 'rol', 'personal@zacatepec.tecnm.mx',
     '$2y$10$xZSlBu/Kf5tGz8neA3P0TOSIDcVV9BwtPPwXswJk1kcYWfJ4lWgAi',
     'Activo', 1),
    ((SELECT id_rol FROM roles WHERE nombre_rol = 'Jefe de departamento'),
     'ROL_JEFE', 'rol', 'jefe@zacatepec.tecnm.mx',
     '$2y$10$xZSlBu/Kf5tGz8neA3P0TOSIDcVV9BwtPPwXswJk1kcYWfJ4lWgAi',
     'Activo', 1),
    ((SELECT id_rol FROM roles WHERE nombre_rol = 'Administrador'),
     'ROL_ADMINISTRADOR', 'rol', 'administrador@zacatepec.tecnm.mx',
     '$2y$10$xZSlBu/Kf5tGz8neA3P0TOSIDcVV9BwtPPwXswJk1kcYWfJ4lWgAi',
     'Activo', NULL)
ON DUPLICATE KEY UPDATE
    id_rol = VALUES(id_rol),
    identificador_institucional = VALUES(identificador_institucional),
    nombre_completo = VALUES(nombre_completo),
    password_hash = VALUES(password_hash),
    estado = VALUES(estado),
    id_area = VALUES(id_area);