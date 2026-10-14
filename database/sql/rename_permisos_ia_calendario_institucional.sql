-- Renombra el tema visible del módulo: Actividad Institucional → Calendario Institucional.
-- Solo catálogo permisos_ia, órdenes 96, 109 y 110.
-- No cambia permisos otorgados ni profesores.permisos_ia.
-- Revisar antes de ejecutar.

UPDATE `permisos_ia`
SET `tema` = 'CALENDARIO INSTITUCIONAL'
WHERE `orden` IN (96, 109, 110);

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden IN (96, 109, 110);
