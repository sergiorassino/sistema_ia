-- Permiso IA orden 108 — Estadística de pago por curso y cuota
-- Menú de Administración → Resúmenes
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.
-- ADVERTENCIA: el UPDATE alarga profesores.permisos_ia. Irreversible en la práctica (no recorta la cadena).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(108, 108, 'RESÚMENES DE ARANCELES', 'Estadística de pago por curso y cuota: PDF de matrícula y meses marzo a diciembre, con importes, bonificaciones, intereses y deuda.')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 108 → longitud mínima 109 (carácter en posición 109, índice 108)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 109 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 109;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 108;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
