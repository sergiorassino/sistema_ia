-- Matrícula web — permiso IA orden 44 (documentos de aceptación por nivel)
-- Los nombres de archivo se guardan en columnas legacy de `ento`: documAcept1 … documAcept4.
-- Idempotente. Ejecutar en el cliente MySQL del colegio (no desde el agente).

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(44, 44, 'MATRÍCULA WEB', 'Documentos de aceptación (PDF por nivel): compromiso educativo, AEC, normativas y traslado para el portal de estudiantes.')
ON DUPLICATE KEY UPDATE
  `orden` = VALUES(`orden`),
  `tema` = VALUES(`tema`),
  `descripcion` = VALUES(`descripcion`);
