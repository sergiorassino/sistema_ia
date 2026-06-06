-- Canales de comunicación: roles por profesortipo (familia + tipo:{id})
-- Ejecutar en cada entorno tras respaldar `com_canales`.
-- Alcance: modifica columnas rol_emisor/rol_receptor y expande filas legacy (directivo|preceptor|profesor|familia)
-- a combinaciones tipo:{id}. Irreversible sin restaurar backup.

ALTER TABLE `com_canales`
  MODIFY `rol_emisor` VARCHAR(64) NOT NULL,
  MODIFY `rol_receptor` VARCHAR(64) NOT NULL;

-- La expansión de datos legacy (cada canal agregado → producto de tipos emisor × receptor)
-- debe ejecutarse con la migración 2026_05_29_120000_com_canales_roles_por_profesortipo.php
-- o replicar su lógica en un script controlado; no se incluye SQL estático del producto cartesiano
-- porque depende de los registros de `profesortipo` de cada colegio.

-- Alternativa en desarrollo:
-- php artisan migrate --path=database/migrations/2026_05_29_120000_com_canales_roles_por_profesortipo.php
