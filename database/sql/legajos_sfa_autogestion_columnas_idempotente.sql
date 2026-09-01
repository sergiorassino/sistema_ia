-- =============================================================================
-- Tabla `legajos` — columnas de autogestión / ficha (variante San Francisco de Asís)
--
-- Uso preferido: php artisan migrate
--   (migración 2026_09_01_160000_add_sfa_autogestion_columns_to_legajos_if_missing.php)
--   o php artisan se:migrate-legacy --force
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI
--   sobre la BD del tenant (p. ej. EPQ, SFQ) que use
--   autogestion.actualizacion_datos.implementacion = sanfranciscoasis.
--
-- Columnas (solo si faltan):
--   reglamApenom VARCHAR(100) NULL  — adulto responsable, apellido y nombre
--   reglamDni    VARCHAR(20)  NULL  — adulto responsable, DNI
--   reglamEmail  VARCHAR(120) NULL  — adulto responsable, e-mail
--   ec_padres    VARCHAR(200) NULL  — estado civil de los padres
--   contacto1    VARCHAR(200) NULL  — contacto de emergencia 1
--   contacto2    VARCHAR(200) NULL  — contacto de emergencia 2
--   contacto3    VARCHAR(200) NULL  — contacto de emergencia 3
--   retira1      VARCHAR(200) NULL  — personas autorizadas a retirar
--   obs_web      TEXT         NULL  — observaciones (autogestión)
--
-- ADVERTENCIA: solo agrega las columnas si faltan. No altera tipos ni datos
-- existentes. Alcance: tabla `legajos` del tenant conectado. Irreversible
-- salvo DROP COLUMN manual. Si la columna ya existe, ese bloque no hace nada.
-- =============================================================================

SET NAMES utf8mb4;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legajos' AND COLUMN_NAME = 'reglamApenom'
);
SET @ddl := IF(@has = 0, 'ALTER TABLE `legajos` ADD COLUMN `reglamApenom` VARCHAR(100) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legajos' AND COLUMN_NAME = 'reglamDni'
);
SET @ddl := IF(@has = 0, 'ALTER TABLE `legajos` ADD COLUMN `reglamDni` VARCHAR(20) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legajos' AND COLUMN_NAME = 'reglamEmail'
);
SET @ddl := IF(@has = 0, 'ALTER TABLE `legajos` ADD COLUMN `reglamEmail` VARCHAR(120) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legajos' AND COLUMN_NAME = 'ec_padres'
);
SET @ddl := IF(@has = 0, 'ALTER TABLE `legajos` ADD COLUMN `ec_padres` VARCHAR(200) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legajos' AND COLUMN_NAME = 'contacto1'
);
SET @ddl := IF(@has = 0, 'ALTER TABLE `legajos` ADD COLUMN `contacto1` VARCHAR(200) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legajos' AND COLUMN_NAME = 'contacto2'
);
SET @ddl := IF(@has = 0, 'ALTER TABLE `legajos` ADD COLUMN `contacto2` VARCHAR(200) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legajos' AND COLUMN_NAME = 'contacto3'
);
SET @ddl := IF(@has = 0, 'ALTER TABLE `legajos` ADD COLUMN `contacto3` VARCHAR(200) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legajos' AND COLUMN_NAME = 'retira1'
);
SET @ddl := IF(@has = 0, 'ALTER TABLE `legajos` ADD COLUMN `retira1` VARCHAR(200) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'legajos' AND COLUMN_NAME = 'obs_web'
);
SET @ddl := IF(@has = 0, 'ALTER TABLE `legajos` ADD COLUMN `obs_web` TEXT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
