-- Permiso IA orden 111 — Libre deuda familiar
-- Menú de Administración → Gestión de aranceles → Libre Deuda Familiar (debajo de Aranceles por estudiante)
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Equivalente (solo INSERT catálogo): database/migrations/2026_09_28_140000_add_permiso_ia_orden_111_libre_deuda_familiar.php
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Permisos por usuario.
-- ADVERTENCIA: el UPDATE alarga profesores.permisos_ia. Irreversible en la práctica (no recorta la cadena).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(111, 111, 'GESTIÓN DE ARANCELES', 'Libre deuda familiar: listado de estudiantes matriculados y constancia PDF si no registran cuotas con saldo.')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 111 → longitud mínima 112 (carácter en posición 112, índice 111)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 112 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 112;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 111;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
