-- Solo si se aplicó un despliegue intermedio con columnas matweb_* (ya no se usan).
-- El módulo usa documAcept1 … documAcept4. Ejecutar solo si esas columnas existen.

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ento' AND COLUMN_NAME = 'matweb_traslado_original_name') > 0,
  'ALTER TABLE `ento`
     DROP COLUMN `matweb_traslado_original_name`,
     DROP COLUMN `matweb_traslado_path`,
     DROP COLUMN `matweb_normas_original_name`,
     DROP COLUMN `matweb_normas_path`,
     DROP COLUMN `matweb_aec_original_name`,
     DROP COLUMN `matweb_aec_path`,
     DROP COLUMN `matweb_compromiso_original_name`,
     DROP COLUMN `matweb_compromiso_path`',
  'SELECT ''Columnas matweb_* no presentes; nada que hacer.'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
