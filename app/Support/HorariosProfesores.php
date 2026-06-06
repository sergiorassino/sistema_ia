<?php

namespace App\Support;

use App\Models\Curso;
use App\Models\HorariosConfig;
use App\Models\TurnoClase;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Horarios de profesores (carga en horarios26, reloj, impresión por curso/docente).
 *
 * Modelo vigente: `horarios26.idHora` = módulo 1..10; `horarios26.idTurnoClase` = jornada (como `reloj` y `cursos`).
 * Compatibilidad lectura: filas sin `idTurnoClase` o con idHora 11–30 (bloques antiguos).
 */
final class HorariosProfesores
{
    public const HORAS_POR_TURNO = 10;

    /** Solo lectura/compat.: idHora 1–30 antes de normalizar por turno. */
    public const BLOQUES_HORARIO_LEGACY = 3;

    public const HORAS_RELOJ_MAX_LEGACY = 30;

    /** @var Collection<int, TurnoClase>|null */
    private static ?Collection $catalogoTurnosClaseCache = null;

    /** @var array<string, array{dias: list<int>, horas: list<int>, reloj: array<int, string>, celdas: array<string, list<string>>}> */
    private static array $grillaProfesorCache = [];

    /** @var array<string, bool> */
    private static array $profesorTieneCeldasCache = [];

    /** @var array<int, list<int>> */
    private static array $idsMateriasPermitidasProfesorCache = [];

    /** @var array<int, Collection> */
    private static array $asignacionesProfesorCache = [];

    /** @var array<string, string> */
    private static array $coDocenteCeldaCache = [];

    /** En PDF de muchos docentes no se retienen grillas en memoria estática. */
    private static bool $impresionPdfMasivaProfesores = false;

