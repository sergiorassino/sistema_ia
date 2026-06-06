-- Elimina tabla com_hilos_participantes (nunca usada en producción).
-- Idempotente: no falla si la tabla ya no existe.

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `com_hilos_participantes`;
