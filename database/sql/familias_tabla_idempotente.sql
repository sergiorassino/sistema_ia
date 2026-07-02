-- =============================================================================
-- Tabla `familias` — creación idempotente (re-ejecutable)
-- Referencia: esquema de ia_sanfranciscoasis (Colegio San Francisco de Asís)
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_07_02_140000_create_familias_table_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
-- Compatible con MySQL 5.7+ / MariaDB 10.x
-- Sin DELIMITER ni procedimientos almacenados (ejecutable en un solo lote).
--
-- Verificar antes:
--   SHOW TABLES LIKE 'familias';
--   SHOW CREATE TABLE familias;
--
-- ADVERTENCIA: solo crea la tabla y la fila placeholder id=1 si faltan.
-- No modifica familias existentes ni datos de legajos.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Tabla familias (estructura San Francisco de Asís)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `familias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apellido` varchar(50) NOT NULL DEFAULT '',
  `responsable` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(150) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- Colegios con tabla legacy sin email (montecristo, epq, sfq, etc.)
SET @has_email := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'familias'
    AND COLUMN_NAME = 'email'
);
SET @ddl_email := IF(
  @has_email = 0,
  'ALTER TABLE `familias` ADD COLUMN `email` varchar(150) DEFAULT '''' AFTER `responsable`',
  'SELECT 1'
);
PREPARE stmt_email FROM @ddl_email;
EXECUTE stmt_email;
DEALLOCATE PREPARE stmt_email;

-- Fila placeholder legacy (legajos.idFamilias = 1 = «sin familia asignada»)
INSERT INTO `familias` (`id`, `apellido`, `responsable`, `email`) VALUES
(1, ' Sin Registro de Familia', '', '')
ON DUPLICATE KEY UPDATE
  `id` = `id`;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
