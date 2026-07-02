-- =============================================================================
-- Tabla `horarios26` — creación idempotente (re-ejecutable)
-- Referencia: esquema de ia_sanfranciscoasis (Colegio San Francisco de Asís)
--
-- Grilla de horas cátedra por docente / materia / curso / día / módulo.
-- Sin datos: no inserta filas.
--
-- Uso: ejecutar en la base del colegio destino (phpMyAdmin, HeidiSQL, mysql CLI).
-- Compatible con MySQL 5.7+ / MariaDB 10.x
-- Sin DELIMITER ni procedimientos almacenados (ejecutable en un solo lote).
--
-- Verificar antes:
--   SHOW TABLES LIKE 'horarios26';
--   SHOW CREATE TABLE horarios26;
--
-- ADVERTENCIA: solo crea la tabla o agrega columnas faltantes. No borra ni altera datos.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Tabla horarios26 (estructura San Francisco de Asís)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `horarios26` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idProfesores` int(11) DEFAULT 0,
  `idMaterias` int(11) DEFAULT 0,
  `idDia` varchar(3) DEFAULT '0',
  `idHora` int(11) DEFAULT 0,
  `idTurnoClase` tinyint(3) unsigned DEFAULT NULL,
  `idCursos` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- Colegios con tabla legacy sin idTurnoClase (modelo antiguo idHora 11–30)
SET @has_turno := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'horarios26'
    AND COLUMN_NAME = 'idTurnoClase'
);
SET @ddl_turno := IF(
  @has_turno = 0,
  'ALTER TABLE `horarios26` ADD COLUMN `idTurnoClase` tinyint(3) unsigned DEFAULT NULL AFTER `idHora`',
  'SELECT 1'
);
PREPARE stmt_turno FROM @ddl_turno;
EXECUTE stmt_turno;
DEALLOCATE PREPARE stmt_turno;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- Tras crear la tabla, correr migraciones Laravel de horarios si aplica
-- (p. ej. 2026_05_18_140000_add_id_turno_clase_to_horarios26.php).
-- =============================================================================
