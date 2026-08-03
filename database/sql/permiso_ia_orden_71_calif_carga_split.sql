-- Separa el permiso orden 9 (antes sincro + carga manual) en:
--   orden 9  → importar calificaciones desde CIDI/GE
--   orden 71 → carga manual de calificaciones e indicadores
-- Equivalente a migración 2026_06_12_100000_split_permiso_calif_sincro_carga.php
--
-- ADVERTENCIA: modifica permisos_ia y profesores.permisos_ia (copia bit 9 → bit 71 donde 9=1).

UPDATE `permisos_ia` SET
    `orden` = 9,
    `tema` = 'CALIFICACIONES',
    `descripcion` = 'Importar calificaciones desde CSV CIDI/GE (inicial, primario y secundario).'
WHERE `id` = 10;

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(71, 71, 'CALIFICACIONES', 'Carga manual de calificaciones e indicadores (inicial, primario y secundario).')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- Usuarios con permiso 9 activo reciben también el permiso 71 (compatibilidad).
UPDATE `profesores`
SET `permisos_ia` = CONCAT(
    IFNULL(`permisos_ia`, ''),
    REPEAT('0', GREATEST(0, 72 - CHAR_LENGTH(IFNULL(`permisos_ia`, ''))))
)
WHERE `permisos_ia` IS NOT NULL
  AND CHAR_LENGTH(IFNULL(`permisos_ia`, '')) > 9
  AND SUBSTRING(`permisos_ia`, 10, 1) = '1';

UPDATE `profesores`
SET `permisos_ia` = CONCAT(
    LEFT(`permisos_ia`, 71),
    '1',
    SUBSTRING(`permisos_ia`, 73)
)
WHERE `permisos_ia` IS NOT NULL
  AND CHAR_LENGTH(`permisos_ia`) >= 10
  AND SUBSTRING(`permisos_ia`, 10, 1) = '1'
  AND (CHAR_LENGTH(`permisos_ia`) < 72 OR SUBSTRING(`permisos_ia`, 72, 1) <> '1');
