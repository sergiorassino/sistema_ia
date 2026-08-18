-- Tablas nuevas: grupos personales de destinatarios (Comunicación institucional).
-- Revisar antes de ejecutar. Aditiva: no modifica tablas legacy ni otras com_*.
-- Un grupo pertenece a un usuario (profesores.id) y a un nivel; no se comparte entre niveles.

CREATE TABLE IF NOT EXISTS `com_grupos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `id_profesor` INT UNSIGNED NOT NULL,
    `id_nivel` INT UNSIGNED NOT NULL,
    `tipo_destinatario` VARCHAR(40) NOT NULL COMMENT 'familia o tipo:{id} de profesortipo',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_com_grupo_dueno_nombre` (`id_profesor`, `id_nivel`, `nombre`),
    KEY `idx_com_grupos_dueno` (`id_profesor`, `id_nivel`),
    KEY `idx_com_grupos_nivel_tipo` (`id_nivel`, `tipo_destinatario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `com_grupos_miembros` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_grupo` BIGINT UNSIGNED NOT NULL,
    `tipo_miembro` ENUM('legajo','profesor') NOT NULL,
    `id_legajo` INT UNSIGNED NULL,
    `id_profesor` INT UNSIGNED NULL,
    `nombre_snapshot` VARCHAR(150) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_com_grupo_legajo` (`id_grupo`, `id_legajo`),
    UNIQUE KEY `uq_com_grupo_profesor` (`id_grupo`, `id_profesor`),
    KEY `idx_com_gmiem_legajo` (`tipo_miembro`, `id_legajo`),
    KEY `idx_com_gmiem_profesor` (`tipo_miembro`, `id_profesor`),
    CONSTRAINT `com_grupos_miembros_id_grupo_foreign`
        FOREIGN KEY (`id_grupo`) REFERENCES `com_grupos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación:
-- SHOW CREATE TABLE com_grupos;
-- SHOW CREATE TABLE com_grupos_miembros;
