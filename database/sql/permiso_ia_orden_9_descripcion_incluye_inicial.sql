-- Permiso IA orden 9 — descripción incluye nivel inicial
-- Equivalente a migración 2026_08_03_110000_update_permiso_ia_orden_9_descripcion_incluye_inicial.php
-- Preferible: php artisan se:migrate-legacy --force
--
-- ADVERTENCIA: solo cambia el texto del catálogo; no modifica profesores.permisos_ia.

UPDATE `permisos_ia`
SET
    `descripcion` = 'Importar calificaciones desde CSV CIDI/GE (inicial, primario y secundario).',
    `tema` = 'CALIFICACIONES'
WHERE `orden` = 9;
