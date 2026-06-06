-- Permisos por ítem del Menú de Administración (aranceles, becas, mora).
-- ADVERTENCIA: inserta filas en permisos_ia (ids 49–64, órdenes 49–64). Reversible: DELETE FROM permisos_ia WHERE id BETWEEN 49 AND 64;

INSERT INTO `permisos_ia` (`id`, `orden`, `tema`, `descripcion`) VALUES
(49, 49, 'GESTIÓN DE ARANCELES', 'Aranceles por estudiante: búsqueda, cuotas generadas, pagos, comprobantes y resumen de pagos.'),
(50, 50, 'GESTIÓN MASIVA DE CUOTAS', 'Crear y editar plantillas de cuotas del año lectivo activo.'),
(51, 51, 'GESTIÓN MASIVA DE CUOTAS', 'Importes y bonificaciones o intereses por curso.'),
(52, 52, 'GESTIÓN MASIVA DE CUOTAS', 'Generación masiva de cuotas para estudiantes regulares por curso.'),
(53, 53, 'GESTIÓN MASIVA DE CUOTAS', 'Eliminar masivamente cuotas generadas sin pagos por curso.'),
(54, 54, 'GESTIÓN MASIVA DE CUOTAS', 'Edición masiva de importes y vencimientos de cuotas generadas.'),
(55, 55, 'GESTIÓN MASIVA DE CUOTAS', 'Cancelar todas las reservas del ciclo (importe y saldo en cero).'),
(56, 56, 'RESÚMENES DE ARANCELES', 'Libro de aranceles por curso (PDF apaisado).'),
(57, 57, 'RESÚMENES DE ARANCELES', 'Listado de pagos recibidos entre dos fechas (PDF).'),
(58, 58, 'RESÚMENES DE ARANCELES', 'Listado de estudiantes con cuotas generadas (PDF apaisado).'),
(59, 59, 'BECAS', 'Tipos de beca y porcentaje de descuento.'),
(60, 60, 'BECAS', 'Asignar beca a alumnos por curso o búsqueda individual.'),
(61, 61, 'BECAS', 'Resumen de becas otorgadas por tipo y nivel pedagógico.'),
(62, 62, 'BECAS', 'Buscar estudiante e imprimir solicitud de ayuda familiar.'),
(63, 63, 'GESTIÓN DE MORA', 'Estado de deuda familiar: listado de familias y deuda.'),
(64, 64, 'GESTIÓN DE MORA', 'Gestión de morosos: filtros, listado de deuda (PDF) y notificaciones.')
ON DUPLICATE KEY UPDATE
    `orden` = VALUES(`orden`),
    `tema` = VALUES(`tema`),
    `descripcion` = VALUES(`descripcion`);

ALTER TABLE `permisos_ia` AUTO_INCREMENT = 65;
