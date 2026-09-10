-- Permiso IA orden 105 — Copiar asignación de profesores y preceptores entre años lectivos
-- Menú de Administración / Secretaría → Configuración
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.
-- ADVERTENCIA: el UPDATE alarga profesores.permisos_ia. Irreversible en la práctica (no recorta la cadena).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(105, 105, 'CONFIGURACIÓN', 'Copiar asignación de profesores por curso (ppc) y de preceptores por curso de un año lectivo de origen a un año de destino (por nivel). NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 105 → longitud mínima 106 (carácter en posición 106, índice 105)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 106 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 106;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 105;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
