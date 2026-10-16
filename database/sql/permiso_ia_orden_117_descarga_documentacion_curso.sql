-- Permiso IA orden 117 — Descarga de documentación por curso
-- Menú de Secretaría y Menú de Administración → Estudiantes → Descarga de documentación
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Equivalente (solo INSERT catálogo): database/migrations/2026_10_16_090000_add_permiso_ia_orden_117_descarga_documentacion_curso.php
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.
-- ADVERTENCIA: el UPDATE alarga profesores.permisos_ia. Irreversible en la práctica (no recorta la cadena).
-- La columna profesores.permisos_ia debe ser al menos VARCHAR(128).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(117, 117, 'LEGAJOS ESTUDIANTES', 'Descarga de documentación por curso: ZIP con los archivos cargados de los estudiantes regulares del curso (carpeta del curso y una carpeta por alumno).')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 117 → longitud mínima 118 (carácter en posición 118, índice 117)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 118 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 118;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 117;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
