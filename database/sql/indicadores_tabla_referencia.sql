-- Referencia: esquema legacy `indicadores` usado en nivel inicial (este proyecto).
-- Una fila por materia; cada período en columna `indicadorN` (texto multilínea, un ítem por línea).
-- NO ejecutar si la tabla ya existe con datos.

CREATE TABLE IF NOT EXISTS `indicadores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idMaterias` int NOT NULL,
  `indicador1` mediumtext NULL,
  `indicador2` mediumtext NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `indicadores_idmaterias_unique` (`idMaterias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
