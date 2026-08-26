-- =============================================================================
-- Tabla legacy preceptoresporcurso — asignación de preceptor(es) por curso y ciclo.
-- Aditiva: CREATE TABLE solo si no existe. Columnas opcionales si faltan.
-- No agrega idProfesores si ya existe idProfesor (esquema variable entre tenants).
-- Revisar antes de ejecutar.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `preceptoresporcurso` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `idCursos` INT NOT NULL,
    `idProfesores` INT NOT NULL,
    `idTerlec` INT NOT NULL,
    `idNivel` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ppc_curso_terlec_nivel` (`idCursos`, `idTerlec`, `idNivel`),
    KEY `idx_ppc_profesor` (`idProfesores`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- idTerlec (ciclo) si la tabla ya existía sin esa columna
SET @db := DATABASE();
SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'preceptoresporcurso' AND COLUMN_NAME = 'idTerlec'
);
SET @sql := IF(@tiene = 0, 'ALTER TABLE `preceptoresporcurso` ADD COLUMN `idTerlec` INT NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- idNivel si no existe ni idNivel ni idNiveles
SET @tieneNivel := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'preceptoresporcurso' AND COLUMN_NAME IN ('idNivel', 'idNiveles')
);
SET @sql := IF(@tieneNivel = 0, 'ALTER TABLE `preceptoresporcurso` ADD COLUMN `idNivel` INT NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Columna del preceptor: solo si no hay idProfesores ni idProfesor
SET @tieneProf := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'preceptoresporcurso' AND COLUMN_NAME IN ('idProfesores', 'idProfesor')
);
SET @sql := IF(@tieneProf = 0, 'ALTER TABLE `preceptoresporcurso` ADD COLUMN `idProfesores` INT NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Completar ciclo/nivel desde el curso cuando queden vacíos (solo si la columna existe)
SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'preceptoresporcurso' AND COLUMN_NAME = 'idTerlec'
);
SET @sql := IF(@tiene > 0,
    'UPDATE `preceptoresporcurso` ppc INNER JOIN `cursos` c ON c.Id = ppc.idCursos SET ppc.idTerlec = c.idTerlec WHERE ppc.idTerlec IS NULL OR ppc.idTerlec = 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'preceptoresporcurso' AND COLUMN_NAME = 'idNivel'
);
SET @sql := IF(@tiene > 0,
    'UPDATE `preceptoresporcurso` ppc INNER JOIN `cursos` c ON c.Id = ppc.idCursos SET ppc.idNivel = c.idNivel WHERE ppc.idNivel IS NULL OR ppc.idNivel = 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tiene := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'preceptoresporcurso' AND COLUMN_NAME = 'idNiveles'
);
SET @sql := IF(@tiene > 0,
    'UPDATE `preceptoresporcurso` ppc INNER JOIN `cursos` c ON c.Id = ppc.idCursos SET ppc.idNiveles = c.idNivel WHERE ppc.idNiveles IS NULL OR ppc.idNiveles = 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verificación:
-- SHOW CREATE TABLE preceptoresporcurso;
-- SELECT idCursos, idProfesores, idTerlec, idNivel FROM preceptoresporcurso LIMIT 10;
