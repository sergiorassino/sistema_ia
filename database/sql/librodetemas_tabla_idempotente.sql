-- Tabla legacy `librodetemas` (Libro de temas).
-- En IESS ya existe. Este script es idempotente para otros tenants que activen el módulo.
-- No altera tablas existentes: solo CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS `librodetemas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idMateria` int(11) NOT NULL,
  `fecha` date DEFAULT NULL,
  `claseNro` int(5) NOT NULL,
  `unidad` int(5) NOT NULL,
  `caracter` varchar(50) COLLATE utf8mb3_spanish_ci NOT NULL DEFAULT '',
  `temas` text COLLATE utf8mb3_spanish_ci NOT NULL,
  `actividades` text COLLATE utf8mb3_spanish_ci NOT NULL,
  `observaciones` text COLLATE utf8mb3_spanish_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;
