-- =============================================================================
-- Columnas `ento.ctaEnvioMail` y `ento.passEnvioMail` — creación idempotente
-- Cuenta y contraseña de aplicación para envío de correo institucional (SMTP).
--
-- Equivalente a:
--   database/migrations/2026_08_10_130000_add_cta_pass_envio_mail_to_ento_if_missing.php
--
-- Uso preferido:
--   php artisan migrate
--   php artisan se:migrate-legacy --force
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
-- Compatible con MySQL 5.7+ / MariaDB 10.x
-- Sin DELIMITER ni procedimientos almacenados (ejecutable en un solo lote).
--
-- Verificar antes/después:
--   SHOW COLUMNS FROM ento LIKE '%EnvioMail%';
--   SHOW COLUMNS FROM ento LIKE 'mail%';
--
-- ADVERTENCIA: solo agrega columnas si faltan. No modifica datos existentes.
-- =============================================================================

SET NAMES utf8mb4;

-- ── ctaEnvioMail ─────────────────────────────────────────────────────────────

SET @has_cta := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ento'
    AND COLUMN_NAME = 'ctaEnvioMail'
);

SET @has_mail := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ento'
    AND COLUMN_NAME = 'mail'
);

SET @ddl_cta := IF(
  @has_cta > 0,
  'SELECT 1',
  IF(
    @has_mail > 0,
    'ALTER TABLE `ento` ADD COLUMN `ctaEnvioMail` varchar(120) DEFAULT NULL AFTER `mail`',
    'ALTER TABLE `ento` ADD COLUMN `ctaEnvioMail` varchar(120) DEFAULT NULL'
  )
);

PREPARE stmt_cta FROM @ddl_cta;
EXECUTE stmt_cta;
DEALLOCATE PREPARE stmt_cta;

-- ── passEnvioMail ────────────────────────────────────────────────────────────

SET @has_pass := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ento'
    AND COLUMN_NAME = 'passEnvioMail'
);

SET @has_cta2 := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ento'
    AND COLUMN_NAME = 'ctaEnvioMail'
);

SET @has_mail2 := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ento'
    AND COLUMN_NAME = 'mail'
);

SET @ddl_pass := IF(
  @has_pass > 0,
  'SELECT 1',
  IF(
    @has_cta2 > 0,
    'ALTER TABLE `ento` ADD COLUMN `passEnvioMail` varchar(40) DEFAULT NULL AFTER `ctaEnvioMail`',
    IF(
      @has_mail2 > 0,
      'ALTER TABLE `ento` ADD COLUMN `passEnvioMail` varchar(40) DEFAULT NULL AFTER `mail`',
      'ALTER TABLE `ento` ADD COLUMN `passEnvioMail` varchar(40) DEFAULT NULL'
    )
  )
);

PREPARE stmt_pass FROM @ddl_pass;
EXECUTE stmt_pass;
DEALLOCATE PREPARE stmt_pass;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
