-- =============================================================================
-- Discriminación de cuotas — una fila por ítem de un curso
--   cuotasdetalle: idCuotas, idCursos, orden, nombre, importe
--
-- php artisan migrate crea la tabla si no existe.
-- Si quedó cuotasdetalle o cuotasdetalleimportes de una versión anterior,
-- borrarlas antes. Ese DROP elimina ítems e importes cargados.
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cuotasdetalle (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idCuotas INT NOT NULL,
  idCursos INT NOT NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  nombre VARCHAR(180) NOT NULL,
  importe DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY cuotasdetalle_cuota_curso_idx (idCuotas, idCursos)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
