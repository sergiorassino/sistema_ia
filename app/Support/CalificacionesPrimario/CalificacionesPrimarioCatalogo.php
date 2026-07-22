<?php

namespace App\Support\CalificacionesPrimario;

use App\Models\Curso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de columnas (materias por `id`) y reglas de visibilidad del formulario legacy de primario.
 */
final class CalificacionesPrimarioCatalogo
{
    /** Etapa de planilla / PDF: apreciación final (`ic03`). */
    public const ETAPA_APRECIACION_FINAL = 9;

    public const CAMPO_FINAL_ETAPA_1 = 'ic01';

    public const CAMPO_FINAL_ETAPA_2 = 'ic02';

    public const CAMPO_ANUAL = 'ic03';

    public const CAMPO_INTENSIFICACION = 'dic';

    public const CAMPO_OBS_ETAPA_1 = 'obs01';

    public const CAMPO_OBS_ETAPA_2 = 'obs02';

    public const MAX_CARACTERES_OBS_CALIFICACION = 1500;

    /** @var list<string> */
    public const PARCIALES_ETAPA_1 = ['ic05', 'ic06', 'ic07', 'ic08', 'ic09', 'ic10'];

    /** @var list<string> */
    public const PARCIALES_ETAPA_2 = ['ic11', 'ic12', 'ic13', 'ic14', 'ic15', 'ic16'];

    /** Materias base (ord 1–14) para todos los grados. */
    public const ORD_BASE = 14;

    /** Ord extra si grado > 1. */
    public const ORD_CICLO_2 = 15;

    /** Ord extra si grado > 2. */
    public const ORD_CICLO_3 = 16;

    public const ORD_CICLO_4 = 17;

    private const GRADO_TEXTO = [
        'PRIMER GRADO' => 1,
        'SEGUNDO GRADO' => 2,
        'TERCER GRADO' => 3,
        'CUARTO GRADO' => 4,
        'QUINTO GRADO' => 5,
        'SEXTO GRADO' => 6,
        'PRIMERO' => 1,
        'SEGUNDO' => 2,
        'TERCERO' => 3,
        'CUARTO' => 4,
        'QUINTO' => 5,
        'SEXTO' => 6,
    ];

    /**
     * Grado numérico del plan (equivalente a `glo_ciclo` en ScriptCase).
     */
    public static function cicloDesdeCurso(Curso $curso): int
    {
        $curso->loadMissing('curplan');
        $texto = mb_strtoupper(trim((string) ($curso->curplan?->curPlanCurso ?? '')), 'UTF-8');

        if ($texto !== '' && isset(self::GRADO_TEXTO[$texto])) {
            return self::GRADO_TEXTO[$texto];
        }

        foreach (self::GRADO_TEXTO as $clave => $num) {
            if ($texto !== '' && str_contains($texto, $clave)) {
                return $num;
            }
        }

        if (preg_match('/\b([1-6])\s*(?:°|º|o)?\b/u', $texto, $m) === 1) {
            return max(1, min(6, (int) $m[1]));
        }

        return 1;
    }

    public static function maxOrdVisible(int $ciclo): int
    {
        if ($ciclo > 2) {
            return self::ORD_CICLO_4;
        }
        if ($ciclo > 1) {
            return self::ORD_CICLO_2;
        }

        return self::ORD_BASE;
    }

    /**
     * Materias del curso en el ciclo lectivo activo (`materias` del año vigente; misma fuente que secundario).
     *
     * @return Collection<int, object{id: int, ord: int, abrev: string, materia: string, infoCalif: int}>
     */
    public static function materiasParaCurso(int $idCurso, int $idNivel, int $idTerlec, int $ciclo): Collection
    {
        return self::consultaMateriasCurso($idCurso, $idNivel, $idTerlec, self::maxOrdVisible($ciclo));
    }