    /** @var array<int, string> */
    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /** @var array<int, string> */
    public const DIAS_CORTO = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mié',
        4 => 'Jue',
        5 => 'Vie',
        6 => 'Sáb',
        7 => 'Dom',
    ];

    /**
     * Valores de horarios26.idDia (varchar 3): lun, mar, mie, etc.
     * No usar números 1..7 en BD legacy.
     *
     * @var array<int, string>
     */
    public const DIA_LEGACY_CANONICO = [
        1 => 'lun',
        2 => 'mar',
        3 => 'mie',
        4 => 'jue',
        5 => 'vie',
        6 => 'sab',
        7 => 'dom',
    ];

    /**
     * Catálogo de turnos de clase (mañana, tarde, noche). No confundir con `turnos` (exámenes).
     *
     * @return Collection<int, TurnoClase>
     */
    public static function catalogoTurnosClase(): Collection
    {
        if (self::$catalogoTurnosClaseCache !== null) {
            return self::$catalogoTurnosClaseCache;
        }

        if (! Schema::hasTable('turnos_clase')) {
            self::$catalogoTurnosClaseCache = collect([
                (object) ['id' => 1, 'codigo' => 'manana', 'nombre' => 'Mañana', 'orden' => 1],
                (object) ['id' => 2, 'codigo' => 'tarde', 'nombre' => 'Tarde', 'orden' => 2],
                (object) ['id' => 3, 'codigo' => 'noche', 'nombre' => 'Noche', 'orden' => 3],
            ]);

            return self::$catalogoTurnosClaseCache;
        }

        self::$catalogoTurnosClaseCache = TurnoClase::query()
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return self::$catalogoTurnosClaseCache;
    }

    /**
     * @return array<int, string> id turnos_clase => nombre
     */
    public static function turnosClaseLabels(): array
    {
        $out = [];
        foreach (self::catalogoTurnosClase() as $t) {
            $out[(int) $t->id] = (string) ($t->nombre ?? '');
        }

        return $out;
    }

    public static function nombreTurnoClase(int $idTurnoClase): string
    {
        $row = self::catalogoTurnosClase()->firstWhere('id', $idTurnoClase);

        return trim((string) ($row->nombre ?? '')) !== ''
            ? trim((string) $row->nombre)
            : 'Turno';
    }

    /**
     * Nivel y ciclo para consultas de horarios (secretaría o portal alumno).
     *
     * @return array{0: int, 1: int}
     */
    private static function contextoHorariosNivelTerlec(?int $idNivel = null, ?int $idTerlec = null): array
    {
        if ($idNivel !== null && $idNivel > 0 && $idTerlec !== null && $idTerlec > 0) {
            return [$idNivel, $idTerlec];
        }

        $school = schoolCtx();
        if ($school->isValid()) {
            if ($idNivel === null || $idNivel <= 0) {
                $idNivel = (int) $school->idNivel;
            }
            if ($idTerlec === null || $idTerlec <= 0) {
                $idTerlec = (int) $school->idTerlec;
            }
        }

        if ($idNivel === null || $idNivel <= 0 || $idTerlec === null || $idTerlec <= 0) {
            $student = studentCtx();
            if ($student->isValid()) {
                if ($idNivel === null || $idNivel <= 0) {
                    $idNivel = (int) $student->idNivel;
                }
                if ($idTerlec === null || $idTerlec <= 0) {
                    $idTerlec = (int) $student->idTerlec;
                }
            }
        }

        return [(int) ($idNivel ?? 0), (int) ($idTerlec ?? 0)];
    }

    private static function idNivelHorariosSesion(?int $explicito = null): int
    {
        if ($explicito !== null && $explicito > 0) {
            return $explicito;
        }

        [$idNivel] = self::contextoHorariosNivelTerlec(null, null);

        return $idNivel;
    }

    /**
     * @return list<int> IDs de turnos_clase
     */
    public static function turnosActivos(?int $idNivel = null): array
    {
        $catalogo = self::catalogoTurnosClase();
        $validos = $catalogo->pluck('id')->map(fn ($id) => (int) $id)->all();
        $soloMadre = array_values(array_filter($validos, fn (int $t) => ! self::esTurnoClaseDobleJornada($t)));
        $default = $soloMadre !== [] ? [$soloMadre[0]] : [1];

        $idNivel = self::idNivelHorariosSesion($idNivel);
        if ($idNivel <= 0) {
            return $default;
        }

        $row = HorariosConfig::query()->find($idNivel);
        $raw = trim((string) ($row?->turnos_activos ?? (string) ($default[0] ?? 1)));

        $parsed = self::parseIdsList($raw, $validos);
        $parsed = array_values(array_intersect($parsed, $validos));
        $parsed = array_values(array_filter($parsed, fn (int $t) => ! self::esTurnoClaseDobleJornada($t)));

        return $parsed !== [] ? $parsed : $default;
    }

    /**
     * @return list<int>
     */
    public static function diasActivos(?int $idNivel = null): array
    {
        $idNivel = self::idNivelHorariosSesion($idNivel);
        if ($idNivel <= 0) {
            return [1, 2, 3, 4, 5];
        }

        $row = HorariosConfig::query()->find($idNivel);
        $raw = trim((string) ($row?->dias_activos ?? '1,2,3,4,5'));

        return self::parseIdsList($raw, array_keys(self::DIAS));
    }

    /**
     * Días habilitados como códigos legacy (lun, mar, …), alineados con horarios26.idDia.
     *
     * @return list<string>
     */
    public static function diasActivosLegacy(?int $idNivel = null): array
    {
        $out = [];
        foreach (self::diasActivos($idNivel) as $num) {
            $cod = self::DIA_LEGACY_CANONICO[$num] ?? null;
            if ($cod !== null) {
                $out[] = $cod;
            }
        }

        return $out !== [] ? $out : ['lun', 'mar', 'mie', 'jue', 'vie'];
    }

    /**
     * Normaliza cualquier valor de horarios26.idDia al código canónico (lun, mar, mie, …).
     */
    public static function normalizarIdDiaCanonico(string $legacy): ?string
    {
        $n = self::diaFromLegacy($legacy);
        if ($n < 1 || $n > 7) {
            return null;
        }

        return self::DIA_LEGACY_CANONICO[$n] ?? null;
    }

    public static function diaNumeroDesdeLegacy(string $diaCanon): int
    {
        $n = array_search($diaCanon, self::DIA_LEGACY_CANONICO, true);

        return $n !== false ? (int) $n : 0;
    }

    public static function etiquetaDiaLegacy(string $diaCanon): string
    {
        $n = self::diaNumeroDesdeLegacy($diaCanon);

        return $n > 0 ? (self::DIAS_CORTO[$n] ?? mb_strtoupper($diaCanon)) : mb_strtoupper($diaCanon);
    }

    public static function celdaKeyLegacy(string $diaCanon, int $hora): string
    {
        return $diaCanon.'-'.$hora;
    }

    /**
     * @param  list<int>  $turnos  IDs de turnos_clase
     * @param  list<int>  $dias
     */
    public static function guardarConfig(int $idNivel, array $turnos, array $dias): void
    {
        $validosTurnos = self::catalogoTurnosClase()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $turnos = array_values(array_intersect(
            self::parseIdsList(implode(',', $turnos), $validosTurnos),
            $validosTurnos,
        ));
        $turnos = array_values(array_filter($turnos, fn (int $t) => ! self::esTurnoClaseDobleJornada($t)));
        if ($turnos === []) {
            $solo = array_values(array_filter($validosTurnos, fn (int $t) => ! self::esTurnoClaseDobleJornada($t)));
            $turnos = $solo !== [] ? [$solo[0]] : [1];
        }

        $dias = self::parseIdsList(implode(',', $dias), array_keys(self::DIAS));

        HorariosConfig::query()->updateOrCreate(
            ['idNivel' => $idNivel],
            [
                'turnos_activos' => implode(',', $turnos),
                'dias_activos' => implode(',', $dias),
            ],
        );
    }

    /**
     * Posición 0..2 del bloque (mañana / tarde / noche) según el turno en el catálogo ordenado.
     */
    public static function indiceBloqueTurnoClase(int $idTurnoClase): int
    {
        $ids = self::catalogoTurnosClase()->pluck('id')->map(fn ($x) => (int) $x)->values()->all();
        $pos = array_search($idTurnoClase, $ids, true);

        return $pos === false ? 0 : min((int) $pos, self::BLOQUES_HORARIO_LEGACY - 1);
    }

    /** Offset 0, 10 o 20 solo para `horarios26.idHora` (bloques 1–30). No aplica a `reloj.orden`. */
    public static function baseHoraLegacyPorTurnoClase(int $idTurnoClase): int
    {
        return self::indiceBloqueTurnoClase($idTurnoClase) * self::HORAS_POR_TURNO;
    }

    /**
     * Valor `idHora` en BD (1..30): bloque del turno + módulo 1..10 de la grilla.
     */
    public static function idHoraLegacyDesdeTurnoYSlot(int $idTurnoClase, int $slot): int
    {
        $slot = max(1, min(self::HORAS_POR_TURNO, $slot));

        return self::baseHoraLegacyPorTurnoClase($idTurnoClase) + $slot;
    }

    /** Módulo 1..10 a partir de `idHora` legacy 1..30. */
    public static function slotDesdeIdHoraLegacy(int $idHora): int
    {
        if ($idHora < 1 || $idHora > self::HORAS_RELOJ_MAX_LEGACY) {
            return 0;
        }

        return (($idHora - 1) % self::HORAS_POR_TURNO) + 1;
    }

    /** Índice de bloque 0..2 desde `idHora` 1..30; -1 si fuera de rango. */
    public static function indiceBloqueDesdeIdHoraLegacy(int $idHora): int
    {
        if ($idHora < 1 || $idHora > self::HORAS_RELOJ_MAX_LEGACY) {
            return -1;
        }

        return intdiv($idHora - 1, self::HORAS_POR_TURNO);
    }

    public static function horarios26UsaIdTurnoClase(): bool
    {
        return Schema::hasColumn('horarios26', 'idTurnoClase');
    }

    /** Módulo 1..10 persistido en `horarios26.idHora` (sin offset por turno). */
    public static function slotHoraParaBd(int $slot): int
    {
        return max(1, min(self::HORAS_POR_TURNO, $slot));
    }

    /**
     * Turno de la celda al cargar: curso simple → cursos.idTurnoClase; doble jornada → banda mañana/tarde.
     */
    public static function idTurnoClaseParaCargaEnCelda(int $idCurso, int $indiceBloqueHorario = 0): int
    {
        $idTcCurso = self::idTurnoClaseParaCurso((int) (DB::table('cursos')->where('Id', $idCurso)->value('idTurnoClase') ?? 0) ?: null);
        if (self::esTurnoClaseDobleJornada($idTcCurso)) {
            $bandas = self::idsTurnoClaseBandasMananaTarde();

            return $bandas[max(0, min(1, $indiceBloqueHorario))];
        }

        return $idTcCurso;
    }

    /**
     * @return array{slot: int, idTurnoClase: int}
     */
    public static function interpretarFilaHorarios26(object $row, ?int $idCursoContexto = null): array
    {
        $idHora = (int) ($row->idHora ?? 0);
        if (self::horarios26UsaIdTurnoClase()) {
            $idTc = (int) ($row->horarioIdTurnoClase ?? 0);
            if ($idTc > 0 && $idHora >= 1 && $idHora <= self::HORAS_POR_TURNO) {
                return ['slot' => $idHora, 'idTurnoClase' => $idTc];
            }
        }

        $slot = self::slotDesdeIdHoraLegacy($idHora);
        $bloque = self::indiceBloqueDesdeIdHoraLegacy($idHora);
        $catalogo = self::catalogoTurnosClase()->values();
        $idTurnoClase = (int) ($catalogo->get($bloque)->id ?? $catalogo->first()->id ?? 1);

        if ($idHora >= 1 && $idHora <= self::HORAS_POR_TURNO && $idCursoContexto > 0) {
            $fk = (int) (DB::table('cursos')->where('Id', $idCursoContexto)->value('idTurnoClase') ?? 0);
            if ($fk > 0) {
                $idTurnoClase = self::idTurnoClaseParaCurso($fk);
            }
        }

        return ['slot' => $slot, 'idTurnoClase' => $idTurnoClase];
    }

    public static function filaHorarios26CoincideTurno(object $row, int $idTurnoEsperado, ?int $idCursoContexto = null): bool
    {
        $interp = self::interpretarFilaHorarios26($row, $idCursoContexto);

        return $interp['slot'] > 0 && $interp['idTurnoClase'] === $idTurnoEsperado;
    }

    /**
     * Filtro de existencia/borrado/conflicto para una celda (día ya aplicado aparte).
     */
    private static function aplicarFiltroCeldaHorarios26(
        Builder $q,
        string $columnHora,
        ?string $columnTurno,
        int $slot,
        int $idTurnoClase,
    ): void {
        $slot = self::slotHoraParaBd($slot);

        if (self::horarios26UsaIdTurnoClase() && $columnTurno !== null) {
            $legacyIdHora = self::idHoraLegacyDesdeTurnoYSlot($idTurnoClase, $slot);
            $q->where(function ($w) use ($columnHora, $columnTurno, $slot, $idTurnoClase, $legacyIdHora) {
                $w->where(function ($wNuevo) use ($columnHora, $columnTurno, $slot, $idTurnoClase) {
                    $wNuevo->where($columnHora, $slot)
                        ->where($columnTurno, $idTurnoClase);
                })->orWhere(function ($wLeg) use ($columnHora, $columnTurno, $legacyIdHora) {
                    $wLeg->where($columnHora, $legacyIdHora)
                        ->where(function ($wNull) use ($columnTurno) {
                            $wNull->whereNull($columnTurno)->orWhere($columnTurno, 0);
                        });
                });
            });

            return;
        }

        $q->where($columnHora, self::idHoraLegacyDesdeTurnoYSlot($idTurnoClase, $slot));
    }

    /**
     * Compat. lectura sin columna idTurnoClase o idHora 11–30 sin migrar.
     *
     * @deprecated Usar {@see filaHorarios26CoincideTurno} cuando exista `horarios26.idTurnoClase`.
     */
    public static function idHoraCoincideConBloqueTurno(int $idHora, int $bloqueEsperado, bool $cursoDobleJornada = false): bool
    {
        $bloqueHora = self::indiceBloqueDesdeIdHoraLegacy($idHora);
        if ($bloqueHora < 0) {
            return false;
        }
        if ($bloqueHora === $bloqueEsperado) {
            return true;
        }
        if ($cursoDobleJornada) {
            return false;
        }

        $turnosAct = self::turnosActivos();
        if (count($turnosAct) !== 1) {
            return false;
        }

        $bloqueActivo = self::indiceBloqueTurnoClase((int) $turnosAct[0]);
        if ($bloqueHora === $bloqueActivo) {
            return true;
        }

        return $bloqueHora === 0 && $bloqueActivo > 0;
    }

    /**
     * Turno "doble" (ej. Mañana/Tarde): el curso cursa dos bandas de idHora; el FK en `cursos` apunta al turno compuesto.
     */
    public static function esTurnoClaseDobleJornada(int $idTurnoClase): bool
    {
        if ($idTurnoClase <= 0) {
            return false;
        }
        $row = self::catalogoTurnosClase()->firstWhere('id', $idTurnoClase);
        if ($row === null) {
            return false;
        }
        $codigo = mb_strtolower(preg_replace('/[\s_\-]+/', '_', trim((string) ($row->codigo ?? ''))));
        $nombre = mb_strtolower(trim((string) ($row->nombre ?? '')));
        if (in_array($codigo, ['manana_tarde', 'mananatarde', 'myt', 'doble_jornada', 'doble'], true)) {
            return true;
        }
        if ((str_contains($nombre, 'mañana') || str_contains($nombre, 'manana')) && str_contains($nombre, 'tarde')) {
            return true;
        }

        return (int) $row->id === 4;
    }

    /**
     * Turnos que el establecimiento activa en «Configuración de horarios» (jornadas madre).
     * No incluye turnos compuestos (p. ej. Mañana/Tarde): esos solo se asignan al curso en ABM.
     *
     * @return Collection<int, TurnoClase>
     */
    public static function turnosClaseParaConfiguracionEstablecimiento(): Collection
    {
        return self::catalogoTurnosClase()
            ->filter(fn ($t) => ! self::esTurnoClaseDobleJornada((int) $t->id))
            ->values();
    }

    /**
     * Dos ids de `turnos_clase` para bloques 0 y 1 de `idHora` (excluido el turno compuesto).
     *
     * @return array{0:int, 1:int}
     */
    public static function idsTurnoClaseBandasMananaTarde(): array
    {
        $simple = self::catalogoTurnosClase()
            ->filter(fn ($t) => ! self::esTurnoClaseDobleJornada((int) $t->id))
            ->sortBy(fn ($t) => [(int) ($t->orden ?? 0), (int) $t->id])
            ->values();

        return [
            (int) ($simple->get(0)->id ?? 1),
            (int) ($simple->get(1)->id ?? 2),
        ];
    }

    /**
     * @return list<array{indice:int, etiqueta:string}>
     */
    public static function segmentosCargaHorarioDesdeTurnoNorm(int $idTurnoClaseNorm): array
    {
        if (! self::esTurnoClaseDobleJornada($idTurnoClaseNorm)) {
            return [['indice' => 0, 'etiqueta' => '']];
        }
        [$ma, $ta] = self::idsTurnoClaseBandasMananaTarde();

        return [
            ['indice' => 0, 'etiqueta' => self::nombreTurnoClase($ma)],
            ['indice' => 1, 'etiqueta' => self::nombreTurnoClase($ta)],
        ];
    }

    public static function cursoVisualizadoEnTurnoImpresion(?int $idTurnoClaseCursoFk, int $idTurnoPagina): bool
    {
        $idNorm = self::idTurnoClaseParaCurso($idTurnoClaseCursoFk !== null && $idTurnoClaseCursoFk > 0 ? $idTurnoClaseCursoFk : null);
        if (self::esTurnoClaseDobleJornada($idNorm)) {
            [$ma, $ta] = self::idsTurnoClaseBandasMananaTarde();

            return $idTurnoPagina === $ma || $idTurnoPagina === $ta;
        }

        return $idNorm === $idTurnoPagina;
    }

    /**
     * Horas reloj del turno: `reloj.orden` siempre 1..10; el turno es `idTurnoClase`.
     * Sin columna `idTurnoClase`, una sola grilla 1..10 por nivel.
     * Compatibilidad lectura: filas antiguas con `orden` 11–30 por bloque y `NULL` en orden 1–10 solo 1.er turno.
     *
     * @return array<int, string> clave 1..10 => texto
     */
    public static function relojPorTurnoClase(int $idTurnoClase, ?int $idNivel = null): array
    {
        $idNivel = self::idNivelHorariosSesion($idNivel);
        $out = [];
        for ($h = 1; $h <= self::HORAS_POR_TURNO; $h++) {
            $out[$h] = '';
        }

        if ($idNivel <= 0) {
            return $out;
        }

        $cols = ['orden', 'horario'];
        if (self::relojTieneTurnoClase()) {
            $cols[] = 'idTurnoClase';
        }

        if (! self::relojTieneTurnoClase()) {
            foreach (
                DB::table('reloj')
                    ->where('idNivel', $idNivel)
                    ->whereBetween('orden', [1, self::HORAS_POR_TURNO])
                    ->orderBy('orden')
                    ->get($cols) as $r
            ) {
                $orden = (int) ($r->orden ?? 0);
                if ($orden >= 1 && $orden <= self::HORAS_POR_TURNO) {
                    $out[$orden] = trim((string) ($r->horario ?? ''));
                }
            }

            return $out;
        }

        foreach (
            DB::table('reloj')
                ->where('idNivel', $idNivel)
                ->where('idTurnoClase', $idTurnoClase)
                ->whereBetween('orden', [1, self::HORAS_POR_TURNO])
                ->orderBy('orden')
                ->get($cols) as $r
        ) {
            $orden = (int) ($r->orden ?? 0);
            if ($orden >= 1 && $orden <= self::HORAS_POR_TURNO) {
                $out[$orden] = trim((string) ($r->horario ?? ''));
            }
        }

        if (self::indiceBloqueTurnoClase($idTurnoClase) === 0) {
            foreach (
                DB::table('reloj')
                    ->where('idNivel', $idNivel)
                    ->whereBetween('orden', [1, self::HORAS_POR_TURNO])
                    ->whereNull('idTurnoClase')
                    ->orderBy('orden')
                    ->get($cols) as $r
            ) {
                $orden = (int) ($r->orden ?? 0);
                if ($orden < 1 || $orden > self::HORAS_POR_TURNO || $out[$orden] !== '') {
                    continue;
                }
                $out[$orden] = trim((string) ($r->horario ?? ''));
            }
        }

        $base = self::baseHoraLegacyPorTurnoClase($idTurnoClase);
        foreach (
            DB::table('reloj')
                ->where('idNivel', $idNivel)
                ->whereBetween('orden', [$base + 1, $base + self::HORAS_POR_TURNO])
                ->where(function ($w) use ($idTurnoClase) {
                    $w->where('idTurnoClase', $idTurnoClase)->orWhereNull('idTurnoClase');
                })
                ->orderBy('orden')
                ->get($cols) as $r
        ) {
            $orden = (int) ($r->orden ?? 0);
            $slot = $orden - $base;
            if ($slot < 1 || $slot > self::HORAS_POR_TURNO || $out[$slot] !== '') {
                continue;
            }
            $out[$slot] = trim((string) ($r->horario ?? ''));
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $horas  clave 1..10 (módulos del turno)
     */
    public static function guardarReloj(int $idNivel, int $idTurnoClase, array $horas): void
    {
        for ($h = 1; $h <= self::HORAS_POR_TURNO; $h++) {
            $orden = $h;
            $texto = trim((string) ($horas[$h] ?? ''));

            if (self::relojTieneTurnoClase()) {
                $existing = DB::table('reloj')
                    ->where('idNivel', $idNivel)
                    ->where('orden', $orden)
                    ->where('idTurnoClase', $idTurnoClase)
                    ->first(['id']);
                if (! $existing && self::indiceBloqueTurnoClase($idTurnoClase) === 0) {
                    $existing = DB::table('reloj')
                        ->where('idNivel', $idNivel)
                        ->where('orden', $orden)
                        ->whereNull('idTurnoClase')
                        ->first(['id']);
                }
            } else {
                $existing = DB::table('reloj')
                    ->where('idNivel', $idNivel)
                    ->where('orden', $orden)
                    ->first(['id']);
            }

            if ($existing) {
                $upd = ['horario' => $texto];
                if (self::relojTieneTurnoClase()) {
                    $upd['idTurnoClase'] = $idTurnoClase;
                }
                DB::table('reloj')->where('id', $existing->id)->update($upd);
            } elseif ($texto !== '') {
                $payload = [
                    'orden' => $orden,
                    'idNivel' => $idNivel,
                    'horario' => $texto,
                ];
                if (self::relojTieneTurnoClase()) {
                    $payload['idTurnoClase'] = $idTurnoClase;
                }
                DB::table('reloj')->insert($payload);
            }
        }
    }

    /**
     * Asignaciones PPC del docente en el ciclo activo.
     *
     * @return Collection<int, object{idMateria:int, idCursos:int, materia:string, cursoLabel:string, idTurnoClase:int, idTurnoClaseCursoFk:int, cursoTieneTurnoEnAbm:bool, esTurnoDobleJornada:bool}>
     */
    public static function asignacionesProfesor(int $idProfesor): Collection
    {
        if (isset(self::$asignacionesProfesorCache[$idProfesor])) {
            return self::$asignacionesProfesorCache[$idProfesor];
        }

        $ctx = schoolCtx();

        self::$asignacionesProfesorCache[$idProfesor] = DB::table('ppc as ppc')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->join('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->where('ppc.idProfesor', $idProfesor)
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->where('c.idNivel', (int) $ctx->idNivel)
            ->where('c.idTerlec', (int) $ctx->idTerlec)
            ->orderBy('c.orden')
            ->orderBy('c.cursec')
            ->orderBy('m.ord')
            ->orderBy('m.materia')
            ->get([
                'ppc.idMateria as idMateria',
                'm.idCursos as idCursos',
                'm.materia as materia',
                'c.cursec',
                'c.orden',
                'c.idTurnoClase',
                'c.c',
                'c.s',
                'c.idCurPlan',
            ])
            ->map(function ($r) {
                $idFk = isset($r->idTurnoClase) && (int) $r->idTurnoClase > 0 ? (int) $r->idTurnoClase : null;
                $curso = new Curso([
                    'cursec' => $r->cursec,
                    'orden' => $r->orden,
                    'idTurnoClase' => $idFk,
                    'c' => $r->c,
                    's' => $r->s,
                    'idCurPlan' => $r->idCurPlan,
                ]);

                return (object) [
                    'idMateria' => (int) $r->idMateria,
                    'idCursos' => (int) $r->idCursos,
                    'materia' => trim((string) ($r->materia ?? '')),
                    'cursoLabel' => $curso->nombreParaListado(),
                    'idTurnoClase' => self::idTurnoClaseParaCurso($idFk),
                    'idTurnoClaseCursoFk' => (int) ($r->idTurnoClase ?? 0),
                    'cursoTieneTurnoEnAbm' => $idFk !== null,
                    'esTurnoDobleJornada' => self::esTurnoClaseDobleJornada(self::idTurnoClaseParaCurso($idFk)),
                ];
            });

        return self::$asignacionesProfesorCache[$idProfesor];
    }

    /**
     * @param  int  $indiceBloqueHorario  0 = 1.ª banda idHora (mañana), 1 = 2.ª (tarde). Solo aplica si el turno del curso es doble jornada.
     * @return array<string, bool> clave "{lun}-{hora}" (códigos legacy, sin 1..7)
     */
    public static function celdasMarcadas(
        int $idProfesor,
        int $idMateria,
        int $idCurso,
        int $indiceBloqueHorario = 0,
    ): array {
        $cols = ['h.idDia as idDia', 'h.idHora as idHora'];
        if (self::horarios26UsaIdTurnoClase()) {
            $cols[] = 'h.idTurnoClase as horarioIdTurnoClase';
        }
        $rows = self::queryHorarios26Carga($idProfesor, $idMateria, $idCurso)->get($cols);

        $idTurnoEsperado = self::idTurnoClaseParaCargaEnCelda($idCurso, $indiceBloqueHorario);

        $activos = array_flip(self::diasActivosLegacy());
        $out = [];
        foreach ($rows as $r) {
            if (! self::filaHorarios26CoincideTurno($r, $idTurnoEsperado, $idCurso)) {
                continue;
            }
            $diaCanon = self::normalizarIdDiaCanonico((string) ($r->idDia ?? ''));
            $slot = self::interpretarFilaHorarios26($r, $idCurso)['slot'];
            if ($diaCanon !== null && $slot > 0 && isset($activos[$diaCanon])) {
                $out[self::celdaKeyLegacy($diaCanon, $slot)] = true;
            }
        }

        return $out;
    }

    /**
     * @param  int  $hora  Módulo 1..10 de la banda (idHora 1–10 o 11–20 según turno efectivo).
     * @param  int  $indiceBloqueHorario  0 mañana / 1 tarde cuando el curso es doble jornada.
     * @return array{ok:bool, mensaje?:string}
     */
    public static function alternarCelda(
        int $idProfesor,
        int $idMateria,
        int $idCurso,
        string $diaLegacy,
        int $hora,
        bool $marcar,
        int $indiceBloqueHorario = 0,
    ): array {
        $diaCanon = self::normalizarIdDiaCanonico($diaLegacy);
        $activos = array_flip(self::diasActivosLegacy());

        if ($diaCanon === null || ! isset($activos[$diaCanon]) || $hora < 1 || $hora > self::HORAS_POR_TURNO) {
            return ['ok' => false, 'mensaje' => 'Día u hora inválidos.'];
        }

        if (! self::profesorTieneAsignacion($idProfesor, $idMateria, $idCurso)) {
            return ['ok' => false, 'mensaje' => 'El docente no está asignado a esa materia y curso.'];
        }

        $slot = self::slotHoraParaBd($hora);
        $idTurnoCelda = self::idTurnoClaseParaCargaEnCelda($idCurso, $indiceBloqueHorario);
        $colTurno = self::horarios26UsaIdTurnoClase() ? 'idTurnoClase' : null;

        $qExist = self::queryHorarios26CargaSinAlias($idProfesor, $idMateria, $idCurso);
        self::aplicarFiltroIdDiaCanonicoEnQuery($qExist, 'idDia', $diaCanon);
        self::aplicarFiltroCeldaHorarios26($qExist, 'idHora', $colTurno, $slot, $idTurnoCelda);
        $existente = $qExist->first(['id']);

        if ($marcar) {
            if ($existente) {
                return ['ok' => true];
            }

            $conflictoDocente = self::conflictoProfesorOtroCurso($idProfesor, $idCurso, $diaCanon, $slot, $idTurnoCelda);
            if ($conflictoDocente !== null) {
                return [
                    'ok' => false,
                    'mensaje' => 'En ese horario el docente ya figura con '.$conflictoDocente.'.',
                ];
            }

            $insert = [
                'idProfesores' => $idProfesor,
                'idMaterias' => $idMateria,
                'idDia' => $diaCanon,
                'idHora' => $slot,
                'idCursos' => $idCurso,
            ];
            if ($colTurno !== null) {
                $insert['idTurnoClase'] = $idTurnoCelda;
            }
            DB::table('horarios26')->insert($insert);

            return ['ok' => true];
        }

        $qDel = self::queryHorarios26CargaSinAlias($idProfesor, $idMateria, $idCurso);
        self::aplicarFiltroIdDiaCanonicoEnQuery($qDel, 'idDia', $diaCanon);
        self::aplicarFiltroCeldaHorarios26($qDel, 'idHora', $colTurno, $slot, $idTurnoCelda);
        $qDel->delete();

        return ['ok' => true];
    }

    /**
     * Mismo docente, mismo día y mismo {@see horarios26.idHora} (reloj del turno) en un curso distinto al que se está cargando.
     * No valida superposiciones dentro del mismo curso (p. ej. cátedra compartida).
     */
    private static function conflictoProfesorOtroCurso(
        int $idProfesor,
        int $idCursoActual,
        string $diaCanon,
        int $slot,
        int $idTurnoClase,
    ): ?string {
        $ctx = schoolCtx();
        $q = DB::table('horarios26 as h')
            ->join('cursos as c', 'c.Id', '=', 'h.idCursos')
            ->leftJoin('materias as m', 'm.id', '=', 'h.idMaterias')
            ->where('h.idProfesores', $idProfesor)
            ->where('h.idCursos', '!=', $idCursoActual);
        self::aplicarFiltroCeldaHorarios26(
            $q,
            'h.idHora',
            self::horarios26UsaIdTurnoClase() ? 'h.idTurnoClase' : null,
            $slot,
            $idTurnoClase,
        );
        $q
            ->where('c.idNivel', (int) $ctx->idNivel)
            ->where('c.idTerlec', (int) $ctx->idTerlec);
        self::aplicarFiltroIdDiaCanonicoEnQuery($q, 'h.idDia', $diaCanon);

        $row = $q->first([
            'm.materia as materia',
            'c.cursec',
            'c.orden',
            'c.idTurnoClase',
            'c.c',
            'c.s',
            'c.idCurPlan',
        ]);
        if ($row === null) {
            return null;
        }

        $idFk = isset($row->idTurnoClase) && (int) $row->idTurnoClase > 0 ? (int) $row->idTurnoClase : null;
        $curso = new Curso([
            'cursec' => $row->cursec,
            'orden' => $row->orden,
            'idTurnoClase' => $idFk,
            'c' => $row->c,
            's' => $row->s,
            'idCurPlan' => $row->idCurPlan,
        ]);
        $cursoLabel = $curso->nombreParaListado();
        $mat = trim((string) ($row->materia ?? ''));

        if ($mat === '') {
            return '«'.$cursoLabel.'»';
        }

        return '«'.$mat.'» en '.$cursoLabel;
    }

    /**
     * Grilla para impresión por curso en un turno.
     *
     * @return array{
     *     dias: list<int>,
     *     horas: list<int>,
     *     reloj: array<int, string>,
     *     celdas: array<string, list<string>>
     * }
     */
    public static function grillaCurso(int $idCurso, int $idTurnoClase, ?int $idNivel = null, ?int $idTerlec = null): array
    {
        [$idNivel, $idTerlec] = self::contextoHorariosNivelTerlec($idNivel, $idTerlec);
        $dias = self::diasActivos($idNivel > 0 ? $idNivel : null);
        $horas = range(1, self::HORAS_POR_TURNO);
        $celdas = [];

        $idsMat = self::idsMateriasSoloEsteCurso($idCurso, $idNivel, $idTerlec);
        $q26 = DB::table('horarios26 as h')
            ->leftJoin('materias as m', 'm.id', '=', 'h.idMaterias')
            ->leftJoin('profesores as p', 'p.id', '=', 'h.idProfesores');
        self::aplicarFiltroHorarios26GrillaSoloEsteCurso($q26, $idCurso, $idsMat);
        self::aplicarFiltroDiasLegacyEnQuery($q26, 'h.idDia', $dias);
        self::aplicarFiltroFilasDeTurnoEnLectura(
            $q26,
            'h.idHora',
            self::horarios26UsaIdTurnoClase() ? 'h.idTurnoClase' : null,
            $idTurnoClase,
        );

        foreach ($q26->orderBy('h.idHora')->orderBy('m.materia')->get([
            'h.idDia',
            'h.idHora',
            'h.idMaterias',
            'h.idProfesores',
            'm.materia',
            'p.apellido',
            'p.nombre',
        ]) as $r) {
            self::agregarLineaGrilla(
                $celdas,
                (string) ($r->idDia ?? ''),
                (int) ($r->idHora ?? 0),
                self::materiaImpresionCursoPdf(self::etiquetaMateriaHorario((int) ($r->idMaterias ?? 0), (string) ($r->materia ?? ''), $idCurso)),
                self::nombreProfesorImpresionCursoPdf(self::nombreProfesorFilaImpresionCurso($r, (int) ($r->idMaterias ?? 0), (int) ($r->idProfesores ?? 0), $idCurso)),
            );
        }

        if (Schema::hasTable('horarios') && $idsMat !== []) {
            $qH = DB::table('horarios as h')
                ->leftJoin('materias as m', 'm.id', '=', 'h.idMaterias')
                ->whereIn('h.idMaterias', $idsMat);
            self::aplicarFiltroDiasLegacyEnQuery($qH, 'h.idDia', $dias);
            self::aplicarFiltroIdHoraRangoTurnoEnQuery($qH, 'h.idHora', $idTurnoClase);

            foreach ($qH->orderBy('h.idHora')->orderBy('m.materia')->get([
                'h.idDia',
                'h.idHora',
                'h.idMaterias',
                'm.materia',
            ]) as $r) {
                self::agregarLineaGrilla(
                    $celdas,
                    (string) ($r->idDia ?? ''),
                    (int) ($r->idHora ?? 0),
                    self::materiaImpresionCursoPdf(self::etiquetaMateriaHorario((int) ($r->idMaterias ?? 0), (string) ($r->materia ?? ''), $idCurso)),
                    self::nombreProfesorImpresionCursoPdf(self::nombresProfesoresMateria((int) ($r->idMaterias ?? 0))),
                    true,
                );
            }
        }

        return [
            'dias' => $dias,
            'horas' => $horas,
            'reloj' => self::relojPorTurnoClase($idTurnoClase, $idNivel > 0 ? $idNivel : null),
            'celdas' => $celdas,
        ];
    }

    /**
     * Filas para el impreso «Parte diario del preceptor»: materia y docente por hora en un día fijo.
     * Reutiliza la misma grilla que el horario por curso ({@see grillaCurso}).
     *
     * @return list<array{hora:int, espacio:string, etiquetaReloj:string}>
     */
    public static function filasParteDiarioCursoDia(int $idCurso, int $diaSemana1a7, int $idTurnoClase): array
    {
        if ($diaSemana1a7 < 1 || $diaSemana1a7 > 7) {
            return [];
        }

        $grilla = self::grillaCurso($idCurso, $idTurnoClase);
        $reloj = $grilla['reloj'];
        $celdas = $grilla['celdas'];
        $filas = [];

        foreach ($grilla['horas'] as $h) {
            $key = self::celdaKey($diaSemana1a7, $h);
            $lineas = $celdas[$key] ?? [];
            $espacio = $lineas !== [] ? implode("\n", $lineas) : '';

            $txtReloj = trim((string) ($reloj[$h] ?? ''));
            if ($txtReloj !== '') {
                $etiqueta = $h.'º HORA: '.$txtReloj;
            } else {
                $etiqueta = $h.'º HORA';
            }

            $filas[] = [
                'hora' => $h,
                'espacio' => $espacio,
                'etiquetaReloj' => $etiqueta,
            ];
        }

        return $filas;
    }

    /**
     * Otros docentes con fila en {@see horarios26} para el mismo módulo (cátedra compartida).
     * Devuelve texto listo para el sufijo en PDF (apellido, nombre con formato homogéneo al horario por curso).
     */
    private static function textoProfesoresCompartenCatedraEnCelda(
        int $idProfesorActual,
        int $idMateria,
        int $idCurso,
        string $idDiaLegacy,
        int $slot,
        int $idTurnoClase,
    ): string {
        if ($idProfesorActual <= 0 || $idMateria <= 0 || $slot <= 0) {
            return '';
        }

        $cacheKey = implode(':', [$idProfesorActual, $idMateria, $idCurso, $idDiaLegacy, $slot, $idTurnoClase]);
        if (! self::$impresionPdfMasivaProfesores && array_key_exists($cacheKey, self::$coDocenteCeldaCache)) {
            return self::$coDocenteCeldaCache[$cacheKey];
        }

        $diaCanon = self::normalizarIdDiaCanonico($idDiaLegacy);
        if ($diaCanon === null) {
            return self::cacheCoDocenteCelda($cacheKey, '');
        }

        $ctx = schoolCtx();
        $idsMat = self::idsMateriasEquivalentes($idMateria, $idCurso);
        if ($idsMat === []) {
            return self::cacheCoDocenteCelda($cacheKey, '');
        }

        $q = DB::table('horarios26 as h')
            ->join('materias as m', 'm.id', '=', 'h.idMaterias')
            ->join('profesores as p', 'p.id', '=', 'h.idProfesores')
            ->where('h.idProfesores', '!=', $idProfesorActual)
            ->whereIn('h.idMaterias', $idsMat);
        self::aplicarFiltroCeldaHorarios26(
            $q,
            'h.idHora',
            self::horarios26UsaIdTurnoClase() ? 'h.idTurnoClase' : null,
            $slot,
            $idTurnoClase,
        );
        $q
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->where('m.idTerlec', (int) $ctx->idTerlec);

        if ($idCurso > 0) {
            $q->where(function ($w) use ($idCurso) {
                $w->where('h.idCursos', $idCurso)
                    ->orWhere(function ($z) use ($idCurso) {
                        $z->where(function ($y) {
                            $y->whereNull('h.idCursos')->orWhere('h.idCursos', 0);
                        })->where('m.idCursos', $idCurso);
                    });
            });
        }

        self::aplicarFiltroIdDiaCanonicoEnQuery($q, 'h.idDia', $diaCanon);

        $nombres = [];
        $vistos = [];
        foreach ($q->orderBy('p.apellido')->orderBy('p.nombre')->get(['p.id', 'p.apellido', 'p.nombre']) as $row) {
            $pid = (int) ($row->id ?? 0);
            if ($pid <= 0 || isset($vistos[$pid])) {
                continue;
            }
            $vistos[$pid] = true;
            $s = trim(((string) ($row->apellido ?? '')).', '.((string) ($row->nombre ?? '')));
            $s = trim($s, ', ');
            if ($s !== '') {
                $nombres[] = $s;
            }
        }

        if ($nombres === []) {
            return self::cacheCoDocenteCelda($cacheKey, '');
        }

        return self::cacheCoDocenteCelda($cacheKey, self::nombreProfesorImpresionCursoPdf(implode(' / ', $nombres)));
    }

    private static function cacheCoDocenteCelda(string $cacheKey, string $valor): string
    {
        if (! self::$impresionPdfMasivaProfesores) {
            self::$coDocenteCeldaCache[$cacheKey] = $valor;
        }

        return $valor;
    }

    /**
     * Grilla para impresión por profesor en un turno.
     *
     * @return array{
     *     dias: list<int>,
     *     horas: list<int>,
     *     reloj: array<int, string>,
     *     celdas: array<string, list<string>>
     * }
     */
    public static function modoImpresionPdfMasivaProfesores(bool $activo = true): void
    {
        self::$impresionPdfMasivaProfesores = $activo;
        if ($activo) {
            self::$grillaProfesorCache = [];
        }
    }

    public static function limpiarCachesRequestHorarios(): void
    {
        self::$grillaProfesorCache = [];
        self::$profesorTieneCeldasCache = [];
        self::$idsMateriasPermitidasProfesorCache = [];
        self::$asignacionesProfesorCache = [];
        self::$coDocenteCeldaCache = [];
    }

    public static function grillaProfesor(int $idProfesor, int $idTurnoClase, bool $filtrarTurno = true): array
    {
        $cacheKey = $idProfesor.':'.$idTurnoClase.':'.($filtrarTurno ? '1' : '0');
        if (! self::$impresionPdfMasivaProfesores && isset(self::$grillaProfesorCache[$cacheKey])) {
            return self::$grillaProfesorCache[$cacheKey];
        }

        $dias = self::diasActivos();
        $horas = range(1, self::HORAS_POR_TURNO);
        $celdas = [];

        $idsMat = self::idsMateriasPermitidasImpresionProfesor($idProfesor);

        $q = DB::table('horarios26 as h')
            ->leftJoin('materias as m', 'm.id', '=', 'h.idMaterias')
            ->leftJoin('cursos as c', function ($join) {
                $join->on('c.Id', '=', 'm.idCursos');
            })
            ->where('h.idProfesores', $idProfesor);
        self::aplicarFiltroHorarios26ProfesorSoloMateriasAsignadas($q, $idsMat);
        self::aplicarFiltroDiasLegacyEnQuery($q, 'h.idDia', $dias);

        $colsProf = [
            'h.idDia',
            'h.idHora',
            'h.idMaterias',
            'h.idCursos',
            'm.materia',
            'm.idCursos as materiaIdCurso',
            'c.cursec',
            'c.orden',
            'c.idTurnoClase',
            'c.c',
            'c.s',
            'c.idCurPlan',
        ];
        if (self::horarios26UsaIdTurnoClase()) {
            $colsProf[] = 'h.idTurnoClase as horarioIdTurnoClase';
        }

        $rows = $q->orderBy('h.idHora')->orderBy('c.orden')->get($colsProf);
        $cursoIdsSinTurno = [];
        foreach ($rows as $r) {
            $idCursoFila = (int) ($r->idCursos ?? 0) > 0
                ? (int) $r->idCursos
                : (int) ($r->materiaIdCurso ?? 0);
            if ((int) ($r->idTurnoClase ?? 0) === 0 && $idCursoFila > 0) {
                $cursoIdsSinTurno[$idCursoFila] = true;
            }
        }
        $turnoPorCursoId = $cursoIdsSinTurno !== []
            ? DB::table('cursos')->whereIn('Id', array_keys($cursoIdsSinTurno))->get([
                'Id', 'cursec', 'orden', 'idTurnoClase', 'c', 's', 'idCurPlan',
            ])->keyBy('Id')
            : collect();

        foreach ($rows as $r) {
            $idCursoFila = (int) ($r->idCursos ?? 0) > 0
                ? (int) $r->idCursos
                : (int) ($r->materiaIdCurso ?? 0);
            $idTcFk = (int) ($r->idTurnoClase ?? 0);
            if ($idTcFk === 0 && $idCursoFila > 0) {
                $rowC = $turnoPorCursoId->get($idCursoFila);
                if ($rowC !== null) {
                    $idTcFk = (int) ($rowC->idTurnoClase ?? 0);
                }
            }
            if ($filtrarTurno) {
                if (! self::filaHorarios26CoincideTurno($r, $idTurnoClase, $idCursoFila > 0 ? $idCursoFila : null)) {
                    continue;
                }
                if (! self::cursoVisualizadoEnTurnoImpresion($idTcFk > 0 ? $idTcFk : null, $idTurnoClase)) {
                    continue;
                }
            }
            $interp = self::interpretarFilaHorarios26($r, $idCursoFila > 0 ? $idCursoFila : null);
            $curso = new Curso([
                'cursec' => $r->cursec,
                'orden' => $r->orden,
                'idTurnoClase' => $idTcFk > 0 ? $idTcFk : null,
                'c' => $r->c,
                's' => $r->s,
                'idCurPlan' => $r->idCurPlan,
            ]);
            $linea = $curso->nombreParaListado() !== ''
                ? $curso->nombreParaListado()
                : ($idCursoFila > 0 ? 'Curso #'.$idCursoFila : 'Curso');
            $mat = self::etiquetaMateriaHorario((int) ($r->idMaterias ?? 0), (string) ($r->materia ?? ''), $idCursoFila);
            if ($mat !== '') {
                $linea .= ' — '.$mat;
            }
            $coDocentes = self::textoProfesoresCompartenCatedraEnCelda(
                $idProfesor,
                (int) ($r->idMaterias ?? 0),
                $idCursoFila,
                (string) ($r->idDia ?? ''),
                $interp['slot'],
                $interp['idTurnoClase'],
            );
            $sufijoCoDocente = $coDocentes !== '' ? '(Con '.$coDocentes.')' : '';
            self::agregarLineaGrilla($celdas, (string) ($r->idDia ?? ''), $interp['slot'], $linea, $sufijoCoDocente);
        }

        $grilla = [
            'dias' => $dias,
            'horas' => $horas,
            'reloj' => self::relojPorTurnoClase($idTurnoClase),
            'celdas' => $celdas,
        ];

        if (! self::$impresionPdfMasivaProfesores) {
            self::$grillaProfesorCache[$cacheKey] = $grilla;
        }

        return $grilla;
    }

    /**
     * Turnos a imprimir para un docente (con horas cargadas o, si no hay, según sus cursos).
     *
     * @return list<int>
     */
    public static function turnosParaImpresionProfesor(int $idProfesor): array
    {
        $activos = self::turnosActivos();
        $conDatos = [];
        foreach ($activos as $t) {
            if (self::profesorTieneCeldasEnTurno($idProfesor, $t, true)) {
                $conDatos[] = $t;
            }
        }
        if ($conDatos !== []) {
            return $conDatos;
        }

        foreach ($activos as $t) {
            if (self::profesorTieneCeldasEnTurno($idProfesor, $t, false)) {
                return [$t];
            }
        }

        $asignaciones = self::asignacionesProfesor($idProfesor);
        $expandidos = [];
        foreach ($asignaciones->pluck('idTurnoClase')->unique()->filter() as $tid) {
            $tid = (int) $tid;
            if (self::esTurnoClaseDobleJornada($tid)) {
                foreach (self::idsTurnoClaseBandasMananaTarde() as $x) {
                    $expandidos[] = (int) $x;
                }
            } else {
                $expandidos[] = $tid;
            }
        }
        $expandidos = array_values(array_unique($expandidos));
        $fallback = self::catalogoTurnosClase()->pluck('id')->map(fn ($id) => (int) $id)->first() ?? 1;

        return array_values(array_intersect($activos, $expandidos !== [] ? $expandidos : [$fallback]));
    }

    /**
     * Si la grilla por turno queda vacía, reintenta sin filtrar por id de turno (para casos con idTurnoClase no acotado).
     */
    public static function grillaProfesorParaImpresion(int $idProfesor, int $idTurnoClase): array
    {
        if (self::profesorTieneCeldasEnTurno($idProfesor, $idTurnoClase, true)) {
            return self::grillaProfesor($idProfesor, $idTurnoClase, true);
        }

        return self::grillaProfesor($idProfesor, $idTurnoClase, false);
    }

    /**
     * Indica si el docente tiene al menos una celda en la grilla del turno (sin armar la grilla completa).
     */
    public static function profesorTieneCeldasEnTurno(int $idProfesor, int $idTurnoClase, bool $filtrarTurno = true): bool
    {
        $cacheKey = $idProfesor.':'.$idTurnoClase.':'.($filtrarTurno ? '1' : '0');
        if (array_key_exists($cacheKey, self::$profesorTieneCeldasCache)) {
            return self::$profesorTieneCeldasCache[$cacheKey];
        }

        $idsMat = self::idsMateriasPermitidasImpresionProfesor($idProfesor);
        if ($idsMat === []) {
            return self::$profesorTieneCeldasCache[$cacheKey] = false;
        }

        $dias = self::diasActivos();
        $q = DB::table('horarios26 as h')
            ->leftJoin('materias as m', 'm.id', '=', 'h.idMaterias')
            ->leftJoin('cursos as c', function ($join) {
                $join->on('c.Id', '=', 'm.idCursos');
            })
            ->where('h.idProfesores', $idProfesor);
        self::aplicarFiltroHorarios26ProfesorSoloMateriasAsignadas($q, $idsMat);
        self::aplicarFiltroDiasLegacyEnQuery($q, 'h.idDia', $dias);

        $cols = [
            'h.idDia',
            'h.idHora',
            'h.idMaterias',
            'h.idCursos',
            'm.idCursos as materiaIdCurso',
            'c.idTurnoClase',
        ];
        if (self::horarios26UsaIdTurnoClase()) {
            $cols[] = 'h.idTurnoClase as horarioIdTurnoClase';
        }

        $rows = $q->orderBy('h.idHora')->limit(400)->get($cols);
        $cursoIdsPendientes = [];
        foreach ($rows as $r) {
            $idCursoFila = (int) ($r->idCursos ?? 0) > 0
                ? (int) $r->idCursos
                : (int) ($r->materiaIdCurso ?? 0);
            if ((int) ($r->idTurnoClase ?? 0) === 0 && $idCursoFila > 0) {
                $cursoIdsPendientes[$idCursoFila] = true;
            }
        }
        $turnoPorCurso = $cursoIdsPendientes !== []
            ? DB::table('cursos')->whereIn('Id', array_keys($cursoIdsPendientes))->pluck('idTurnoClase', 'Id')
            : collect();

        foreach ($rows as $r) {
            $idCursoFila = (int) ($r->idCursos ?? 0) > 0
                ? (int) $r->idCursos
                : (int) ($r->materiaIdCurso ?? 0);
            $idTcFk = (int) ($r->idTurnoClase ?? 0);
            if ($idTcFk === 0 && $idCursoFila > 0) {
                $idTcFk = (int) ($turnoPorCurso[$idCursoFila] ?? 0);
                $r->idTurnoClase = $idTcFk;
            }
            if ($filtrarTurno) {
                if (! self::filaHorarios26CoincideTurno($r, $idTurnoClase, $idCursoFila > 0 ? $idCursoFila : null)) {
                    continue;
                }
                if (! self::cursoVisualizadoEnTurnoImpresion($idTcFk > 0 ? $idTcFk : null, $idTurnoClase)) {
                    continue;
                }
            }

            return self::$profesorTieneCeldasCache[$cacheKey] = true;
        }

        return self::$profesorTieneCeldasCache[$cacheKey] = false;
    }

    /**
     * @return list<int> IDs de turnos_clase
     */
    public static function turnosParaImpresionCurso(Curso $curso, ?int $idNivel = null): array
    {
        $activos = self::turnosActivos(self::idNivelHorariosSesion($idNivel));
        $fk = isset($curso->idTurnoClase) && (int) $curso->idTurnoClase > 0 ? (int) $curso->idTurnoClase : null;
        $idNorm = self::idTurnoClaseParaCurso($fk);

        if (self::esTurnoClaseDobleJornada($idNorm)) {
            [$ma, $ta] = self::idsTurnoClaseBandasMananaTarde();
            $pair = array_values(array_unique([$ma, $ta]));
            $out = array_values(array_filter($pair, fn (int $t) => in_array($t, $activos, true)));

            return $out !== [] ? $out : $pair;
        }

        return array_values(array_filter($activos, fn (int $t) => $t === $idNorm)) ?: [$idNorm];
    }

    /**
     * Texto SQL / notas de consultas para depurar impresión PDF «horario por curso» ({@see grillaCurso}).
     * Ejecuta lecturas a BD para armar el texto: solo debe llamarse desde código de depuración activo en pantalla,
     * no desde rutas/productivo con el panel oculto. Convención: `docs/05-preferencias-y-convenciones.md` §10.
     */
    public static function textoDepuracionSqlImpresionHorarioCurso(int $idCurso): string
    {
        if ($idCurso <= 0) {
            return 'Seleccione un curso válido.';
        }

        $ctx = schoolCtx();
        $idNivel = abs((int) $ctx->idNivel);
        $idTerlec = abs((int) $ctx->idTerlec);

        $curso = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', $idCurso)
            ->first(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);

        if (! $curso) {
            return "El curso {$idCurso} no pertenece al nivel/ciclo lectivo de la sesión.";
        }

        $idC = abs((int) $curso->Id);
        $turnos = self::turnosParaImpresionCurso($curso);
        $turnosTxt = implode(', ', array_map(fn ($t) => (string) abs((int) $t), $turnos));
        $idTurnoEjemplo = (int) ($turnos[0] ?? 1);

        $idsMat = self::idsMateriasSoloEsteCurso($idCurso);
        $listaIn = $idsMat === [] ? '(sin ids; la grilla horarios26 no devolverá filas con el filtro actual)' : implode(', ', array_map(fn ($id) => (string) abs((int) $id), $idsMat));

        $valida = <<<SQL
── 1) Validación del curso (mismo criterio que HorarioCursoPdfController) ──

SELECT Id, cursec, orden, idCurPlan, idTurnoClase, c, s
  FROM cursos
 WHERE Id = {$idC}
   AND idNivel = {$idNivel}
   AND idTerlec = {$idTerlec}
 LIMIT 1

SQL;

        $mats = <<<SQL

── 2) Materias solo de este curso y ciclo (ids usados en filtros horarios26 / horarios) ──

SELECT id
  FROM materias
 WHERE idCursos = {$idC}
   AND idNivel = {$idNivel}
   AND idTerlec = {$idTerlec}
 ORDER BY id

→ IDs resultantes para IN (...): {$listaIn}

SQL;

        $filtroHor26 = <<<SQL

── 3) Lectura principal horarios26 + materias + profesores ({@see grillaCurso}) ──
Filtro de curso (estricto, sin mezclar divisiones paralelas):
  (h.idCursos = {$idC} AND h.idMaterias IN (lista arriba))
  OR ((h.idCursos IS NULL OR h.idCursos = 0) AND h.idMaterias IN (lista arriba))
Además: h.idDía debe coincidir con uno de los códigos legacy de los días activos del nivel (Laravel: varios OR LOWER(TRIM(h.idDia)) = 'lun', etc.).

SELECT h.idDia,
       h.idHora,
       h.idMaterias,
       h.idProfesores,
       m.materia,
       p.apellido,
       p.nombre
  FROM horarios26 AS h
  LEFT JOIN materias AS m ON m.id = h.idMaterias
  LEFT JOIN profesores AS p ON p.id = h.idProfesores
 WHERE ( …filtro curso+materias… )
   AND ( …filtro días activos… )
 ORDER BY h.idHora ASC, m.materia ASC

SQL;

        $legacy = '';
        if (Schema::hasTable('horarios') && $idsMat !== []) {
            $legacy = <<<SQL

── 4) Tabla horarios (legado; si existe) ── mismas materias IN y mismo filtro de días ──

SELECT h.idDia, h.idHora, h.idMaterias, m.materia
  FROM horarios AS h
  LEFT JOIN materias AS m ON m.id = h.idMaterias
 WHERE h.idMaterias IN ({$listaIn})
   AND ( …filtro días activos… )
 ORDER BY h.idHora ASC, m.materia ASC

(En la aplicación estas filas solo completan una celda si horarios26 no aportó ninguna línea para ese día y hora; horarios26 manda cuando hay datos.)

SQL;
        }

        $reloj = <<<SQL

