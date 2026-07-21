-- Permiso orden 86 — Planificaciones y programas (doc_pp).
-- Ejecutar manualmente en el tenant (revisar id si 86 ya existe con otro significado).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(86, 86, 'EXÁMENES', 'Planificaciones y programas: subida de PDF, aprobación para estudiantes y observaciones (tabla doc_pp).')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);