    /**
     * Todas las materias del curso (sin tope de `ord` por grado).
     * Usado por el boletín Montecristo para incluir extracurriculares institucionales con `ord` alto.
     *
     * @return Collection<int, object{id: int, ord: int, abrev: string, materia: string, infoCalif: int}>
     */
    public static function materiasParaCursoTodasOrd(int $idCurso, int $idNivel, int $idTerlec): Collection
    {
        return self::consultaMateriasCurso($idCurso, $idNivel, $idTerlec, null);
    }

    /**
     * Materias del curso en el ciclo lectivo activo para selectores de carga (sin tope de `ord` por grado).
     * Alineado a «Asignaturas del año»: incluye institucionales y filas con `ord` alto del año vigente.
     *
     * @return Collection<int, object{id: int, ord: int, abrev: string, materia: string, infoCalif: int}>
     */
    public static function materiasParaSelectorAnio(int $idCurso, int $idNivel, int $idTerlec): Collection
    {
        return self::consultaMateriasCurso($idCurso, $idNivel, $idTerlec, null, ordenarParaColumnasIpe: false);
    }

    /**
     * Materias para la planilla PDF: solo tabla `materias` del curso, orden `ord`, sin matplan ni tope por grado.
     * Las institucionales (`esInstitucional = 1`) quedan en bloque aparte (mismo criterio de orden dentro del bloque).
     *
     * @return array{
     *     curriculares: Collection<int, object{id: int, ord: int, abrev: string, materia: string, esInstitucional: int}>,
     *     institucionales: Collection<int, object{id: int, ord: int, abrev: string, materia: string, esInstitucional: int}>,
     *     columnas: Collection<int, object{id: int, ord: int, abrev: string, materia: string, esInstitucional: int}>
     * }
     */
    public static function materiasParaPlanilla(int $idCurso, int $idNivel, int $idTerlec): array
    {
        $todas = self::consultaMateriasCurso($idCurso, $idNivel, $idTerlec, null, ordenarParaColumnasIpe: false);

        $curriculares = $todas
            ->filter(fn (object $m): bool => (int) ($m->esInstitucional ?? 0) !== 1)
            ->values();

        $institucionales = $todas
            ->filter(fn (object $m): bool => (int) ($m->esInstitucional ?? 0) === 1)
            ->values();

        return [
            'curriculares' => $curriculares,
            'institucionales' => $institucionales,
            'columnas' => $curriculares->concat($institucionales),
        ];
    }

    /**
     * @return Collection<int, object{id: int, ord: int, abrev: string, materia: string, infoCalif: int}>
     */
    private static function consultaMateriasCurso(
        int $idCurso,
        int $idNivel,
        int $idTerlec,
        ?int $maxOrd,
        bool $ordenarParaColumnasIpe = true,
    ): Collection {
        $cursoOk = Curso::query()
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('Id', $idCurso)
            ->exists();

        if (! $cursoOk) {
            return collect();
        }

        $columnas = ['id', 'ord', 'abrev', 'materia'];
        if (Schema::hasColumn('materias', 'esInstitucional')) {
            $columnas[] = 'esInstitucional';
        }
        if (Schema::hasColumn('materias', 'infoCalif')) {
            $columnas[] = 'infoCalif';
        }
        if (Schema::hasColumn('materias', 'escala')) {
            $columnas[] = 'escala';
        }

        $query = DB::table('materias')
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('idCursos', $idCurso);

        if ($maxOrd !== null) {
            $query->where('ord', '<=', $maxOrd);
        }

        $materias = $query
            ->orderBy('ord')
            ->orderBy('id')
            ->get($columnas)
            ->map(fn ($r) => (object) [
                'id' => (int) $r->id,
                'ord' => (int) $r->ord,
                'abrev' => trim((string) ($r->abrev ?? '')),
                'materia' => trim((string) ($r->materia ?? '')),
                'esInstitucional' => (int) ($r->esInstitucional ?? 0),
                'infoCalif' => (int) ($r->infoCalif ?? 0),
                'escala' => CalificacionesPrimarioNotasPermitidas::normalizarEscala($r->escala ?? 1),
            ]);

        return $ordenarParaColumnasIpe
            ? self::ordenarMateriasParaColumnas($materias)
            : self::ordenarMateriasPorOrd($materias);
    }

