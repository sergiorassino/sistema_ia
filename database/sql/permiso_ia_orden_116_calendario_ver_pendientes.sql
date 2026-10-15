-- Permiso IA orden 116 — Ver actividades sin aprobar en el calendario institucional
-- Menú de Secretaría → CALENDARIO INSTITUCIONAL → Calendario escolar
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Equivalente (solo INSERT catálogo): database/migrations/2026_10_15_090000_add_permiso_ia_orden_116_calendario_ver_pendientes.php
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.
-- ADVERTENCIA: el UPDATE alarga profesores.permisos_ia. Irreversible en la práctica (no recorta la cadena).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(116, 116, 'CALENDARIO INSTITUCIONAL', 'Ver en el calendario institucional las actividades que aún no están aprobadas, de todos los niveles del ciclo. Se muestran en rojo y sin negrita. Sin este permiso el calendario solo muestra las aprobadas, también de todos los niveles.')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 116 → longitud mínima 117 (carácter en posición 117, índice 116)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 117 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 117;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 116;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
