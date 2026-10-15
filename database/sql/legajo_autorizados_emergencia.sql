-- =============================================================================
-- Tabla `legajo_autorizados_emergencia`
-- Personas autorizadas para comunicación y retiro del estudiante.
-- Solo la usa San Francisco de Asís
-- (`autogestion.actualizacion_datos.contactos_emergencia_unificados = true`).
--
-- Uso preferido: php artisan migrate
--   (migración 2026_10_15_103000_create_legajo_autorizados_emergencia_table.php)
-- Alternativa manual: ejecutar este SQL en la BD del tenant sanfranciscoasis.
--
-- No copia contacto1 / contacto2 / contacto3 / retira1: esos textos no traen DNI
-- ni campos separados. Esos datos quedan en `legajos` hasta que la familia
-- cargue la lista nueva.
--
-- ADVERTENCIA: crea una tabla nueva. No modifica `legajos` ni borra datos.
-- Alcance: base del tenant conectado. Si la tabla ya existe, no hace nada.
-- Irreversible solo si después se ejecuta DROP TABLE.
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `legajo_autorizados_emergencia` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idLegajo` INT UNSIGNED NOT NULL,
  `orden` TINYINT UNSIGNED NOT NULL,
  `apellido_nombre` VARCHAR(120) NOT NULL,
  `dni` VARCHAR(11) NOT NULL,
  `relacion` VARCHAR(60) NOT NULL,
  `telefono` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_legajo_aut_emerg_orden` (`idLegajo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