    /**
     * Orden estricto por `ord` (y `id` como desempate).
     *
     * @param  Collection<int, object{id: int, ord: int, abrev?: string, materia?: string, infoCalif?: int}>  $materias
     * @return Collection<int, object{id: int, ord: int, abrev?: string, materia?: string, infoCalif?: int}>
     */
    public static function ordenarMateriasPorOrd(Collection $materias): Collection
    {
        return $materias
            ->sortBy(fn (object $m) => [(int) $m->ord, (int) $m->id])
            ->values();
    }

    /**
     * Orden de columnas alineado al IPE: por `ord` y, si hay extracurriculares (`esInstitucional`), al final del bloque curricular.
     *
     * @param  Collection<int, object{id: int, ord: int, abrev: string, materia: string, esInstitucional?: int, infoCalif?: int}>  $materias
     * @return Collection<int, object{id: int, ord: int, abrev: string, materia: string, esInstitucional: int, infoCalif?: int}>
     */
    public static function ordenarMateriasParaColumnas(Collection $materias): Collection
    {
        $ordenadas = self::ordenarMateriasPorOrd($materias);

        $tieneExtracurricular = $ordenadas->contains(
            fn (object $m): bool => (int) ($m->esInstitucional ?? 0) === 1,
        );

        if (! $tieneExtracurricular) {
            return $ordenadas;
        }

        $curriculares = $ordenadas
            ->filter(fn (object $m): bool => (int) ($m->esInstitucional ?? 0) !== 1)
            ->values();

        $extracurriculares = $ordenadas
            ->filter(fn (object $m): bool => (int) ($m->esInstitucional ?? 0) === 1)
            ->values();

        return $curriculares->concat($extracurriculares);
    }

    /**
     * Abreviatura para el encabezado de columna (ORAL, LECT, MA.OP, etc.).
     */
    public static function resolverAbrevEncabezado(string ...$candidatos): string
    {
        foreach ($candidatos as $c) {
            $t = trim($c);
            if ($t !== '') {
                return $t;
            }
        }

        return '';
    }

    /**
     * Texto para `<select>` de materia: nombre completo y abreviatura al final entre paréntesis.
     *
     * @param  object|array{id?: int, ord?: int, abrev?: string, materia?: string}  $materia
     */
    public static function etiquetaSelectorMateria(object|array $materia): string
    {
        $nombre = is_array($materia)
            ? trim((string) ($materia['materia'] ?? ''))
            : trim((string) ($materia->materia ?? ''));
        $ord = is_array($materia)
            ? (int) ($materia['ord'] ?? 0)
            : (int) ($materia->ord ?? 0);
        $abrev = is_array($materia)
            ? trim((string) ($materia['abrev'] ?? ''))
            : trim((string) ($materia->abrev ?? ''));

        $base = $nombre !== '' ? $nombre : ($ord > 0 ? 'Ord '.$ord : '—');

        return $abrev !== '' ? $base.' ('.$abrev.')' : $base;
    }

    /**
     * Texto visible en el `<th>`: solo abreviatura (tooltip con nombre completo en la vista).
     *
     * @param  object|array{id?: int, ord?: int, abrev?: string, materia?: string}  $materia
     */
    public static function etiquetaEncabezadoColumna(object|array $materia): string
    {
        $abrev = is_array($materia)
            ? trim((string) ($materia['abrev'] ?? ''))
            : trim((string) ($materia->abrev ?? ''));

        return $abrev !== '' ? $abrev : '—';
    }

    /** @deprecated Use etiquetaEncabezadoColumna() */
    public static function etiquetaColumna(object $materia): string
    {
        return self::etiquetaEncabezadoColumna($materia);
    }

    /** @deprecated Sin celdas bloqueadas: la nota anual (ic03) se carga manualmente en todas las materias. */
    public static function celdaInhabilitada(int $ciclo, int $ord, string $campo): bool
    {
        return false;
    }

    /** Etapas seleccionables en la carga por materia (1ª y 2ª). */
    public static function normalizarEtapaCargaMateria(int $etapa): int
    {
        return $etapa === 2 ? 2 : 1;
    }

