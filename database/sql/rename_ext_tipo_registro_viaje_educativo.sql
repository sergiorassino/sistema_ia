-- Renombra el tipo 1 a Viaje Educativo.
-- Vacía lugar y descripción de las actividades de tipo 2 y 3.
-- Equivalente: database/migrations/2026_10_14_160000_rename_ext_tipo_registro_viaje_educativo.php
-- Revisar antes de ejecutar.
-- Alcance: ext_tipo_registro (id 1) y ext_actividades (tipos 2 y 3).
-- El vaciado de lugar y descripción no se puede deshacer.

UPDATE `ext_tipo_registro`
SET `nombre` = 'Viaje Educativo'
WHERE `id` = 1;

UPDATE `ext_actividades`
SET `lugar` = NULL,
    `descripcion` = NULL
WHERE `id_tipo_registro` IN (2, 3);

-- Verificación:
-- SELECT id, nombre FROM ext_tipo_registro ORDER BY id;
-- SELECT id, id_tipo_registro, lugar, descripcion FROM ext_actividades WHERE id_tipo_registro IN (2, 3);
