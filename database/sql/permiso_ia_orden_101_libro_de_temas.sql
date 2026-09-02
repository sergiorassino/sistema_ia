-- Permiso IA orden 101 — Libro de temas
-- Menú de Secretaría / Administración → DOCENTES / USUARIOS
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.
-- El módulo también exige config tenant `modulos.libro_de_temas` (hoy: iess).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(101, 101, 'LIBRO DE TEMAS', 'Libro de temas: alta, edición y baja de clases por materia (fecha, unidad, carácter, temas, actividades y observaciones).')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 101 → longitud mínima 102 (carácter en posición 102, índice 101)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 102 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 102;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 101;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
