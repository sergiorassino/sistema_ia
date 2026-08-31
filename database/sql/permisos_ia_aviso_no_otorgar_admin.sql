-- Aviso en descripciones de permisos reservados al administrador.
-- Órdenes: 25, 26, 33, 34, 35, 36, 100.
-- Equivalente: database/migrations/2026_08_31_190000_update_permisos_ia_aviso_no_otorgar_admin.php
-- Revisar antes de ejecutar. Solo actualiza permisos_ia.descripcion (no toca profesores.permisos_ia).
-- Idempotente: deja el texto alineado al catálogo PHP.

UPDATE `permisos_ia` SET `descripcion` = 'Términos lectivos. NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR' WHERE `orden` = 25;
UPDATE `permisos_ia` SET `descripcion` = 'Niveles educativos. NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR' WHERE `orden` = 26;
UPDATE `permisos_ia` SET `descripcion` = 'Gestión de planes de estudio. NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR' WHERE `orden` = 33;
UPDATE `permisos_ia` SET `descripcion` = 'Gestión de cursos y materias del plan. NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR' WHERE `orden` = 34;
UPDATE `permisos_ia` SET `descripcion` = 'Gestión de cursos / grados / salas del año. NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR' WHERE `orden` = 35;
UPDATE `permisos_ia` SET `descripcion` = 'Gestión de asignaturas del año. NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR' WHERE `orden` = 36;
UPDATE `permisos_ia` SET `descripcion` = 'Cierre anual: consultar lotes persistidos (informe de cada ejecución) y revertir un cierre. NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR' WHERE `orden` = 100;

-- Verificación:
-- SELECT orden, descripcion FROM permisos_ia WHERE orden IN (25, 26, 33, 34, 35, 36, 100) ORDER BY orden;
