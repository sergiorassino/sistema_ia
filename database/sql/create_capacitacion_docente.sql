-- Tabla nueva: cursos de capacitación docente (módulo Capacitación Docente).
-- Revisar antes de ejecutar. Aditiva: no modifica tablas legacy.

CREATE TABLE IF NOT EXISTS `capacitacion_docente` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_profesor` INT UNSIGNED NOT NULL,
    `id_nivel` INT UNSIGNED NOT NULL,
    `fecha` DATE NOT NULL,
    `nombre` VARCHAR(255) NOT NULL,
    `entidad_otorgante` VARCHAR(255) NOT NULL,
    `duracion` VARCHAR(80) NOT NULL,
    `modalidad` VARCHAR(20) NOT NULL COMMENT 'presencial | virtual | hibrida',
    `certificado_archivo` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `idx_cap_doc_nivel_prof_fecha` (`id_nivel`, `id_profesor`, `fecha`),
    KEY `idx_cap_doc_nivel_fecha` (`id_nivel`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación:
-- SHOW CREATE TABLE capacitacion_docente;
