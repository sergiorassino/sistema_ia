-- Permiso orden 86 — Gestión de planificaciones y programas de examen.
-- Ejecutar manualmente en el tenant que use el módulo (revisar id disponible si 86 ya existe).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(86, 86, 'EXÁMENES', 'Gestión de planificaciones y programas: subida de PDF, aprobación para estudiantes y observaciones (tabla materias).')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);