    public static function normalizarEtapaPlanilla(int $etapa): int
    {
        return match ($etapa) {
            2 => 2,
            self::ETAPA_APRECIACION_FINAL => self::ETAPA_APRECIACION_FINAL,
            default => 1,
        };
    }

    /** @return list<string> */
    public static function parcialesEtapa(int $etapa): array
    {
        return self::normalizarEtapaCargaMateria($etapa) === 2
            ? self::PARCIALES_ETAPA_2
            : self::PARCIALES_ETAPA_1;
    }

    public static function finalEtapa(int $etapa): string
    {
        return self::normalizarEtapaCargaMateria($etapa) === 2
            ? self::CAMPO_FINAL_ETAPA_2
            : self::CAMPO_FINAL_ETAPA_1;
    }

    public static function campoAnual(): string
    {
        return self::CAMPO_ANUAL;
    }

    /**
     * Campo de nota de etapa para planilla / boletín según selector de etapa.
     */
    public static function campoNotaEtapa(int $etapa): string
    {
        return match (self::normalizarEtapaPlanilla($etapa)) {
            2 => self::CAMPO_FINAL_ETAPA_2,
            self::ETAPA_APRECIACION_FINAL => self::CAMPO_ANUAL,
            default => self::CAMPO_FINAL_ETAPA_1,
        };
    }

    public static function etiquetaEtapaPlanilla(int $etapa): string
    {
        return match (self::normalizarEtapaPlanilla($etapa)) {
            2 => 'SEGUNDA',
            self::ETAPA_APRECIACION_FINAL => 'APRECIACIÓN FINAL',
            default => 'PRIMERA',
        };
    }

    public static function campoObservacionMatriculaEtapa(int $etapa): string
    {
        return self::normalizarEtapaCargaMateria($etapa) === 2 ? 'obs2' : 'obs1';
    }

    /**
     * Columnas de inasistencias en `matricula` para la planilla PDF según etapa.
     * Apreciación final no tiene par just/inju anual → sin campos.
     *
     * @return array{just: ?string, inju: ?string}
     */
    public static function camposInasistenciasPlanilla(int $etapa): array
    {
        return match (self::normalizarEtapaPlanilla($etapa)) {
            2 => ['just' => 'just2', 'inju' => 'inju2'],
            self::ETAPA_APRECIACION_FINAL => ['just' => null, 'inju' => null],
            default => ['just' => 'just1', 'inju' => 'inju1'],
        };
    }

    /** @return list<string> */
    public static function camposNotaTodos(): array
    {
        return array_values(array_unique(array_merge(
            [self::CAMPO_FINAL_ETAPA_1, self::CAMPO_FINAL_ETAPA_2, self::CAMPO_ANUAL],
            self::PARCIALES_ETAPA_1,
            self::PARCIALES_ETAPA_2,
        )));
    }

    /**
     * Texto de síntesis a partir de parciales no vacíos (boletín Montecristo y similares).
     *
     * @param  array<string, string>  $notasPorCampo
     */
    public static function formatearParcialesEtapa(array $notasPorCampo, int $etapa): string
    {
        $cols = self::columnasGrillaMateria($etapa)['parciales'];
        $partes = [];
        foreach ($cols as $col) {
            $campo = (string) $col['campo'];
            $valor = trim((string) ($notasPorCampo[$campo] ?? ''));
            if ($valor === '') {
                continue;
            }
            $partes[] = trim((string) $col['etiqueta']).': '.$valor;
        }

        return implode(' · ', $partes);
    }

