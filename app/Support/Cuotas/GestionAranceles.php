<?php

namespace App\Support\Cuotas;

use App\Models\CuotaGenerada;
use App\Models\CuotaTipoPago;
use App\Models\CuotasBeca;
use App\Models\Legajo;
use App\Models\Matricula;
use App\Models\Terlec;
use App\Support\MatriculaNivelEstilo;
use App\Support\SchoolAlcancePedagogico;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Consultas del módulo Gestión de aranceles (nivel Administración).
 */
final class GestionAranceles
{
    public static function legajoParaGestion(int $idLegajo): ?Legajo
    {
        return Legajo::query()
            ->whereKey($idLegajo)
            ->first(['id', 'apellido', 'nombre', 'dni', 'legajo', 'idFamilias']);
    }

    /**
     * Legajo con datos de familia y vínculos para facturación AFIP (devengamiento).
     */
    public static function legajoParaFacturacionAfip(int $idLegajo): ?Legajo
    {
        return Legajo::query()
            ->with(['familia:id,apellido,responsable,dniResp,email'])
            ->whereKey($idLegajo)
            ->first([
                'id',
                'apellido',
                'nombre',
                'dni',
                'legajo',
                'idFamilias',
                'nombrepad',
                'dnipad',
                'emailpad',
                'nombremad',
                'dnimad',
                'emailmad',
                'nombretut',
                'dnitut',
                'emailtut',
            ]);
    }

    /**
     * Vista normal: todas las cuotas del ciclo activo y las impagas de años anteriores.
     *
     * @return Collection<int, CuotaGenerada>
     */
    public static function cuotasDelEstudiante(int $idLegajo): Collection
    {
        $idTerlec = (int) schoolCtx()->idTerlec;

        return self::aplicarOrdenCuotasPorAnoYCuota(
            self::aplicarFiltroCuotasVistaNormal(
                self::consultaCuotasEstudiante($idLegajo),
                $idTerlec,
            ),
        )->get();
    }

    /**
     * Historial completo: todas las cuotas del estudiante en cualquier ciclo lectivo,
     * ordenadas por año lectivo y concepto (reserva y matrícula primero en cada año).
     *
     * @return Collection<int, CuotaGenerada>
     */
    public static function cuotasHistorial(int $idLegajo): Collection
    {
        return self::aplicarOrdenCuotasPorAnoYCuota(
            self::consultaCuotasEstudiante($idLegajo),
        )->get();
    }

