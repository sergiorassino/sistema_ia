-- =============================================================================
-- Tablas `emails_escritos` / `emails_enviados` — creación idempotente
-- Referencia: esquema de ia_colegiofader (módulo Correo masivo a estudiantes)
--
-- Uso preferido: php artisan se:migrate-legacy --force
--   (migración 2026_07_22_120000_create_emails_masivos_tables_if_missing.php)
-- Alternativa manual: ejecutar este SQL en phpMyAdmin / HeidiSQL / mysql CLI.
-- Compatible con MySQL 5.7+ / MariaDB 10.x
-- Sin DELIMITER ni procedimientos almacenados (ejecutable en un solo lote).
--
-- Verificar antes:
--   SHOW TABLES LIKE 'emails_%';
--   SHOW CREATE TABLE emails_escritos;
--   SHOW CREATE TABLE emails_enviados;
--
-- ADVERTENCIA: solo crea las tablas si faltan. No copia datos ni borra nada.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Mensajes redactados (borradores / plantillas de campaña)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `emails_escritos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(254) NOT NULL DEFAULT '',
  `text` text NOT NULL,
  `attached` varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- -----------------------------------------------------------------------------
-- Auditoría de envíos (un registro por destinatario / campaña)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `emails_enviados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mailDestino` varchar(100) NOT NULL DEFAULT '',
  `fechhora` datetime NOT NULL,
  `idProfesores` int(11) NOT NULL,
  `idLegajos` int(11) NOT NULL,
  `idCursos` int(11) NOT NULL,
  `idNiveles` int(11) NOT NULL,
  `idTerlec` int(11) NOT NULL,
  `subject` varchar(254) NOT NULL DEFAULT '',
  `texto` text NOT NULL,
  `attached` varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Fin. Puede ejecutarse varias veces sin error.
-- =============================================================================
