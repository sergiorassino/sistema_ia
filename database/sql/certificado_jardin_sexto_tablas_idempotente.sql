-- Tablas legacy de datos comunes para certificados de finalización.
-- Aditivas: si ya existen (ScriptCase), no se modifican.
-- Revisar antes de ejecutar. No borra datos.

CREATE TABLE IF NOT EXISTS `certificadojardin` (
    `id` INT NOT NULL,
    `serie` VARCHAR(50) NOT NULL DEFAULT '',
    `mesApro` VARCHAR(40) NOT NULL DEFAULT '',
    `anoApro` VARCHAR(20) NOT NULL DEFAULT '',
    `diaEmision` VARCHAR(40) NOT NULL DEFAULT '',
    `mesEmision` VARCHAR(40) NOT NULL DEFAULT '',
    `anoEmision` VARCHAR(20) NOT NULL DEFAULT '',
    `ppi` VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `certificadosextogrado` (
    `id` INT NOT NULL,
    `serie` VARCHAR(50) NOT NULL DEFAULT '',
    `mesApro` VARCHAR(40) NOT NULL DEFAULT '',
    `anoApro` VARCHAR(20) NOT NULL DEFAULT '',
    `diaEmision` VARCHAR(40) NOT NULL DEFAULT '',
    `mesEmision` VARCHAR(40) NOT NULL DEFAULT '',
    `anoEmision` VARCHAR(20) NOT NULL DEFAULT '',
    `ppi` VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación:
-- SHOW CREATE TABLE certificadojardin;
-- SHOW CREATE TABLE certificadosextogrado;
