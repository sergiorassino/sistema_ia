-- Estadística de rendimiento escolar (permiso orden 65)
-- ADVERTENCIA: INSERT idempotente por id. No modifica profesores.permisos_ia.
-- Reversible: DELETE FROM permisos_ia WHERE id = 65;

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(65, 65, 'ESTADÍSTICAS', 'Estadística de rendimiento escolar: aprobación por materias, docentes y estudiantes (nivel medio).')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

ALTER TABLE `permisos_ia` AUTO_INCREMENT = 66;
