-- Tipos de registro adicionales en proyectos extracurriculares.
-- Equivalente: database/migrations/2026_10_13_160000_seed_ext_tipo_registro_nivel_institucional.php
-- Revisar antes de ejecutar. No modifica filas existentes de actividades.

INSERT INTO `ext_tipo_registro` (`id`, `nombre`) VALUES
(2, 'Actividad de Nivel'),
(3, 'Actividad Institucional')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Verificación:
-- SELECT * FROM ext_tipo_registro ORDER BY id;
