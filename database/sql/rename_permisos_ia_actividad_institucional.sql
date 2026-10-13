-- Renombra el tema visible de los permisos del módulo Actividad Institucional
-- (antes Proyectos extracurriculares). Solo catálogo permisos_ia, órdenes 96, 109 y 110.
-- No cambia permisos otorgados ni profesores.permisos_ia.
-- Revisar antes de ejecutar.

UPDATE `permisos_ia`
SET `tema` = 'ACTIVIDAD INSTITUCIONAL',
    `descripcion` = 'Aprobar actividades institucionales presentadas por docentes y comunicar a los involucrados (organizadores, docentes del curso y preceptores).'
WHERE `orden` = 96;

UPDATE `permisos_ia`
SET `tema` = 'ACTIVIDAD INSTITUCIONAL',
    `descripcion` = 'Ver y presentar actividades institucionales propias en el Menú de Secretaría (listado, alta y edición).'
WHERE `orden` = 109;

UPDATE `permisos_ia`
SET `tema` = 'ACTIVIDAD INSTITUCIONAL',
    `descripcion` = 'Ver actividades institucionales aprobadas e imprimir autorizaciones de padres o tutores para los alumnos involucrados.'
WHERE `orden` = 110;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden IN (96, 109, 110);
