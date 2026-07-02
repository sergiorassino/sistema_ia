-- =============================================================================
-- Tabla `ento` — campos de facturación AFIP (idempotente, re-ejecutable)
-- Referencia: esquema legacy con columnas para emisión/consulta WSFE
--
-- Columnas:
--   condicionIva, ptoVta, afipCertCarpeta, afipCertKey, afipCertCrt,
--   ingresosBrutos, fechaInicioAct
--
-- Uso: ejecutar en la base del colegio (phpMyAdmin, HeidiSQL, mysql CLI).
-- Compatible con MySQL 5.7+ / MariaDB 10.x
--
-- Verificar antes:
--   SHOW COLUMNS FROM ento LIKE 'condicionIva';
--   SHOW COLUMNS FROM ento LIKE 'ptoVta';
--
-- ADVERTENCIA: solo agrega columnas que falten. No altera tipos ni datos existentes.
-- Si el colegio ya tiene ptoVta como smallint unsigned (migración Laravel), se conserva.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Utilidad: agregar columna solo si no existe
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE sp_add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT(
            'ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition
        );
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- -----------------------------------------------------------------------------
-- Datos fiscales / comprobante (legacy ScriptCase)
-- -----------------------------------------------------------------------------
CALL sp_add_column_if_missing('ento', 'condicionIva', "varchar(50) NULL DEFAULT NULL");
CALL sp_add_column_if_missing('ento', 'ptoVta', "int(5) NULL DEFAULT NULL");
CALL sp_add_column_if_missing('ento', 'afipCertCarpeta', "varchar(40) NULL DEFAULT NULL");
CALL sp_add_column_if_missing('ento', 'afipCertKey', "varchar(120) NULL DEFAULT NULL");
CALL sp_add_column_if_missing('ento', 'afipCertCrt', "varchar(120) NULL DEFAULT NULL");
CALL sp_add_column_if_missing('ento', 'ingresosBrutos', "varchar(10) NULL DEFAULT NULL");
CALL sp_add_column_if_missing('ento', 'fechaInicioAct', "varchar(15) NULL DEFAULT NULL");

DROP PROCEDURE IF EXISTS sp_add_column_if_missing;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- Tras aplicar, completar valores en Parámetros del sistema (facturación AFIP).
-- =============================================================================
