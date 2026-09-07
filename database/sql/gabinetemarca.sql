-- Tabla gabinetemarca — semáforo (verde / amarillo / rojo) del seguimiento de gabinete.
-- Una fila por legajo. color: 0 sin marca, 1 verde, 2 amarillo, 3 rojo.
-- Revisar antes de ejecutar. No toca gabinete ni gabinetetipo.
-- Pack completo (gabinetetipo + gabinete + gabinetemarca): database/sql/gabinete_tablas_idempotente.sql

CREATE TABLE IF NOT EXISTS `gabinetemarca` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idLegajos` INT UNSIGNED NOT NULL,
  `color` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gabinetemarca_legajo` (`idLegajos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verificación:
-- SHOW COLUMNS FROM gabinetemarca;
