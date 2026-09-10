-- Permiso IA orden 104 — Copiar cursos, materias y horarios entre años lectivos
-- Menú de Administración / Secretaría → Configuración
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.
-- ADVERTENCIA: el UPDATE alarga profesores.permisos_ia. Irreversible en la práctica (no recorta la cadena).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(104, 104, 'CONFIGURACIÓN', 'Copiar cursos, materias y horarios de un año lectivo de origen a un año de destino (por nivel). NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 104 → longitud mínima 105 (carácter en posición 105, índice 104)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 105 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 105;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 104;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