── 5) Reloj de horas (por hoja PDF; un pdf por turno en: {$turnosTxt}) ──
`reloj.orden` siempre 1–10 por nivel; el turno se filtra con `idTurnoClase`.
Ejemplo para el primer turno imprimible de este curso: idTurnoClase = {$idTurnoEjemplo}.

SELECT orden, horario, idTurnoClase
  FROM reloj
 WHERE idNivel = {$idNivel}
   AND orden BETWEEN 1 AND 10
   AND idTurnoClase = {$idTurnoEjemplo}
 ORDER BY orden

(Si no existe la columna `idTurnoClase`, usar solo idNivel y orden 1–10. Lectura en app: ver relojPorTurnoClase() por compat. con `orden` 11–30 antiguo.)

SQL;

        $notaTurno = <<<TXT

── Nota ── Turnos imprimibles derivados del curso: `cursos.idTurnoClase` → catálogo `turnos_clase`.
IDs turno en esta vista: {$turnosTxt}.

TXT;

        return $valida.$mats.$filtroHor26.$legacy.$reloj.$notaTurno;
    }

    /**
     * Resuelve el id de {@see TurnoClase} del curso: solo FK `cursos.idTurnoClase` validado contra el catálogo;
     * si falta o es inválido, el primer id del catálogo.
     */
    public static function idTurnoClaseParaCurso(?int $idTurnoClaseFk): int
    {
        $id = (int) ($idTurnoClaseFk ?? 0);
        $catalogo = self::catalogoTurnosClase();
        $validos = $catalogo->pluck('id')->map(fn ($x) => (int) $x)->all();
        if ($id > 0 && in_array($id, $validos, true)) {
            return $id;
        }

        $activos = self::turnosActivos();
        if ($activos !== []) {
            return (int) $activos[0];
        }

        return (int) ($catalogo->first()->id ?? 1);
    }

    /**
     * Reservado para migraciones que copian texto antiguo de turno a idTurnoClase.
     *
     * @internal
     */
    public static function inferirTurnoClaseDesdeCurso(string $turnoTexto): int
    {
        $t = mb_strtolower(trim($turnoTexto));
        $catalogo = self::catalogoTurnosClase();
        $fallback = (int) ($catalogo->first()->id ?? 1);

        if ($t === '') {
            return $fallback;
        }

        // Error tipográfico frecuente
        if (str_contains($t, 'trarde')) {
            $t = str_replace('trarde', 'tarde', $t);
        }

        // Abreviaturas habituales (p. ej. TM / TT en listados de curso)
        if (preg_match('/\b(tt|t\.?\s*t\.?)\b/u', $t)) {
            return (int) ($catalogo->firstWhere('codigo', 'tarde')->id ?? $fallback);
        }
        if (preg_match('/\b(tm|t\.?\s*m\.?|t\/m)\b/u', $t)) {
            return (int) ($catalogo->firstWhere('codigo', 'manana')->id ?? $fallback);
        }

        foreach ($catalogo as $turno) {
            $codigo = mb_strtolower(trim((string) ($turno->codigo ?? '')));
            $nombre = mb_strtolower(trim((string) ($turno->nombre ?? '')));
            if ($codigo !== '' && (str_contains($t, $codigo) || str_contains($codigo, $t))) {
                return (int) $turno->id;
            }
            if ($nombre !== '' && str_contains($t, $nombre)) {
                return (int) $turno->id;
            }
        }

        if (str_contains($t, 'noche') || str_contains($t, 'noct')) {
            return (int) ($catalogo->firstWhere('codigo', 'noche')->id ?? $catalogo->last()->id ?? $fallback);
        }
        if (str_contains($t, 'tarde') || str_contains($t, 'vespert')) {
            return (int) ($catalogo->firstWhere('codigo', 'tarde')->id ?? $fallback);
        }
        if (str_contains($t, 'mañana') || str_contains($t, 'manana') || $t === 'm') {
            return (int) ($catalogo->firstWhere('codigo', 'manana')->id ?? $fallback);
        }

        return $fallback;
    }

    public static function diaToLegacy(int $dia): string
    {
        return self::DIA_LEGACY_CANONICO[$dia] ?? 'lun';
    }

    /**
     * @return list<string> variantes posibles en horarios26.idDia
     */
    public static function legacyCodigosDia(int $dia): array
    {
        $canon = self::DIA_LEGACY_CANONICO[$dia] ?? null;
        if ($canon === null) {
            return [];
        }

        $base = match ($dia) {
            1 => ['lun', 'lu', '1'],
            2 => ['mar', 'ma', '2'],
            3 => ['mie', 'mié', 'mi', '3'],
            4 => ['jue', 'ju', '4'],
            5 => ['vie', 'vi', '5'],
            6 => ['sab', 'sáb', 'sa', '6'],
            7 => ['dom', 'do', '7'],
            default => [],
        };

        $extra = [];
        foreach ($base as $c) {
            if (mb_strlen($c) >= 2) {
                $extra[] = mb_strtoupper(mb_substr($c, 0, 1)).mb_substr($c, 1);
            }
        }

        return array_values(array_unique(array_merge($base, $extra)));
    }

    /**
     * @param  list<int>  $diasNumeros
     * @return list<string>
     */
    public static function legacyCodigosParaDias(array $diasNumeros): array
    {
        $out = [];
        foreach ($diasNumeros as $d) {
            foreach (self::legacyCodigosDia((int) $d) as $codigo) {
                $out[$codigo] = $codigo;
            }
        }

        return array_values($out);
    }

    public static function diaFromLegacy(string $legacy): int
    {
        $raw = trim($legacy);
        $legacy = self::limpiarIdDiaLegacy($raw);
        if ($legacy === '' && $raw !== '' && preg_match('/^\d+$/', $raw)) {
            $legacy = $raw;
        }
        if ($legacy === '' || $legacy === '0') {
            return 0;
        }
        if (ctype_digit($legacy)) {
            $n = (int) $legacy;

            return ($n >= 1 && $n <= 7) ? $n : 0;
        }

        foreach (self::DIA_LEGACY_CANONICO as $num => $codigo) {
            if ($legacy === $codigo) {
                return $num;
            }
        }

        $pref = mb_substr($legacy, 0, 3);
        foreach (self::DIA_LEGACY_CANONICO as $num => $codigo) {
            if ($pref === $codigo) {
                return $num;
            }
        }

        $map = [
            'lun' => 1, 'lu' => 1, 'l' => 1,
            'mar' => 2, 'ma' => 2, 'm' => 2,
            'mie' => 3, 'mié' => 3, 'mi' => 3, 'x' => 3,
            'jue' => 4, 'ju' => 4, 'j' => 4,
            'vie' => 5, 'vi' => 5, 'v' => 5,
            'sab' => 6, 'sáb' => 6, 'sa' => 6, 's' => 6,
            'dom' => 7, 'do' => 7, 'd' => 7,
            'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7,
        ];

        if (isset($map[$legacy])) {
            return $map[$legacy];
        }

        return $map[$pref] ?? 0;
    }

    private static function limpiarIdDiaLegacy(string $legacy): string
    {
        $legacy = mb_strtolower(trim($legacy));
        if ($legacy === '') {
            return '';
        }

        return preg_replace('/[^a-z0-9áéíóúüñ]/u', '', $legacy) ?? '';
    }

    public static function celdaKey(int $dia, int $hora): string
    {
        return $dia.'-'.$hora;
    }

    public static function relojTieneTurnoClase(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = Schema::hasColumn('reloj', 'idTurnoClase');

        return $cache;
    }

    /**
     * @param  list<int>  $permitidos
     * @return list<int>
     */
    private static function parseIdsList(string $raw, array $permitidos): array
    {
        $permitidosSet = array_flip($permitidos);
        $default = $permitidos !== [] ? [$permitidos[0]] : [1];

        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $ids = [];
        foreach ($parts as $p) {
            if ($p === '' || ! ctype_digit((string) $p)) {
                continue;
            }
            $n = (int) $p;
            if (isset($permitidosSet[$n])) {
                $ids[$n] = $n;
            }
        }
        ksort($ids);

        return $ids !== [] ? array_values($ids) : $default;
    }

    /**
     * Misma restricción que {@see queryHorarios26Carga} pero sin alias de tabla,
     * para poder usar {@see Builder::delete()} en MariaDB (no admite `delete from t as alias`).
     */
    private static function queryHorarios26CargaSinAlias(int $idProfesor, int $idMateria, int $idCurso): Builder
    {
        $q = DB::table('horarios26')
            ->where('idProfesores', $idProfesor);

        if ($idMateria > 0) {
            $idsMat = self::idsMateriasParaCarga($idMateria, $idCurso, $idProfesor);
            $q->whereIn('idMaterias', $idsMat);
        }

        return $q;
    }

    /**
     * Filas de carga: docente + misma asignatura (incluye alias de materia en cursos homólogos, para legados en horarios26).
     */
    private static function queryHorarios26Carga(int $idProfesor, int $idMateria, int $idCurso): Builder
    {
        $q = DB::table('horarios26 as h')
            ->where('h.idProfesores', $idProfesor);

        if ($idMateria > 0) {
            $idsMat = self::idsMateriasParaCarga($idMateria, $idCurso, $idProfesor);
            $q->whereIn('h.idMaterias', $idsMat);
        }

        return $q;
    }

    /**
     * IDs de materia en horarios26 para la grilla de carga: PPC actual, alias mismo nombre en el ciclo,
     * y filas ya guardadas del docente (p. ej. idMaterias de otro ciclo pero misma asignatura y curso lógico).
     *
     * @return list<int>
     */
    private static function idsMateriasParaCarga(int $idMateria, int $idCurso, ?int $idProfesor = null): array
    {
        if ($idMateria <= 0) {
            return [];
        }

        $ids = [$idMateria];

        $ref = DB::table('materias')->where('id', $idMateria)->first([
            'materia', 'idCursos', 'idNivel', 'idTerlec',
        ]);
        if ($ref === null) {
            return $ids;
        }

        $nombreNorm = mb_strtolower(trim((string) ($ref->materia ?? '')));
        $idNivel = (int) ($ref->idNivel ?? 0);
        $idTerlec = (int) ($ref->idTerlec ?? 0);
        $anchorCurso = $idCurso > 0 ? $idCurso : (int) ($ref->idCursos ?? 0);
        $cursosBusqueda = $anchorCurso > 0
            ? array_values(array_unique(array_merge(
                [$anchorCurso],
                self::idsCursosEquivalentes($anchorCurso),
            )))
            : array_values(array_unique(array_filter([
                (int) ($ref->idCursos ?? 0),
            ])));

        if ($nombreNorm === '' || $idNivel <= 0 || $cursosBusqueda === []) {
            return self::idsMateriasParaCargaUnirLegadoHorario26($ids, $idProfesor, $idNivel, $nombreNorm, $anchorCurso);
        }

        $extra = DB::table('materias')
            ->whereIn('idCursos', $cursosBusqueda)
            ->where('idNivel', $idNivel)
            ->whereRaw('LOWER(TRIM(materia)) = ?', [$nombreNorm])
            ->when($idTerlec > 0, fn ($q) => $q->where('idTerlec', $idTerlec))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ids = array_values(array_unique(array_merge($ids, $extra)));

        return self::idsMateriasParaCargaUnirLegadoHorario26($ids, $idProfesor, $idNivel, $nombreNorm, $anchorCurso);
    }

    /**
     * Añade idMaterias que ya figuran en horarios26 para el docente, misma asignatura y curso lógico
     * (cursos homólogos aunque sean de otro idTerlec), para poder marcar la grilla con datos legados.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function idsMateriasParaCargaUnirLegadoHorario26(
        array $ids,
        ?int $idProfesor,
        int $idNivel,
        string $nombreNorm,
        int $anchorCurso,
    ): array {
        if ($idProfesor === null || $idProfesor <= 0 || $nombreNorm === '' || $idNivel <= 0 || $anchorCurso <= 0) {
            return $ids;
        }

        $cursosLectura = self::idsCursosEquivalentes($anchorCurso, false);

        $legacyIds = DB::table('horarios26 as h')
            ->join('materias as m', 'm.id', '=', 'h.idMaterias')
            ->where('h.idProfesores', $idProfesor)
            ->where('m.idNivel', $idNivel)
            ->whereRaw('LOWER(TRIM(m.materia)) = ?', [$nombreNorm])
            ->whereIn('m.idCursos', $cursosLectura)
            ->where(function ($w) use ($cursosLectura) {
                $w->whereIn('h.idCursos', $cursosLectura)
                    ->orWhereNull('h.idCursos')
                    ->orWhere('h.idCursos', 0);
            })
            ->distinct()
            ->pluck('h.idMaterias')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($ids, $legacyIds)));
    }

    /**
     * IDs de materias del curso en el nivel (mismo ciclo lectivo que el curso ancla).
     *
     * @return list<int>
     */
    private static function idsMateriasCursoNivel(int $idCurso, ?int $idNivel = null): array
    {
        if ($idCurso <= 0) {
            return [];
        }

        $idNivel = $idNivel ?? (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            return [];
        }

        $cursosIds = self::idsCursosEquivalentes($idCurso);

        return DB::table('materias')
            ->whereIn('idCursos', $cursosIds)
            ->where('idNivel', $idNivel)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Solo materías del ciclo vigente declaradas sobre este Id de curso (sin “homólogos”).
     * Uso en impresión de grilla por curso para no mezclar divisiones paralelas ni idMaterias ajenos.
     *
     * @return list<int>
     */
    private static function idsMateriasSoloEsteCurso(int $idCurso, ?int $idNivel = null, ?int $idTerlec = null): array
    {
        if ($idCurso <= 0) {
            return [];
        }

        [$idNivel, $idTerlec] = self::contextoHorariosNivelTerlec($idNivel, $idTerlec);
        if ($idNivel <= 0 || $idTerlec <= 0) {
            return [];
        }

        return DB::table('materias')
            ->where('idCursos', $idCurso)
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Cursos homólogos (misma sección / plan en el nivel). Por defecto solo el mismo ciclo lectivo.
     *
     * @param  bool  $soloMismoTerlec  Si false, incluye homólogos en otros idTerlec (solo para enlazar legados en carga).
     * @return list<int> cursos.Id
     */
    private static function idsCursosEquivalentes(int $idCurso, bool $soloMismoTerlec = true): array
    {
        if ($idCurso <= 0) {
            return [];
        }

        $ref = DB::table('cursos')->where('Id', $idCurso)->first([
            'idNivel', 'idTerlec', 'cursec', 'orden', 'c', 's', 'idCurPlan', 'idTurnoClase',
        ]);
        if ($ref === null) {
            return [$idCurso];
        }

        $idNivel = (int) ($ref->idNivel ?? 0);
        $idTerlecRef = (int) ($ref->idTerlec ?? 0);
        $q = DB::table('cursos')->where('idNivel', $idNivel);
        if ($soloMismoTerlec && $idTerlecRef > 0) {
            $q->where('idTerlec', $idTerlecRef);
        }

        $cursec = trim((string) ($ref->cursec ?? ''));
        if ($cursec !== '') {
            $q->where('cursec', $cursec);
            $idTcRef = (int) ($ref->idTurnoClase ?? 0);
            if ($idTcRef > 0) {
                $q->where('idTurnoClase', $idTcRef);
            }
        } else {
            if ((int) ($ref->idCurPlan ?? 0) > 0) {
                $q->where('idCurPlan', (int) $ref->idCurPlan);
            }
            if ($ref->orden !== null && $ref->orden !== '') {
                $q->where('orden', $ref->orden);
            }
            $idTcRef = (int) ($ref->idTurnoClase ?? 0);
            if ($idTcRef > 0) {
                $q->where('idTurnoClase', $idTcRef);
            } else {
                foreach (['c', 's'] as $col) {
                    $val = trim((string) ($ref->{$col} ?? ''));
                    if ($val !== '') {
                        $q->where($col, $val);
                    }
                }
            }
        }

        $ids = $q->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $ids !== [] ? $ids : [$idCurso];
    }

    /**
     * Misma materia en el mismo ciclo lectivo (mismo nombre en el curso lógico).
     *
     * @return list<int>
     */
    private static function idsMateriasEquivalentes(int $idMateria, int $idCurso): array
    {
        if ($idMateria <= 0) {
            return [];
        }

        $ctx = schoolCtx();
        $ref = DB::table('materias')->where('id', $idMateria)->first(['materia', 'idCursos', 'idNivel']);
        if ($ref === null) {
            return [$idMateria];
        }

        $curso = $idCurso > 0 ? $idCurso : (int) ($ref->idCursos ?? 0);
        $nombre = trim((string) ($ref->materia ?? ''));
        if ($curso <= 0 || $nombre === '') {
            return [$idMateria];
        }

        $cursosIds = self::idsCursosEquivalentes($curso);
        $nombreNorm = mb_strtolower($nombre);

        $ids = DB::table('materias')
            ->whereIn('idCursos', $cursosIds)
            ->where('idNivel', (int) ($ref->idNivel ?? $ctx->idNivel))
            ->whereRaw('LOWER(TRIM(materia)) = ?', [$nombreNorm])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $ids !== [] ? $ids : [$idMateria];
    }

    /**
     * Materias de horarios26 que pueden figurar en la grilla/impr. del docente: solo PPC vigente,
     * con la misma ampliación que la carga (homólogos, legado), no todas las del curso.
     *
     * @return list<int>
     */
    private static function idsMateriasPermitidasImpresionProfesor(int $idProfesor): array
    {
        if (isset(self::$idsMateriasPermitidasProfesorCache[$idProfesor])) {
            return self::$idsMateriasPermitidasProfesorCache[$idProfesor];
        }

        $ids = [];
        foreach (self::asignacionesProfesor($idProfesor) as $a) {
            $idM = (int) ($a->idMateria ?? 0);
            $idC = (int) ($a->idCursos ?? 0);
            if ($idM <= 0) {
                continue;
            }
            foreach (self::idsMateriasParaCarga($idM, $idC, $idProfesor) as $idMat) {
                if ($idMat > 0) {
                    $ids[$idMat] = $idMat;
                }
            }
        }

        return self::$idsMateriasPermitidasProfesorCache[$idProfesor] = array_values($ids);
    }

    /**
     * @param  list<int>  $idsMat
     */
    private static function aplicarFiltroHorarios26Curso(Builder $q, int $idCurso, array $idsMat): void
    {
        if ($idCurso <= 0 && $idsMat === []) {
            $q->whereRaw('1 = 0');

            return;
        }

        $q->where(function ($w) use ($idCurso, $idsMat) {
            if ($idCurso > 0) {
                $w->where('h.idCursos', $idCurso);
            }
            if ($idsMat !== []) {
                $idCurso > 0
                    ? $w->orWhereIn('h.idMaterias', $idsMat)
                    : $w->whereIn('h.idMaterias', $idsMat);
            }
        });
    }

    /**
     * Filtro de horarios26 para la grilla de un único curso: no usar OR laxo materia⊂homólogos que importa huecos de otras divisiones.
     *
     * @param  list<int>  $idsMat  Debe ser {@see idsMateriasSoloEsteCurso()}.
     */
    private static function aplicarFiltroHorarios26GrillaSoloEsteCurso(Builder $q, int $idCurso, array $idsMat): void
    {
        if ($idCurso <= 0 || $idsMat === []) {
            $q->whereRaw('1 = 0');

            return;
        }

        $q->where(function ($w) use ($idCurso, $idsMat) {
            $w->where(function ($a) use ($idCurso, $idsMat) {
                $a->where('h.idCursos', $idCurso)
                    ->whereIn('h.idMaterias', $idsMat);
            })->orWhere(function ($b) use ($idsMat) {
                $b->where(function ($z) {
                    $z->whereNull('h.idCursos')->orWhere('h.idCursos', 0);
                })->whereIn('h.idMaterias', $idsMat);
            });
        });
    }

    /**
     * Impresión por docente: solo filas cuya materia corresponde a una asignación PPC (misma lógica que carga).
     * Evita atribuir horas de otras materias del mismo curso solo por coincidir el curso o el id del docente en legados confusos.
     *
     * @param  list<int>  $idsMat
     */
    private static function aplicarFiltroHorarios26ProfesorSoloMateriasAsignadas(Builder $q, array $idsMat): void
    {
        if ($idsMat === []) {
            $q->whereRaw('1 = 0');

            return;
        }

        $q->whereIn('h.idMaterias', $idsMat);
    }

    private static function etiquetaMateriaHorario(int $idMateriaLegacy, string $nombreJoin, int $idCurso): string
    {
        $nombre = trim($nombreJoin);
        if ($nombre !== '') {
            return $nombre;
        }

        if ($idMateriaLegacy <= 0) {
            return '';
        }

        $ctx = schoolCtx();
        $q = DB::table('materias')->where('id', $idMateriaLegacy);
        if ($idCurso > 0) {
            $q->where('idCursos', $idCurso);
        }
        $nombre = trim((string) ($q->value('materia') ?? ''));
        if ($nombre !== '') {
            return $nombre;
        }

        return trim((string) (DB::table('materias')
            ->where('id', $idMateriaLegacy)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->value('materia') ?? ''));
    }

    /** Texto de matería en PDF horario por curso: mayúsculas sostenidas. */
    private static function materiaImpresionCursoPdf(string $nombre): string
    {
        $t = trim($nombre);

        return $t === '' ? '' : mb_strtoupper($t, 'UTF-8');
    }

    /**
     * Docente(s) en PDF horario por curso: título por palabra (apellido, nombre y varios con « / »).
     */
    private static function nombreProfesorImpresionCursoPdf(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }

        $bloques = preg_split('/\s*\/\s*/u', $texto) ?: [];
        $out = [];
        foreach ($bloques as $bloque) {
            $f = self::nombreUnProfesorTitleCase(trim($bloque));
            if ($f !== '') {
                $out[] = $f;
            }
        }

        return implode(' / ', $out);
    }

    private static function nombreUnProfesorTitleCase(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }

        if (str_contains($s, ',')) {
            $parts = explode(',', $s, 2);
            $ap = self::palabrasTitleCase(trim($parts[0] ?? ''));
            $no = self::palabrasTitleCase(trim($parts[1] ?? ''));

            return $no !== '' ? $ap.', '.$no : $ap;
        }

        return self::palabrasTitleCase($s);
    }

    private static function palabrasTitleCase(string $s): string
    {
        $s = trim($s);

        return $s === '' ? '' : mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    }

    private static function aplicarFiltroIdDiaCanonicoEnQuery(Builder $q, string $column, string $diaCanon): void
    {
        $diaNum = self::diaNumeroDesdeLegacy($diaCanon);
        if ($diaNum > 0) {
            self::aplicarFiltroDiasLegacyEnQuery($q, $column, [$diaNum]);
        }
    }

    /**
     * Lectura de grilla/PDF: filas del turno con idHora 1–10 + idTurnoClase, o legado 11–20 sin migrar.
     */
    private static function aplicarFiltroFilasDeTurnoEnLectura(
        Builder $q,
        string $columnHora,
        ?string $columnTurno,
        int $idTurnoClase,
    ): void {
        if (self::horarios26UsaIdTurnoClase() && $columnTurno !== null) {
            $legacyBase = self::baseHoraLegacyPorTurnoClase($idTurnoClase);
            $q->where(function ($w) use ($columnHora, $columnTurno, $idTurnoClase, $legacyBase) {
                $w->where(function ($wN) use ($columnHora, $columnTurno, $idTurnoClase) {
                    $wN->whereBetween($columnHora, [1, self::HORAS_POR_TURNO])
                        ->where($columnTurno, $idTurnoClase);
                })->orWhere(function ($wL) use ($columnHora, $columnTurno, $legacyBase) {
                    $wL->whereBetween($columnHora, [$legacyBase + 1, $legacyBase + self::HORAS_POR_TURNO])
                        ->where(function ($wNull) use ($columnTurno) {
                            $wNull->whereNull($columnTurno)->orWhere($columnTurno, 0);
                        });
                });
            });

            return;
        }

        self::aplicarFiltroIdHoraRangoTurnoEnQuery($q, $columnHora, $idTurnoClase);
    }

    /**
     * Sin columna idTurnoClase: rango idHora 1–10 / 11–20 / 21–30.
     */
    private static function aplicarFiltroIdHoraRangoTurnoEnQuery(Builder $q, string $column, int $idTurnoClase): void
    {
        $base = self::baseHoraLegacyPorTurnoClase($idTurnoClase);
        $desde = $base + 1;
        $hasta = $base + self::HORAS_POR_TURNO;

        $turnosAct = self::turnosActivos();
        $incluirLegado = count($turnosAct) === 1
            && self::indiceBloqueTurnoClase((int) $turnosAct[0]) > 0;

        if (! $incluirLegado) {
            $q->whereBetween($column, [$desde, $hasta]);

            return;
        }

        $q->where(function ($w) use ($column, $desde, $hasta) {
            $w->whereBetween($column, [$desde, $hasta])
                ->orWhereBetween($column, [1, self::HORAS_POR_TURNO]);
        });
    }

    /**
     * Filtro idDia insensible a mayúsculas (lun, Lun, LUN, etc.).
     *
     * @param  list<int>  $diasNumeros
     */
    private static function aplicarFiltroDiasLegacyEnQuery(Builder $q, string $column, array $diasNumeros): void
    {
        $codigos = [];
        foreach ($diasNumeros as $d) {
            foreach (self::legacyCodigosDia((int) $d) as $c) {
                $codigos[mb_strtolower(trim($c))] = true;
            }
        }
        if ($codigos === []) {
            return;
        }

        $q->where(function ($w) use ($column, $codigos) {
            foreach (array_keys($codigos) as $codigo) {
                $w->orWhereRaw('LOWER(TRIM('.$column.')) = ?', [$codigo]);
            }
        });
    }

    private static function normalizarHoraLegacy(int $hora): int
    {
        if ($hora >= 1 && $hora <= self::HORAS_RELOJ_MAX_LEGACY) {
            return self::slotDesdeIdHoraLegacy($hora);
        }
        if ($hora >= 0 && $hora < self::HORAS_POR_TURNO) {
            return $hora + 1;
        }

        return 0;
    }

    /**
     * @param  array<string, list<string>>  $celdas
     * @param  bool  $soloRellenarHueco  Si true, no escribe si la celda día/hora ya tiene al menos una línea (prioridad para la fuente previa).
     */
    private static function agregarLineaGrilla(
        array &$celdas,
        string $idDiaLegacy,
        int $idHoraLegacy,
        string $textoPrincipal,
        string $sufijoProfesor,
        bool $soloRellenarHueco = false,
    ): void {
        $dia = self::diaFromLegacy($idDiaLegacy);
        $hora = self::normalizarHoraLegacy($idHoraLegacy);
        if ($dia < 1 || $hora < 1) {
            return;
        }

        $linea = trim($textoPrincipal);
        $prof = trim($sufijoProfesor);
        if ($prof !== '') {
            $linea = $linea !== '' ? $linea.' — '.$prof : $prof;
        }
        if ($linea === '') {
            return;
        }

        $key = self::celdaKey($dia, $hora);
        if ($soloRellenarHueco && isset($celdas[$key]) && $celdas[$key] !== []) {
            return;
        }

        $celdas[$key] ??= [];
        if (! in_array($linea, $celdas[$key], true)) {
            $celdas[$key][] = $linea;
        }
    }

    /**
     * Nombre docente junto al texto de materia en grilla por curso: sólo muestra profesor cargado si está en ppc;
     * si no, cae la lista sólo‑ppc para esa materia (alineado con el módulo de asignación).
     *
     * @param  object{apellido?: string|null, nombre?: string|null}  $r
     */
    private static function nombreProfesorFilaImpresionCurso(object $r, int $idMateria, int $idProfesor, int $idCurso): string
    {
        if ($idMateria <= 0 || $idCurso <= 0) {
            return self::nombreProfesorFila($r, $idMateria, $idProfesor);
        }

        $delJoin = trim(((string) ($r->apellido ?? '')).', '.((string) ($r->nombre ?? '')));
        $delJoin = trim($delJoin, ', ');

        if ($idProfesor > 0) {
            if (! self::profesorTieneAsignacion($idProfesor, $idMateria, $idCurso)) {
                return self::nombresProfesoresMateria($idMateria);
            }
            if ($delJoin !== '') {
                return $delJoin;
            }
            $p = DB::table('profesores')->where('id', $idProfesor)->first(['apellido', 'nombre']);
            if ($p) {
                return trim(((string) $p->apellido).', '.((string) $p->nombre));
            }

            return self::nombresProfesoresMateria($idMateria);
        }

        return self::nombreProfesorFila($r, $idMateria, $idProfesor);
    }

    private static function nombreProfesorFila(object $r, int $idMateria, int $idProfesor): string
    {
        $prof = trim(((string) ($r->apellido ?? '')).', '.((string) ($r->nombre ?? '')));
        $prof = trim($prof, ', ');

        if ($prof !== '') {
            return $prof;
        }

        if ($idProfesor > 0) {
            $p = DB::table('profesores')->where('id', $idProfesor)->first(['apellido', 'nombre']);
            if ($p) {
                return trim(((string) $p->apellido).', '.((string) $p->nombre));
            }
        }

        return self::nombresProfesoresMateria($idMateria);
    }

    private static function nombresProfesoresMateria(int $idMateria): string
    {
        if ($idMateria <= 0) {
            return '';
        }

        $nombres = DB::table('ppc as ppc')
            ->join('profesores as p', 'p.id', '=', 'ppc.idProfesor')
            ->where('ppc.idMateria', $idMateria)
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->get(['p.apellido', 'p.nombre'])
            ->map(fn ($p) => trim(((string) $p->apellido).', '.((string) $p->nombre)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return implode(' / ', $nombres);
    }

    private static function profesorTieneAsignacion(int $idProfesor, int $idMateria, int $idCurso): bool
    {
        $ctx = schoolCtx();

        return DB::table('ppc as ppc')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->where('ppc.idProfesor', $idProfesor)
            ->where('ppc.idMateria', $idMateria)
            ->where('m.idCursos', $idCurso)
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->exists();
    }

    /**
     * Texto SQL / notas del módulo «Configuración de horarios» (`horarios_config`, `reloj`).
     * Ligero: no arma listas IN grandes; solo contexto de sesión y turno de reloj elegido.
     * Convención: `docs/05-preferencias-y-convenciones.md` §10.
     */
    public static function textoDepuracionSqlConfigHorarios(int $idTurnoReloj): string
    {
        $ctx = schoolCtx();
        $idNivel = abs((int) $ctx->idNivel);
        $idTerlec = abs((int) $ctx->idTerlec);
        if ($idNivel <= 0) {
            return 'Nivel no definido en el contexto de sesión (schoolCtx).';
        }

        $idTurno = self::idTurnoClaseParaCurso($idTurnoReloj > 0 ? $idTurnoReloj : null);
        $nombreTurno = self::nombreTurnoClase($idTurno);
        $relojTieneTc = self::relojTieneTurnoClase() ? 'sí' : 'no';

        $turnosTxt = implode(', ', array_map(fn ($t) => (string) abs((int) $t), self::turnosActivos()));
        $diasTxt = implode(', ', array_map(fn ($d) => (string) abs((int) $d), self::diasActivos()));

        $catIds = self::catalogoTurnosClase()->pluck('id')->map(fn ($x) => (string) abs((int) $x))->implode(', ');

        $sqlRelojLectura = '';
        if ($relojTieneTc === 'sí') {
            $sqlRelojLectura = <<<SQL

SELECT orden, horario, idTurnoClase
  FROM reloj
 WHERE idNivel = {$idNivel}
   AND orden BETWEEN 1 AND 10
   AND idTurnoClase = {$idTurno}
 ORDER BY orden

(El código también lee filas antiguas `orden` 11–30 y `idTurnoClase` NULL en 1–10 solo primer turno — ver relojPorTurnoClase().)
SQL;
        } else {
            $sqlRelojLectura = <<<SQL

SELECT orden, horario
  FROM reloj
 WHERE idNivel = {$idNivel}
   AND orden BETWEEN 1 AND 10
 ORDER BY orden
SQL;
        }

        return <<<TXT
── Configuración de horarios (pantalla ABM config) ──
Contexto sesión: idNivel={$idNivel}, idTerlec={$idTerlec} (ciclo lectivo autogestión).

── 1) Lectura `horarios_config` (turnos y días activos del nivel) ──
(MySQL: clave idNivel.)

SELECT idNivel, turnos_activos, dias_activos
  FROM horarios_config
 WHERE idNivel = {$idNivel}
 LIMIT 1

── 2) Catálogo `turnos_clase` (define bloques de `horarios26.idHora` 1–30; en `reloj` solo importa id + FK) ──
IDs en catálogo: {$catIds}

SELECT id, codigo, nombre, orden
  FROM turnos_clase
 ORDER BY orden, id

── 3) Lectura `reloj` para el turno seleccionado en el formulario ──
Turno normalizado: id={$idTurno} ({$nombreTurno}).
`reloj.orden` siempre 1..10 por nivel; discriminación por `idTurnoClase`.
Tabla `reloj` tiene columna `idTurnoClase`: {$relojTieneTc}.
{$sqlRelojLectura}

