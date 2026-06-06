-- Permiso orden 47: modificar legajos/matrículas en cualquier nivel pedagógico (usuario Administración).
-- Permite modificar legajos/matrículas en cualquier nivel pedagógico (sin selector de nivel).
-- Alcance: tabla permisos_ia. Reversible: DELETE FROM permisos_ia WHERE id = 47;

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`)
VALUES (
    47,
    47,
    'LEGAJOS ESTUDIANTES',
    'Nivel Administración: crear, editar, eliminar legajos y matrículas en Inicial, Primario y Secundario (cualquier nivel pedagógico del ciclo activo).'
)
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);
