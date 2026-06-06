-- Permiso orden 45: Gestión de Solicitudes de Evaluación (secundario, tabla evaluac)
-- Equivalente a migración 2026_05_30_120000_add_permiso_ia_orden_45_gestion_solicitudes_evaluacion.php
--
-- ADVERTENCIA: INSERT idempotente por id. No modifica profesores.permisos_ia.
-- Tras ejecutar, asignar el permiso 45 a los usuarios que deban gestionar evaluaciones.

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(45, 45, 'CALIFICACIONES SECUNDARIO', 'Gestión de solicitudes de evaluación: listado por fecha, alta, edición y baja de evaluaciones programadas (tabla evaluac).')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

ALTER TABLE `permisos_ia` AUTO_INCREMENT = 46;
