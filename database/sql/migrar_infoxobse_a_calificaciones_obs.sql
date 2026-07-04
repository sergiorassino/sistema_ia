-- =============================================================================
-- Migración de DATOS: infoxobse → calificaciones.obs01 / obs02
-- =============================================================================
-- NO es migración de esquema Laravel: no se ejecuta con migrate ni se:migrate-legacy.
-- Preferir el comando Artisan:
--   php artisan se:migrar-infoxobse-a-calificaciones-obs --dry-run
--
-- Alcance por defecto (equivalente al comando sin --todos-ciclos):
--   • Matrículas activas (fechaBaja IS NULL)
--   • Ciclo lectivo actual = MAX(terlec.ano)
--
-- IMPORTANTE: hacer BACKUP de calificaciones e infoxobse antes de ejecutar.
-- =============================================================================

-- Verificar ciclo que se migrará:
-- SELECT id, ano, orden FROM terlec WHERE ano = (SELECT MAX(ano) FROM terlec);

-- ── 1) Actualizar filas existentes emparejadas por idMatricula + idMaterias ──
UPDATE calificaciones AS c
INNER JOIN infoxobse AS i
    ON i.idMatricula = c.idMatricula
   AND i.idMaterias = c.idMaterias
INNER JOIN matricula AS m
    ON m.id = i.idMatricula
INNER JOIN terlec AS t
    ON t.id = m.idTerlec
SET
    c.obs01 = CASE
        WHEN TRIM(COALESCE(i.etapa1, '')) = '' THEN c.obs01
        WHEN TRIM(COALESCE(c.obs01, '')) = '' THEN TRIM(i.etapa1)
        WHEN TRIM(c.obs01) = TRIM(i.etapa1) THEN c.obs01
        ELSE c.obs01
    END,
    c.obs02 = CASE
        WHEN TRIM(COALESCE(i.etapa2, '')) = '' THEN c.obs02
        WHEN TRIM(COALESCE(c.obs02, '')) = '' THEN TRIM(i.etapa2)
        WHEN TRIM(c.obs02) = TRIM(i.etapa2) THEN c.obs02
        ELSE c.obs02
    END
WHERE
    m.fechaBaja IS NULL
    AND t.ano = (SELECT MAX(ano) FROM terlec)
    AND (TRIM(COALESCE(i.etapa1, '')) <> '' OR TRIM(COALESCE(i.etapa2, '')) <> '')
    -- AND m.idNivel = 2   -- descomentar para solo primario
;

-- ── 2) Actualizar filas legacy emparejadas por idMatricula + ord ─────────────
UPDATE calificaciones AS c
INNER JOIN infoxobse AS i
    ON i.idMatricula = c.idMatricula
INNER JOIN matricula AS m
    ON m.id = i.idMatricula
INNER JOIN terlec AS t
    ON t.id = m.idTerlec
INNER JOIN materias AS mat
    ON mat.id = i.idMaterias
   AND mat.idCursos = m.idCursos
   AND mat.idNivel = m.idNivel
   AND mat.idTerlec = m.idTerlec
SET
    c.obs01 = CASE
        WHEN TRIM(COALESCE(i.etapa1, '')) = '' THEN c.obs01
        WHEN TRIM(COALESCE(c.obs01, '')) = '' THEN TRIM(i.etapa1)
        WHEN TRIM(c.obs01) = TRIM(i.etapa1) THEN c.obs01
        ELSE c.obs01
    END,
    c.obs02 = CASE
        WHEN TRIM(COALESCE(i.etapa2, '')) = '' THEN c.obs02
        WHEN TRIM(COALESCE(c.obs02, '')) = '' THEN TRIM(i.etapa2)
        WHEN TRIM(c.obs02) = TRIM(i.etapa2) THEN c.obs02
        ELSE c.obs02
    END,
    c.idMaterias = mat.id
WHERE
    c.ord = mat.ord
    AND (c.idMaterias IS NULL OR c.idMaterias = 0)
    AND m.fechaBaja IS NULL
    AND t.ano = (SELECT MAX(ano) FROM terlec)
    AND (TRIM(COALESCE(i.etapa1, '')) <> '' OR TRIM(COALESCE(i.etapa2, '')) <> '')
    -- AND m.idNivel = 2
;

-- ── 3) Insertar filas nuevas cuando no existe calificación para esa materia ───
INSERT INTO calificaciones (
    idMatricula,
    idLegajos,
    idTerlec,
    idCursos,
    idMaterias,
    ord,
    obs01,
    obs02,
    ic01,
    ic02,
    ic03
)
SELECT
    m.id,
    m.idLegajos,
    m.idTerlec,
    m.idCursos,
    mat.id,
    mat.ord,
    TRIM(COALESCE(i.etapa1, '')),
    TRIM(COALESCE(i.etapa2, '')),
    '',
    '',
    ''
FROM infoxobse AS i
INNER JOIN matricula AS m
    ON m.id = i.idMatricula
INNER JOIN terlec AS t
    ON t.id = m.idTerlec
INNER JOIN materias AS mat
    ON mat.id = i.idMaterias
   AND mat.idCursos = m.idCursos
   AND mat.idNivel = m.idNivel
   AND mat.idTerlec = m.idTerlec
LEFT JOIN calificaciones AS c
    ON c.idMatricula = m.id
   AND (
        c.idMaterias = mat.id
        OR (
            c.ord = mat.ord
            AND (c.idMaterias IS NULL OR c.idMaterias = 0)
        )
   )
WHERE
    c.id IS NULL
    AND m.fechaBaja IS NULL
    AND t.ano = (SELECT MAX(ano) FROM terlec)
    AND (TRIM(COALESCE(i.etapa1, '')) <> '' OR TRIM(COALESCE(i.etapa2, '')) <> '')
    -- AND m.idNivel = 2
;
