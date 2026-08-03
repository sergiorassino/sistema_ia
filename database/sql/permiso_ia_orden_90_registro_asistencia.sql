-- =============================================================================
-- Permiso IA orden 90 — Registro de Asistencia (PDF mensual + feriados)
-- Equivalente a migración 2026_08_03_100000_add_permiso_ia_orden_90_registro_asistencia.php
--
-- Solo inserta/actualiza el catálogo en `permisos_ia`.
-- No modifica `profesores.permisos_ia` — asignar el bit 90 desde la UI de permisos.
--
-- Uso preferido: php artisan se:migrate-legacy --force
-- =============================================================================

SET NAMES utf8mb4;

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(90, 90, 'ASISTENCIA ESTUDIANTES', 'Registro de asistencia: impresión PDF mensual por curso(s) (con o sin datos) y administración de feriados del nivel.')
ON DUPLICATE KEY UPDATE
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);