    /**
     * Columnas de la grilla por materia según etapa (mapeo GE / legacy primario).
     *
     * @return array{
     *     parciales: list<array{campo: string, etiqueta: string}>,
     *     finalEtapa: array{campo: string, etiqueta: string},
     *     anual: ?array{campo: string, etiqueta: string},
     *     intensificacion: ?array{campo: string, etiqueta: string},
     *     obs: array{campo: string, etiqueta: string}
     * }
     */
    public static function columnasGrillaMateria(int $etapa): array
    {
        $etapa = self::normalizarEtapaCargaMateria($etapa);

        if ($etapa === 2) {
            return [
                'parciales' => [
                    ['campo' => 'ic11', 'etiqueta' => 'Eval. 1'],
                    ['campo' => 'ic12', 'etiqueta' => 'Eval. 2'],
                    ['campo' => 'ic13', 'etiqueta' => 'Eval. 3'],
                    ['campo' => 'ic14', 'etiqueta' => 'Eval. 4'],
                    ['campo' => 'ic15', 'etiqueta' => 'Eval. 5'],
                    ['campo' => 'ic16', 'etiqueta' => 'Eval. 6'],
                ],
                'finalEtapa' => ['campo' => self::CAMPO_FINAL_ETAPA_2, 'etiqueta' => 'CALIF. 2º ETAPA'],
                'anual' => ['campo' => self::CAMPO_ANUAL, 'etiqueta' => 'APREC. FINAL'],
                'intensificacion' => ['campo' => self::CAMPO_INTENSIFICACION, 'etiqueta' => 'Intensif.'],
                'obs' => ['campo' => self::CAMPO_OBS_ETAPA_2, 'etiqueta' => 'Obs. etapa'],
            ];
        }

        return [
            'parciales' => [
                ['campo' => 'ic05', 'etiqueta' => 'Eval. 1'],
                ['campo' => 'ic06', 'etiqueta' => 'Eval. 2'],
                ['campo' => 'ic07', 'etiqueta' => 'Eval. 3'],
                ['campo' => 'ic08', 'etiqueta' => 'Eval. 4'],
                ['campo' => 'ic09', 'etiqueta' => 'Eval. 5'],
                ['campo' => 'ic10', 'etiqueta' => 'Eval. 6'],
            ],
            'finalEtapa' => ['campo' => self::CAMPO_FINAL_ETAPA_1, 'etiqueta' => 'CALIF. 1º ETAPA'],
            'anual' => null,
            'intensificacion' => null,
            'obs' => ['campo' => self::CAMPO_OBS_ETAPA_1, 'etiqueta' => 'Obs. etapa'],
        ];
    }

    /** @return list<string> */
    public static function camposNotaGrillaMateria(int $etapa): array
    {
        $cols = self::columnasGrillaMateria($etapa);
        $campos = array_column($cols['parciales'], 'campo');
        $campos[] = $cols['finalEtapa']['campo'];
        if ($cols['anual'] !== null) {
            $campos[] = $cols['anual']['campo'];
        }
        if ($cols['intensificacion'] !== null) {
            $campos[] = $cols['intensificacion']['campo'];
        }

        return $campos;
    }

    public static function esCampoNotaGrillaPersistible(string $campo): bool
    {
        return in_array($campo, self::camposNotaEditables(), true)
            || $campo === self::CAMPO_INTENSIFICACION;
    }

    /** Notas + observación de etapa visibles en la grilla por materia. */
    public static function camposGrillaMateriaEditables(int $etapa): array
    {
        $campos = self::camposNotaGrillaMateria($etapa);
        $campos[] = self::columnasGrillaMateria($etapa)['obs']['campo'];

        return $campos;
    }

    /** @return list<string> */
    public static function camposObservacionCalificacion(): array
    {
        return [self::CAMPO_OBS_ETAPA_1, self::CAMPO_OBS_ETAPA_2];
    }

    public static function esCampoObservacionCalificacion(string $campo): bool
    {
        return in_array($campo, self::camposObservacionCalificacion(), true);
    }

    public static function campoObsCalificacionPorEtapa(int $etapa): string
    {
        return self::normalizarEtapaCargaMateria($etapa) === 2
            ? self::CAMPO_OBS_ETAPA_2
            : self::CAMPO_OBS_ETAPA_1;
    }

    /** @return list<string> */
    public static function camposNotaEditables(): array
    {
        return self::camposNotaTodos();
    }

    /** @return list<string> */
    public static function camposObservacionMatricula(): array
    {
        return ['obs1', 'obs2', 'obsAnual'];
    }
}
