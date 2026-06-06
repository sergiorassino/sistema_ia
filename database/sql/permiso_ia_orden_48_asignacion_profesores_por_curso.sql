-- Permiso orden 48: Asignación de profesores por materia y curso (ppc) + consulta cursos por profesor.
-- Permiso orden 11: solo ABM legajo docente (descripción actualizada).
-- Equivalente a migración 2026_06_04_120000_add_permiso_ia_orden_48_asignacion_profesores_por_curso.php
--
-- ADVERTENCIA: modifica permisos_ia y profesores.permisos_ia (copia orden 11 → 48 donde 11=1).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(12, 11, 'LEGAJOS DOCENTES', 'Crear, editar y eliminar legajos de docentes (ABM legajo docente).')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(48, 48, 'LEGAJOS DOCENTES', 'Asignar y quitar docentes en materias por curso (ppc); consultar cursos por profesor.')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

ALTER TABLE `permisos_ia` AUTO_INCREMENT = 49;

-- Usuarios que tenían permiso 11 conservan asignación (nuevo permiso 48).
UPDATE `profesores`
SET `permisos_ia` = CONCAT(
    IFNULL(`permisos_ia`, ''),
    REPEAT('0', GREATEST(0, 49 - CHAR_LENGTH(IFNULL(`permisos_ia`, ''))))
)
WHERE `permisos_ia` IS NOT NULL
  AND CHAR_LENGTH(IFNULL(`permisos_ia`, '')) > 11
  AND SUBSTRING(`permisos_ia`, 12, 1) = '1';

UPDATE `profesores`
SET `permisos_ia` = CONCAT(
    LEFT(`permisos_ia`, 48),
    '1',
    SUBSTRING(`permisos_ia`, 50)
)
WHERE `permisos_ia` IS NOT NULL
  AND CHAR_LENGTH(`permisos_ia`) >= 12
  AND SUBSTRING(`permisos_ia`, 12, 1) = '1'
  AND (CHAR_LENGTH(`permisos_ia`) < 49 OR SUBSTRING(`permisos_ia`, 49, 1) <> '1');
