-- Separa el permiso orden 44 (antes aceptación + documentos familia) en:
--   orden 44 → documentos de aceptación (PDF por nivel)
--   orden 83 → documentos a subir (familia)
-- Equivalente a migración 2026_07_03_140000_split_permiso_matricula_web_documentos_estudiante.php
--
-- ADVERTENCIA: modifica permisos_ia y profesores.permisos_ia (copia bit 44 → bit 83 donde 44=1).

UPDATE `permisos_ia` SET
    `orden` = 44,
    `tema` = 'MATRÍCULA WEB',
    `descripcion` = 'Documentos de aceptación (PDF por nivel): compromiso educativo, AEC, normativas y traslado para el portal de estudiantes.'
WHERE `id` = 44;

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(83, 83, 'MATRÍCULA WEB', 'Documentos a subir (familia): parametrizar tipos de documentación que la familia carga en actualización de datos.')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- Usuarios con permiso 44 activo reciben también el permiso 83 (compatibilidad).
UPDATE `profesores`
SET `permisos_ia` = CONCAT(
    IFNULL(`permisos_ia`, ''),
    REPEAT('0', GREATEST(0, 84 - CHAR_LENGTH(IFNULL(`permisos_ia`, ''))))
)
WHERE `permisos_ia` IS NOT NULL
  AND CHAR_LENGTH(IFNULL(`permisos_ia`, '')) > 44
  AND SUBSTRING(`permisos_ia`, 45, 1) = '1';

UPDATE `profesores`
SET `permisos_ia` = CONCAT(
    LEFT(`permisos_ia`, 83),
    '1',
    SUBSTRING(`permisos_ia`, 85)
)
WHERE `permisos_ia` IS NOT NULL
  AND CHAR_LENGTH(`permisos_ia`) >= 45
  AND SUBSTRING(`permisos_ia`, 45, 1) = '1'
  AND (CHAR_LENGTH(`permisos_ia`) < 84 OR SUBSTRING(`permisos_ia`, 84, 1) <> '1');
