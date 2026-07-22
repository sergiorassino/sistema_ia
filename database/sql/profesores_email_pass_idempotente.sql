-- =============================================================================
-- Columna `profesores.emailPass` — creación idempotente
-- Referencia: esquema de ia_colegiofader (correo masivo SMTP Gmail del usuario)
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_07_22_130000_add_email_pass_to_profesores_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
-- Compatible con MySQL 5.7+ / MariaDB 10.x
-- Sin DELIMITER ni procedimientos almacenados (ejecutable en un solo lote).
--
-- Verificar antes:
--   SHOW COLUMNS FROM profesores LIKE 'email%';
--
-- ADVERTENCIA: solo agrega la columna si falta. No modifica datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

SET @has_email_pass := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'profesores'
    AND COLUMN_NAME = 'emailPass'
);

SET @has_email := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'profesores'
    AND COLUMN_NAME = 'email'
);

SET @ddl_email_pass := IF(
  @has_email_pass > 0,
  'SELECT 1',
  IF(
    @has_email > 0,
    'ALTER TABLE `profesores` ADD COLUMN `emailPass` varchar(19) DEFAULT NULL AFTER `email`',
    'ALTER TABLE `profesores` ADD COLUMN `emailPass` varchar(19) DEFAULT NULL'
  )
);

PREPARE stmt_email_pass FROM @ddl_email_pass;
EXECUTE stmt_email_pass;
DEALLOCATE PREPARE stmt_email_pass;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
