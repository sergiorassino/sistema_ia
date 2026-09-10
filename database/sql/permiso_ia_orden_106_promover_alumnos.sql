-- Permiso IA orden 106 — Promover a todos los alumnos entre años lectivos
-- Menú de Administración / Secretaría → Configuración
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.
-- ADVERTENCIA: el UPDATE alarga profesores.permisos_ia. Irreversible en la práctica (no recorta la cadena).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(106, 106, 'CONFIGURACIÓN', 'Promover a todos los alumnos: crear matrícula y calificaciones en el año de destino a partir de los cursos marcados del año de origen. NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 106 → longitud mínima 107 (carácter en posición 107, índice 106)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 107 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 107;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 106;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
