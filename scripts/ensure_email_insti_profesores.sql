-- Agrega profesores.emailInsti en colegios legacy que no la tienen.
-- Idempotente: ejecutar solo si la columna no existe.
--
-- Verificar antes:
--   SHOW COLUMNS FROM profesores LIKE 'emailInsti';
--
-- Si no devuelve filas, aplicar:

ALTER TABLE `profesores`
    ADD COLUMN `emailInsti` VARCHAR(100) NULL DEFAULT NULL AFTER `email`;

-- Si la columna `email` no existe en ese colegio, usar en su lugar:
-- ALTER TABLE `profesores`
--     ADD COLUMN `emailInsti` VARCHAR(100) NULL DEFAULT NULL;
