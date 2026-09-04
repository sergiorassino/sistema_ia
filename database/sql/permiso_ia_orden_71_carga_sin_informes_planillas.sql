-- Aclara que el permiso orden 71 cubre solo la carga manual.
-- Informes/boletines y planillas de visualización no lo requieren.
-- Equivalente a migración 2026_09_04_120000_update_permiso_ia_orden_71_carga_sin_informes_planillas.php
--
-- ADVERTENCIA: actualiza solo la descripción en `permisos_ia` (id 71). No modifica `profesores`.

UPDATE `permisos_ia`
SET `descripcion` = 'Carga manual de calificaciones e indicadores (inicial, primario y secundario). No incluye informes ni planillas de visualización.'
WHERE `id` = 71
  AND `orden` = 71;
