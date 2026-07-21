-- Permiso IA orden 85 — Gestión de TEA por inasistencias (Menú de Secretaría → ASISTENCIA ESTUDIANTES)
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia si hiciera falta.

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(85, 85, 'ASISTENCIA ESTUDIANTES', 'Gestión de TEA por inasistencias: registros por estudiante, alta, edición, baja e impresión PDF por situación.')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- Extender cadena permisos_ia en profesores (posición 85 = índice 86 en cadena 0-based no aplica; orden 85 → longitud mínima 86)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 86 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 86;
