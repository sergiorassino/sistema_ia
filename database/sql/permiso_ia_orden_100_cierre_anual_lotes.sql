-- Permiso IA orden 100 — Cierre anual: lotes y reversión
-- Menú de Secretaría → CALIFICACIONES (Secundario) → Cierre anual → Lotes
-- Alcance: tabla permisos_ia + extensión de profesores.permisos_ia.
-- Equivalente (solo INSERT catálogo): database/migrations/2026_08_31_180000_add_permiso_ia_orden_100_cierre_anual_lotes.php
-- Revisar antes de ejecutar. No otorga el permiso (queda en 0); activarlo en Asignación de Permisos de Usuario.

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(100, 100, 'CALIFICACIONES SECUNDARIO', 'Cierre anual: consultar lotes persistidos (informe de cada ejecución) y revertir un cierre. NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

-- orden 100 → longitud mínima 101 (carácter en posición 101, índice 100)
UPDATE `profesores`
SET `permisos_ia` = CONCAT(IFNULL(`permisos_ia`, ''), REPEAT('0', GREATEST(0, 101 - CHAR_LENGTH(IFNULL(`permisos_ia`, '')))))
WHERE CHAR_LENGTH(IFNULL(`permisos_ia`, '')) < 101;

-- Verificación:
-- SELECT id, orden, tema, descripcion FROM permisos_ia WHERE orden = 100;
-- SELECT id, CHAR_LENGTH(permisos_ia) AS len FROM profesores ORDER BY id LIMIT 5;
