-- Permiso orden 46: Gestión de familias de estudiantes (tabla familias, vínculo legajos.idFamilias)
-- Equivalente a migración 2026_06_01_120000_add_permiso_ia_orden_46_legajos_familias_gestion.php
--
-- ADVERTENCIA: INSERT idempotente por id. No modifica profesores.permisos_ia.
-- Tras ejecutar, asignar el permiso 46 a los usuarios que deban crear, editar o eliminar familias.

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(46, 46, 'LEGAJOS ESTUDIANTES', 'Gestionar familias de estudiantes: crear, editar, eliminar y asignar o quitar vínculos con legajos (la consulta permanece disponible para todos).')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

ALTER TABLE `permisos_ia` AUTO_INCREMENT = 47;
