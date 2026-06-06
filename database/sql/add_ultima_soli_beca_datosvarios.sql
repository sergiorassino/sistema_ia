-- Numeración de solicitudes de ayuda familiar (legacy datosvarios.ultimaSoliBeca).
-- Ejecutar solo si la columna aún no existe en el servidor.

ALTER TABLE `datosvarios`
    ADD COLUMN `ultimaSoliBeca` INT UNSIGNED NOT NULL DEFAULT 0
    AFTER `ultimoComprobante`;
