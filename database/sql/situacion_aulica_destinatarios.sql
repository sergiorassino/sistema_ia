-- Destinatarios adicionales del aviso de situación áulica (Cuaderno de Seguimiento Áulico).
-- Una fila por profesor y nivel. No reemplaza el aviso al preceptor del curso.
-- Revisar antes de ejecutar. Solo crea la tabla si no existe.

CREATE TABLE IF NOT EXISTS `situacion_aulica_destinatarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idNivel` INT UNSIGNED NOT NULL,
  `idProfesor` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sit_aulica_dest_nivel_prof` (`idNivel`, `idProfesor`),
  KEY `idx_sit_aulica_dest_nivel` (`idNivel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verificación:
-- SHOW COLUMNS FROM situacion_aulica_destinatarios;
