-- Remitente y destinatario del mensaje en auditoría de comunicaciones (idempotente).
-- No requiere sp_add_column_if_missing. Ejecutar con la BD del colegio seleccionada.

SET NAMES utf8mb4;

-- mensaje_remitente_snapshot
SET @col_remitente := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'com_auditoria'
      AND COLUMN_NAME = 'mensaje_remitente_snapshot'
);
SET @ddl_remitente := IF(
    @col_remitente = 0,
    'ALTER TABLE `com_auditoria` ADD COLUMN `mensaje_remitente_snapshot` varchar(200) DEFAULT NULL AFTER `mensaje_fecha_snapshot`',
    'SELECT 1'
);
PREPARE stmt_remitente FROM @ddl_remitente;
EXECUTE stmt_remitente;
DEALLOCATE PREPARE stmt_remitente;

-- mensaje_destinatario_snapshot
SET @col_dest := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'com_auditoria'
      AND COLUMN_NAME = 'mensaje_destinatario_snapshot'
);
SET @ddl_dest := IF(
    @col_dest = 0,
    'ALTER TABLE `com_auditoria` ADD COLUMN `mensaje_destinatario_snapshot` text DEFAULT NULL AFTER `mensaje_remitente_snapshot`',
    'SELECT 1'
);
PREPARE stmt_dest FROM @ddl_dest;
EXECUTE stmt_dest;
DEALLOCATE PREPARE stmt_dest;
