-- Tabla doc_pp — registro de planificaciones y programas (PDF).
-- Un documento por materia del año y tipo (plan|prog).
-- Revisar antes de ejecutar. Irreversible sin backup si hay datos.

CREATE TABLE IF NOT EXISTS `doc_pp` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `idNivel` int unsigned NOT NULL,
  `idTerlec` int unsigned NOT NULL,
  `idMaterias` int unsigned NOT NULL,
  `idCursos` int unsigned NOT NULL,
  `tipo` varchar(8) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `aprobado` tinyint unsigned NOT NULL DEFAULT 0,
  `observaciones` varchar(500) DEFAULT NULL,
  `subido_por` int unsigned DEFAULT NULL,
  `subido_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `doc_pp_materias_tipo_unique` (`idMaterias`, `tipo`),
  KEY `doc_pp_nivel_terlec_tipo_idx` (`idNivel`, `idTerlec`, `tipo`),
  KEY `doc_pp_cursos_idx` (`idCursos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