── 4) Guardar turnos y días (updateOrCreate en Laravel) ──

INSERT INTO horarios_config (idNivel, turnos_activos, dias_activos)
VALUES ({$idNivel}, '<ids csv turnos>', '<ids csv días>')
ON DUPLICATE KEY UPDATE
  turnos_activos = VALUES(turnos_activos),
  dias_activos = VALUES(dias_activos);

── 5) Guardar horario reloj (por cada módulo 1..10 del turno) ──
UPDATE reloj SET horario = '<texto>', idTurnoClase = {$idTurno}
 WHERE idNivel = {$idNivel} AND orden = <1..10> AND idTurnoClase = {$idTurno}
INSERT … (orden 1..10, idNivel, horario, idTurnoClase) si no hay fila y el texto no es vacío.

── Referencia resuelta en esta sesión ──
turnosActivos(): {$turnosTxt}
diasActivos(): {$diasTxt}

TXT;
    }

    /**
     * Cadena textual con SQL representative del módulo «Carga de horarios» para depuración en pantalla.
     * Solo ids enteros (contexto sesión / formulario); no exponer entrada cruda libre sin validar antes.
     * Ejecuta trabajo pesado cuando la grilla está armada (helpers IN / listas): no invocar con depuración apagada.
     * Convención: `docs/05-preferencias-y-convenciones.md` §10.
     */
    public static function textoDepuracionSqlCargaHorarios(?int $idProfesor, ?int $idMateria, ?int $idCurso): string
    {
        $ctx = schoolCtx();
        $idNivel = abs((int) $ctx->idNivel);
        $idTerlec = abs((int) $ctx->idTerlec);
        $idProf = (int) ($idProfesor ?? 0);
        $idMat = (int) ($idMateria ?? 0);
        $idC = (int) ($idCurso ?? 0);

        $combo = <<<SQL
── Lista docentes (combo; todos con IdTipoProf distinto de «Sin Rol» (1); NULL permitido) ──

SELECT p.id, p.apellido, p.nombre
FROM profesores AS p
WHERE (p.IdTipoProf IS NULL OR p.IdTipoProf <> 1)
SQL;

        if ($idNivel > 0) {
            $combo .= <<<SQL
  AND (
        p.nivel = {$idNivel}
        OR p.nivel IS NULL
        OR p.nivel = 0
      )
SQL;
        }

        $combo .= <<<SQL
ORDER BY p.apellido, p.nombre

SQL;

        if ($idProf <= 0) {
            return $combo . "\n(Seleccioná un docente para ver asignaciones PPC, grilla horarios26, etc.)";
        }

        $ppcAsig = <<<SQL

── Materias/Cursos disponibles para el docente (asignaciones PPC seleccionadas) ──

SELECT ppc.idMateria, m.idCursos AS idCursos, m.materia, c.cursec, c.orden, c.idTurnoClase, c.c, c.s, c.idCurPlan
FROM ppc AS ppc
INNER JOIN materias AS m ON m.id = ppc.idMateria
INNER JOIN cursos AS c ON c.Id = m.idCursos
WHERE ppc.idProfesor = {$idProf}
  AND m.idNivel = {$idNivel}
  AND m.idTerlec = {$idTerlec}
  AND c.idNivel = {$idNivel}
  AND c.idTerlec = {$idTerlec}
ORDER BY c.orden, c.cursec, m.ord, m.materia

SQL;

        if ($idMat <= 0 || $idC <= 0) {
            return $combo . $ppcAsig . "\n(Seleccioná curso+matería para las consultas de la grilla horarios26)";
        }

        $idsParaGrilla = self::idsMateriasParaCarga($idMat, $idC, $idProf);
        $listaIn = $idsParaGrilla === [] ? '0' : implode(', ', array_map(fn ($id) => (string) abs((int) $id), $idsParaGrilla));

        $verificaPpc = <<<SQL

── Antes de marcar/desmarcar: existe PPC docente‑materia‑curso ({$idProf}, {$idMat}, curso {$idC}) ──

SELECT 1
FROM ppc AS ppc
INNER JOIN materias AS m ON m.id = ppc.idMateria
WHERE ppc.idProfesor = {$idProf}
  AND ppc.idMateria = {$idMat}
  AND m.idCursos = {$idC}
  AND m.idNivel = {$idNivel}
  AND m.idTerlec = {$idTerlec}
LIMIT 1

SQL;

        $grilla = <<<SQL

── Celda marcadas: lectura desde horarios26 (ids de materias ampliados para carga legada/homónimos: IN ({$listaIn})) ──

SELECT h.idDia, h.idHora
FROM horarios26 AS h
WHERE h.idProfesores = {$idProf}
  AND h.idMaterias IN ({$listaIn})

SQL;

        $conflicto = <<<SQL

── Al intentar marcar celda nueva: ¿el mismo docente ya tiene hora en otro curso? (mismo idDia + idHora; idCursos distinto) ──

SELECT m.materia, c.cursec, …
FROM horarios26 AS h
INNER JOIN cursos AS c ON c.Id = h.idCursos
LEFT JOIN materias AS m ON m.id = h.idMaterias
WHERE h.idProfesores = {$idProf}
  AND h.idCursos <> {$idC}
  AND h.idHora = (según módulo pulsado y turno del curso)
  AND c.idNivel = {$idNivel}
  AND c.idTerlec = {$idTerlec}
  AND día igual a la celda pulsada …
LIMIT 1

SQL;

        return $combo.$ppcAsig.$verificaPpc.$grilla."\n".$conflicto;
    }

    /**
     * SQL illustrative del último intento de alternar una celda (insert/delete/select exists).
     * Arma texto con listas de ids (llama a helpers de resolución de materias). Solo con depuración activa en UI.
     * Convención: `docs/05-preferencias-y-convenciones.md` §10.
     */
    public static function textoDepuracionSqlAlternarUltimaAccion(
        int $idProfesor,
        int $idMateria,
        int $idCurso,
        string $diaLegacyPasadoPorLivewire,
        int $hora,
        bool $queriaMarcarCelda,
        int $indiceBloqueHorario = 0,
    ): string {
        $diaCanon = self::normalizarIdDiaCanonico(trim($diaLegacyPasadoPorLivewire));
        $diaCanon = $diaCanon !== null ? addslashes($diaCanon) : addslashes(trim($diaLegacyPasadoPorLivewire));
        $idProf = abs((int) $idProfesor);
        $idMat = abs((int) $idMateria);
        $idC = abs((int) $idCurso);
        $horaNorm = abs((int) $hora);
        $idsMat = self::idsMateriasParaCarga($idMat, $idC, $idProf);
        $listaIn = $idsMat === [] ? '0' : implode(', ', array_map(fn ($id) => (string) abs((int) $id), $idsMat));

        $idTcCurso = self::idTurnoClaseParaCurso((int) (DB::table('cursos')->where('Id', $idC)->value('idTurnoClase') ?? 0) ?: null);
        if (self::esTurnoClaseDobleJornada($idTcCurso)) {
            $bandas = self::idsTurnoClaseBandasMananaTarde();
            $idx = max(0, min(1, $indiceBloqueHorario));
            $idTurnoParaHora = $bandas[$idx];
        } else {
            $idTurnoParaHora = $idTcCurso;
        }
        $idHoraBd = self::idHoraLegacyDesdeTurnoYSlot($idTurnoParaHora, $horaNorm);

        $existeSql = <<<SQL
SELECT id FROM horarios26
 WHERE idProfesores = {$idProf}
   AND idMaterias IN ({$listaIn})
   AND idHora = {$idHoraBd}
   AND (LOWER(TRIM(idDia)) en variantes día según día «{$diaCanon}» como en Laravel)
LIMIT 1
SQL;

        if ($queriaMarcarCelda) {
            $ins = <<<SQL

── INSERT al marcar (si no existe y no hay conflicto) ──
Nota: {$horaNorm} = módulo 1..10 en pantalla; `idHora` en BD = {$idHoraBd} (bloque mañana/tarde/noche).

INSERT INTO horarios26 (idProfesores, idMaterias, idDia, idHora, idCursos)
VALUES ({$idProf}, {$idMat}, '{$diaCanon}', {$idHoraBd}, {$idC})

SQL;

            return "── Última acción celda / marcar ──\n".$existeSql.$ins;
        }

        $del = <<<SQL

── DELETE al desmarcar ──

DELETE FROM horarios26
 WHERE idProfesores = {$idProf}
   AND idMaterias IN ({$listaIn})
   AND idHora = {$idHoraBd}
   AND (LOWER(TRIM(idDia)) … día «{$diaCanon}»)

SQL;

        return "── Última acción celda / desmarcar ──\n".$existeSql."\n".$del;
    }
}
