-- Amplía profesores.permisos_ia para soportar órdenes del catálogo hasta 127 (hoy máx. 64).
-- ADVERTENCIA: ALTER TABLE en profesores. Reversible con MODIFY VARCHAR(50) solo si ninguna cadena supera 50 caracteres.

ALTER TABLE `profesores` MODIFY `permisos_ia` VARCHAR(128) NULL DEFAULT NULL;