    /**
     * Año del contexto (todas las cuotas, pagadas o no) + impagas de años lectivos anteriores.
     *
     * @param  Builder<CuotaGenerada>  $query
     * @return Builder<CuotaGenerada>
     */
    private static function aplicarFiltroCuotasVistaNormal(Builder $query, int $idTerlec): Builder
    {
        $tabla = self::tablaCuotasGeneradas();
        $anoActivo = schoolCtx()->terlecAno();

        if ($anoActivo === null) {
            return $query->where(function (Builder $q) use ($idTerlec, $tabla): void {
                $q->where("{$tabla}.idTerlec", $idTerlec)
                    ->orWhere(function (Builder $q2) use ($idTerlec, $tabla): void {
                        $q2->where("{$tabla}.faltapa", '>', 0)
                            ->where("{$tabla}.idTerlec", '!=', $idTerlec);
                    });
            });
        }

        return $query->where(function (Builder $q) use ($tabla, $anoActivo): void {
            $q->whereIn("{$tabla}.idTerlec", fn ($sub) => self::subqueryIdsTerlecPorAno($sub, '=', $anoActivo))
                ->orWhere(function (Builder $q2) use ($tabla, $anoActivo): void {
                    $q2->where("{$tabla}.faltapa", '>', 0)
                        ->whereIn("{$tabla}.idTerlec", fn ($sub) => self::subqueryIdsTerlecPorAno($sub, '<', $anoActivo));
                });
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $sub
     */
    private static function subqueryIdsTerlecPorAno($sub, string $operador, int $ano): void
    {
        $sub->select('id')->from('terlec')->where('ano', $operador, $ano);
    }

    private static function tablaCuotasGeneradas(): string
    {
        return (new CuotaGenerada)->getTable();
    }

    /**
     * Año lectivo (terlec.ano) → orden de plantilla → reserva/matrícula primero → mes → vencimiento.
     *
     * @param  Builder<CuotaGenerada>  $query
     * @return Builder<CuotaGenerada>
     */
    private static function aplicarOrdenCuotasPorAnoYCuota(Builder $query): Builder
    {
        $tabla = self::tablaCuotasGeneradas();
        $reserva = GeneracionCuotaEstudianteService::TIPO_RESERVA;
        $matricula = GeneracionCuotaEstudianteService::TIPO_MATRICULA;

        return $query
            ->leftJoin('terlec', 'terlec.id', '=', "{$tabla}.idTerlec")
            ->leftJoin('cuotas', 'cuotas.id', '=', "{$tabla}.idCuotas")
            ->orderBy('terlec.ano')
            ->orderByRaw('COALESCE(cuotas.orden, 9999)')
            ->orderByRaw("CASE WHEN {$tabla}.idCuotastipo = {$reserva} THEN 0 WHEN {$tabla}.idCuotastipo = {$matricula} THEN 1 ELSE 2 END")
            ->orderBy("{$tabla}.idCuotasmeses")
            ->orderBy("{$tabla}.venc1")
            ->orderBy("{$tabla}.id")
            ->select("{$tabla}.*");
    }

    /**
     * @return Builder<CuotaGenerada>
     */
    private static function consultaCuotasEstudiante(int $idLegajo): Builder
    {
        $tabla = self::tablaCuotasGeneradas();

        return CuotaGenerada::query()
            ->with([
                'legajo:id,apellido,nombre,dni',
                'terlec:id,ano',
                'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                'curso.curplan:id,curPlanCurso',
                'curso.turnoClase:id,nombre',
                'curso.nivel:id,nivel',
                'cuota:id,nombre,orden,idCuotastipo',
            ])
            ->where("{$tabla}.idLegajos", $idLegajo);
    }

    public static function cuotaParaGestion(int $idCuotaGenerada, int $idLegajo): ?CuotaGenerada
    {
        $idTerlec = (int) schoolCtx()->idTerlec;

        return self::aplicarFiltroCuotasVistaNormal(
            CuotaGenerada::query()
                ->with([
                    'legajo:id,apellido,nombre,dni',
                    'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                    'curso.curplan:id,curPlanCurso',
                    'curso.turnoClase:id,nombre',
                    'curso.nivel:id,nivel',
                    'cuota:id,nombre',
                ])
                ->whereKey($idCuotaGenerada)
                ->where('idLegajos', $idLegajo),
            $idTerlec,
        )->first();
    }

    /**
     * Cuota del legajo sin filtro de ciclo activo / deuda pendiente (comprobantes PDF tras imputar).
     */
    public static function cuotaDelLegajo(int $idCuotaGenerada, int $idLegajo): ?CuotaGenerada
    {
        return CuotaGenerada::query()
            ->with([
                'legajo:id,apellido,nombre,dni',
                'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                'curso.curplan:id,curPlanCurso',
                'curso.turnoClase:id,nombre',
                'curso.nivel:id,nivel',
                'cuota:id,nombre',
            ])
            ->whereKey($idCuotaGenerada)
            ->where('idLegajos', $idLegajo)
            ->first();
    }

    /**
     * Varias cuotas del estudiante para imputación (misma relación que cuotaParaGestion).
     *
     * @param  list<int>  $idsCuotasGeneradas
     * @return \Illuminate\Support\Collection<int, CuotaGenerada>
     */
    public static function cuotasParaImputacion(array $idsCuotasGeneradas, int $idLegajo): \Illuminate\Support\Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsCuotasGeneradas), fn (int $id) => $id > 0)));
        if ($ids === []) {
            return collect();
        }

        $idTerlec = (int) schoolCtx()->idTerlec;
        $tabla = self::tablaCuotasGeneradas();

        return self::aplicarOrdenCuotasPorAnoYCuota(
            self::aplicarFiltroCuotasVistaNormal(
                CuotaGenerada::query()
                    ->with([
                        'legajo:id,apellido,nombre,dni',
                        'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                        'curso.curplan:id,curPlanCurso',
                        'curso.turnoClase:id,nombre',
                        'curso.nivel:id,nivel',
                        'cuota:id,nombre',
                        'terlec:id,ano',
                    ])
                    ->whereIn("{$tabla}.id", $ids)
                    ->where("{$tabla}.idLegajos", $idLegajo)
                    ->where("{$tabla}.faltapa", '>', 0),
                $idTerlec,
            ),
        )->get();
    }

    /**
     * @return array{
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string,
     *     becaResumen: string,
     *     terlecAno: string
     * }|null
     */
    public static function encabezadoEstudiante(int $idLegajo): ?array
    {
        $legajo = self::legajoParaGestion($idLegajo);
        if ($legajo === null) {
            return null;
        }

        $matricula = self::matriculaCicloActivo($idLegajo);
        $curso = trim((string) ($matricula?->curso?->nombreParaListado() ?? ''));
        if ($curso === '') {
            $primera = self::cuotasDelEstudiante($idLegajo)->first();
            $curso = trim((string) ($primera?->curso?->nombreParaListado() ?? ''));
        }

        $becaResumen = self::etiquetaBecaPorId((int) ($matricula?->idCuotasbecas ?? 0));

        $terlec = Terlec::query()->find((int) schoolCtx()->idTerlec, ['id', 'ano']);

        return [
            'apellido' => mb_strtoupper(trim((string) ($legajo->apellido ?? ''))),
            'nombre' => mb_strtoupper(trim((string) ($legajo->nombre ?? ''))),
            'dni' => CuotasFormato::formatearDni($legajo->dni ?? ''),
            'curso' => mb_strtoupper($curso),
            'becaResumen' => $becaResumen,
            'terlecAno' => (string) ($terlec->ano ?? schoolCtx()->terlecAno()),
        ];
    }

    /**
     * Matrícula del estudiante en el ciclo lectivo activo (beca y curso del encabezado).
     */
    public static function matriculaCicloActivo(int $idLegajo): ?Matricula
    {
        $idTerlec = (int) schoolCtx()->idTerlec;

        $query = Matricula::query()
            ->where('idLegajos', $idLegajo)
            ->where('idTerlec', $idTerlec);

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNivel');

        return $query
            ->with(self::relacionesMatricula())
            ->first();
    }

    /**
     * Última matrícula registrada del estudiante (cualquier ciclo lectivo).
     */
    public static function ultimaMatricula(int $idLegajo): ?Matricula
    {
        $query = Matricula::query()->where('idLegajos', $idLegajo);

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNivel');

        return $query
            ->with(self::relacionesMatricula())
            ->orderByDesc('idTerlec')
            ->orderByDesc('id')
            ->first();
    }

    /** Beca de la fila en cuotasgeneradas (idCuotasbecas del registro). */
    public static function etiquetaBeca(CuotaGenerada $registro): string
    {
        return self::etiquetaBecaPorId((int) ($registro->idCuotasbecas ?? 0));
    }

    /**
     * Texto de beca para listados y formularios.
     * id = 1 → «C/E» (cuota entera, sin ayuda familiar).
     */
    public static function etiquetaBecaPorId(int $idCuotasbecas): string
    {
        if ($idCuotasbecas < 1) {
            return '';
        }

        $beca = CuotasBeca::query()->find($idCuotasbecas);
        if ($beca === null) {
            return $idCuotasbecas === 1 ? 'C/E' : '';
        }

        $nombre = trim((string) ($beca->nombreBeca ?? ''));

        return $nombre !== '' ? $nombre : ($idCuotasbecas === 1 ? 'C/E' : '');
    }

    public static function filaPagada(CuotaGenerada $registro): bool
    {
        return (float) ($registro->faltapa ?? 0) <= 0;
    }

    /**
     * Totales de saldo neto (faltapa) y a pagar con interés/bonificación al día de hoy.
     * Incluye desglose por cuota para la grilla de autogestión.
     *
     * @param  iterable<CuotaGenerada>  $registros
     * @return array{
     *     neto: float,
     *     interes: float,
     *     conIntereses: float,
     *     porCuota: array<int, array{interes: float, aPagar: float}>
     * }
     */
    public static function totalizarSaldosAdeudados(iterable $registros): array
    {
        $adeudadas = collect($registros)->filter(
            fn (CuotaGenerada $registro) => (float) ($registro->faltapa ?? 0) > 0
                && (float) ($registro->importe ?? 0) > 0,
        );

        if ($adeudadas->isEmpty()) {
            return [
                'neto' => 0.0,
                'interes' => 0.0,
                'conIntereses' => 0.0,
                'porCuota' => [],
            ];
        }

        ImputacionPagoCalculo::precargarFormulas($adeudadas);

        $neto = 0.0;
        $interesTotal = 0.0;
        $conIntereses = 0.0;
        $porCuota = [];
        $hoy = Carbon::today();

        foreach ($adeudadas as $registro) {
            $saldo = (float) ($registro->faltapa ?? 0);
            $neto += $saldo;

            $calc = ImputacionPagoCalculo::calcular(
                $registro,
                $saldo,
                $hoy,
                null,
            );
            $interesFila = round((float) $calc['interes'], 2);
            $aPagarFila = round((float) $calc['aPagar'], 2);
            $interesTotal += $interesFila;
            $conIntereses += $aPagarFila;
            $porCuota[(int) $registro->id] = [
                'interes' => $interesFila,
                'aPagar' => $aPagarFila,
            ];
        }

        ImputacionPagoCalculo::limpiarCacheFormulas();

        return [
            'neto' => round($neto, 2),
            'interes' => round($interesTotal, 2),
            'conIntereses' => round($conIntereses, 2),
            'porCuota' => $porCuota,
        ];
    }

    /**
     * Total a pagar hoy por el estudiante (saldo + interés/bonificación), todos los ciclos y conceptos.
     */
    public static function totalAdeudadoEstudiante(int $idLegajo): float
    {
        $registros = CuotaGenerada::query()
            ->where('idLegajos', $idLegajo)
            ->where('faltapa', '>', 0)
            ->where('importe', '>', 0)
            ->get();

        return self::totalizarSaldosAdeudados($registros)['conIntereses'];
    }

    public static function filaAvisoPago(CuotaGenerada $registro): bool
    {
        return (int) ($registro->avisoPago ?? 0) === 1;
    }

    /**
     * IDs de cuotastipopago habilitados en imputación manual de pago.
     *
     * @var list<int>
     */
    public const IDS_MEDIOS_PAGO_IMPUTACION = [1, 2, 8, 9, 10];

    /**
     * @return list<int>
     */
    public static function idsMediosPagoImputacion(): array
    {
        return self::IDS_MEDIOS_PAGO_IMPUTACION;
    }

    /**
     * Medios de pago para el formulario de imputación (solo IDs habilitados).
     *
     * @return Collection<int, CuotaTipoPago>
     */
    public static function mediosDePagoImputacion(): Collection
    {
        $orden = self::IDS_MEDIOS_PAGO_IMPUTACION;

        return CuotaTipoPago::query()
            ->whereIn('id', $orden)
            ->get(['id', 'tipoPago', 'abrev'])
            ->sortBy(fn (CuotaTipoPago $medio) => array_search((int) $medio->id, $orden, true))
            ->values();
    }

    /**
     * @return Collection<int, CuotaTipoPago>
     */
    public static function mediosDePago(): Collection
    {
        return CuotaTipoPago::query()
            ->where('tipoPago', '!=', '')
            ->orderBy('id')
            ->get(['id', 'tipoPago', 'abrev']);
    }

    /**
     * @return Collection<int, CuotasBeca>
     */
    public static function becasParaSelector(): Collection
    {
        return CuotasBeca::query()
            ->orderBy('porcentaje')
            ->orderBy('nombreBeca')
            ->get(['id', 'nombreBeca', 'porcentaje']);
    }

    /**
     * Búsqueda de legajos con al menos una matrícula histórica (todos los niveles pedagógicos en Administración).
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function buscarLegajos(string $termino, int $porPagina = 20)
    {
        $query = Legajo::query()
            ->whereHas('matriculas', function (Builder $q) {
                SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($q, 'idNivel');
            });

        $termino = trim($termino);
        if ($termino !== '') {
            $query->buscar($termino);
        }

        return $query
            ->with([
                'matriculas' => function ($q) {
                    $q->with(self::relacionesMatricula())
                        ->orderByDesc('idTerlec')
                        ->orderByDesc('id');
                    SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($q, 'idNivel');
                },
            ])
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate($porPagina, ['id', 'apellido', 'nombre', 'dni', 'legajo']);
    }

    /**
     * Búsqueda de legajos que tienen al menos una cuota generada de las plantillas indicadas
     * en el ciclo lectivo activo (agregar alumnos individuales en facturación AFIP).
     *
     * @param  list<int>  $idCuotasPlantilla
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, Legajo>
     */
    public static function buscarLegajosConCuotasPlantilla(string $termino, array $idCuotasPlantilla, int $porPagina = 20)
    {
        $idCuotasPlantilla = array_values(array_unique(array_filter(
            array_map('intval', $idCuotasPlantilla),
            fn (int $id) => $id > 0,
        )));

        if ($idCuotasPlantilla === []) {
            return collect();
        }

        $idTerlec = (int) schoolCtx()->idTerlec;

        $query = Legajo::query()
            ->whereHas('matriculas', function (Builder $q) {
                SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($q, 'idNivel');
            })
            ->whereHas('cuotasGeneradas', function (Builder $q) use ($idTerlec, $idCuotasPlantilla) {
                $q->where('idTerlec', $idTerlec)
                    ->whereIn('idCuotas', $idCuotasPlantilla);
            });

        $termino = trim($termino);
        if ($termino !== '') {
            $query->buscar($termino);
        }

        return $query
            ->with([
                'matriculas' => function ($q) {
                    $q->with(self::relacionesMatricula())
                        ->orderByDesc('idTerlec')
                        ->orderByDesc('id');
                    SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($q, 'idNivel');
                },
            ])
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate($porPagina, ['id', 'apellido', 'nombre', 'dni', 'legajo']);
    }

    /**
     * Curso, nivel y beca para el listado de búsqueda (ciclo activo o última matrícula).
     *
     * @return array{curso: string, nivel: string, beca: string}
     */
    public static function datosMatriculaParaListado(Legajo $legajo): array
    {
        $mat = self::matriculaReferenciaListado($legajo);

        return self::datosDesdeMatricula($mat);
    }

    /**
     * Datos de fila en la búsqueda de estudiantes (incluye matrícula actual y año de la última).
     *
     * @return array{
     *     curso: string,
     *     nivel: string,
     *     beca: string,
     *     tieneMatriculaActual: bool,
     *     anoUltimaMatricula: string,
     *     nivelEtiqueta: string,
     *     claseChipNivel: string,
     *     condicion: string
     * }
     */
    public static function datosListadoBusqueda(Legajo $legajo): array
    {
        $idTerlec = (int) schoolCtx()->idTerlec;
        $ultima = self::ultimaMatriculaDesdeLegajo($legajo);
        $matReferencia = self::matriculaReferenciaListado($legajo);
        $matActual = self::matriculaCicloActivoDesdeLegajo($legajo, $idTerlec);
        $nivelNombre = trim((string) ($matReferencia?->nivel?->nivel ?? ''));

        return array_merge(self::datosMatriculaParaListado($legajo), [
            'tieneMatriculaActual' => $matActual !== null,
            'anoUltimaMatricula' => self::anoTerlecMatricula($ultima),
            'nivelEtiqueta' => $nivelNombre !== '' ? $nivelNombre : '—',
            'claseChipNivel' => MatriculaNivelEstilo::claseChipPorNombreNivel($nivelNombre),
            'condicion' => self::etiquetaCondicionMatricula($matActual),
        ]);
    }

    public static function tieneMatriculaCicloActivo(Legajo $legajo, ?int $idTerlec = null): bool
    {
        return self::matriculaCicloActivoDesdeLegajo($legajo, $idTerlec) !== null;
    }

    /**
     * Matrícula del ciclo lectivo indicado (sesión por defecto), reutilizando eager load si existe.
     */
    private static function matriculaCicloActivoDesdeLegajo(Legajo $legajo, ?int $idTerlec = null): ?Matricula
    {
        $idTerlec ??= (int) schoolCtx()->idTerlec;

        if ($legajo->relationLoaded('matriculas')) {
            $mat = $legajo->matriculas->first(
                fn (Matricula $m) => (int) $m->idTerlec === $idTerlec,
            );

            return $mat instanceof Matricula ? $mat : null;
        }

        return self::matriculaCicloActivo((int) $legajo->id);
    }

    /** Texto de condición pedagógica (`condiciones.condicion`) de una matrícula. */
    private static function etiquetaCondicionMatricula(?Matricula $mat): string
    {
        if ($mat === null) {
            return '—';
        }

        $texto = trim((string) ($mat->condicion?->condicion ?? ''));

        return $texto !== '' ? $texto : '—';
    }

    /**
     * Curso, nivel y beca de la matrícula del ciclo lectivo activo en sesión.
     *
     * @return array{curso: string, nivel: string, beca: string}
     */
    public static function datosMatriculaCicloActivo(Legajo $legajo): array
    {
        return self::datosDesdeMatricula(self::matriculaCicloActivo((int) $legajo->id));
    }

    /**
     * @return array{curso: string, nivel: string, beca: string}
     */
    private static function datosDesdeMatricula(?Matricula $mat): array
    {
        if ($mat === null) {
            return ['curso' => '—', 'nivel' => '—', 'beca' => '—'];
        }

        $curso = trim((string) ($mat->curso?->nombreParaListado() ?? $mat->curso?->cursec ?? ''));
        $nivel = trim((string) ($mat->nivel?->nivel ?? ''));
        $beca = self::etiquetaBecaPorId((int) ($mat->idCuotasbecas ?? 0));

        return [
            'curso' => $curso !== '' ? mb_strtoupper($curso) : '—',
            'nivel' => $nivel !== '' ? mb_strtoupper($nivel) : '—',
            'beca' => $beca !== '' ? $beca : '—',
        ];
    }

    public static function matriculaReferenciaListado(Legajo $legajo): ?Matricula
    {
        $idTerlec = (int) schoolCtx()->idTerlec;

        if ($legajo->relationLoaded('matriculas') && $legajo->matriculas->isNotEmpty()) {
            return $legajo->matriculas->firstWhere('idTerlec', $idTerlec)
                ?? $legajo->matriculas->first();
        }

        return self::matriculaCicloActivo((int) $legajo->id) ?? self::ultimaMatricula((int) $legajo->id);
    }

    private static function ultimaMatriculaDesdeLegajo(Legajo $legajo): ?Matricula
    {
        if ($legajo->relationLoaded('matriculas') && $legajo->matriculas->isNotEmpty()) {
            return $legajo->matriculas->first();
        }

        return self::ultimaMatricula((int) $legajo->id);
    }

    private static function anoTerlecMatricula(?Matricula $mat): string
    {
        if ($mat === null) {
            return '—';
        }

        if ($mat->relationLoaded('terlec') && $mat->terlec !== null) {
            $ano = trim((string) ($mat->terlec->ano ?? ''));

            return $ano !== '' ? $ano : '—';
        }

        $terlec = Terlec::query()->find((int) $mat->idTerlec, ['id', 'ano']);
        $ano = trim((string) ($terlec->ano ?? ''));

        return $ano !== '' ? $ano : '—';
    }

    /**
     * @return array<int, string>
     */
    private static function relacionesMatricula(): array
    {
        return [
            'terlec:id,ano',
            'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
            'curso.curplan:id,curPlanCurso',
            'curso.turnoClase:id,nombre',
            'nivel:id,nivel',
            'condicion:id,condicion',
        ];
    }
}
