-- =============================================================================
-- Seed de filas faltantes en `calificaciones` — Inicial 2026 (caixalsf)
-- =============================================================================
-- NO ejecutar desde el asistente: revisar y correr en el cliente MySQL del tenant.
--
-- Motivo: materias dadas de alta después de matricular no generan filas;
-- la carga de notas/obs ya no crea registros on-the-fly (solo UPDATE).
--
-- Equivalente a LegajoForm::seedCalificacionesForMatricula() para pares faltantes.
-- Idempotente: solo inserta donde no existe idMatricula + idMaterias.
--
-- Alcance:
--   • idNivel = 1 (Inicial)
--   • terlec.ano = 2026
--   • matrículas activas (fechaBaja IS NULL)
-- =============================================================================

-- 1) Vista previa
SELECT
    m.id              AS idMatricula,
    m.idLegajos,
    m.idCursos,
    mat.id            AS idMaterias,
    mat.ord,
    mat.materia,
    COALESCE(mat.idMatPlan, 0) AS idMatPlan
FROM matricula AS m
INNER JOIN terlec AS t
    ON t.id = m.idTerlec
INNER JOIN materias AS mat
    ON mat.idNivel = m.idNivel
   AND mat.idTerlec = m.idTerlec
   AND mat.idCursos = m.idCursos
LEFT JOIN calificaciones AS c
    ON c.idMatricula = m.id
   AND c.idMaterias = mat.id
WHERE
    m.idNivel = 1
    AND t.ano = 2026
    AND m.fechaBaja IS NULL
    AND c.id IS NULL
ORDER BY m.idCursos, m.id, mat.ord, mat.id;

-- 2) INSERT (backup de calificaciones recomendado antes)
INSERT INTO calificaciones (
    idLegajos,
    idMatricula,
    ord,
    idTerlec,
    idCursos,
    idMaterias,
    idMatPlan
)
SELECT
    m.idLegajos,
    m.id,
    mat.ord,
    m.idTerlec,
    m.idCursos,
    mat.id,
    COALESCE(mat.idMatPlan, 0)
FROM matricula AS m
INNER JOIN terlec AS t
    ON t.id = m.idTerlec
INNER JOIN materias AS mat
    ON mat.idNivel = m.idNivel
   AND mat.idTerlec = m.idTerlec
   AND mat.idCursos = m.idCursos
LEFT JOIN calificaciones AS c
    ON c.idMatricula = m.id
   AND c.idMaterias = mat.id
WHERE
    m.idNivel = 1
    AND t.ano = 2026
    AND m.fechaBaja IS NULL
    AND c.id IS NULL;
