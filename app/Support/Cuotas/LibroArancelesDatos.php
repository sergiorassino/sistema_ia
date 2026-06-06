<?php

namespace App\Support\Cuotas;

use App\Models\CuotasBeca;
use App\Models\Curso;
use App\Models\Ento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Datos para el PDF «Libro de aranceles» (legacy FPDF apaisado).
 */
final class LibroArancelesDatos
{
    /** @var list<int> */
    private const MESES_CUOTA = [3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    /**
     * @param  list<int>  $cursoIds
     * @return array{
     *     ano: int,
     *     paginaInicial: int,
     *     secciones: list<array<string, mixed>>
     * }|null
     */
    public static function build(array $cursoIds, int $paginaInicial): ?array
    {
        $idTerlec = (int) schoolCtx()->idTerlec;
        if ($idTerlec < 1) {
            return null;
        }

        $cursosPermitidos = GeneracionMasivaCuotasConsulta::cursosEnContexto();
        if ($cursosPermitidos->isEmpty()) {
            return null;
        }

        $permitidosFlip = $cursoIds === []
            ? []
            : array_flip(array_values(array_filter(
                array_map('intval', $cursoIds),
                fn (int $id) => $id > 0,
            )));

        $ordenados = [];
        foreach ($cursosPermitidos as $curso) {
            $id = (int) $curso->Id;
            if (isset($permitidosFlip[$id])) {
                $ordenados[] = $curso;
            }
        }

        if ($ordenados === []) {
            return null;
        }

        $secciones = [];
        foreach ($ordenados as $curso) {
            $seccion = self::seccionCurso($curso, $idTerlec);
            if ($seccion !== null) {
                $secciones[] = $seccion;
            }
        }

        if ($secciones === []) {
            return null;
        }

        return [
            'ano' => (int) schoolCtx()->terlecAno(),
            'paginaInicial' => max(1, $paginaInicial),
            'secciones' => $secciones,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function seccionCurso(Curso $curso, int $idTerlec): ?array
    {
        $idCurso = (int) $curso->Id;
        $idNivel = (int) ($curso->idNivel ?? 0);

        $matriculas = DB::table('matricula as m')
            ->join('legajos as l', 'l.id', '=', 'm.idLegajos')
            ->where('m.idCursos', $idCurso)
            ->where('m.idTerlec', $idTerlec)
            ->where('m.idCondiciones', '<', 5)
            ->whereNull('m.fechaBaja')
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->orderBy('m.id')
            ->get(['m.id', 'm.idCuotasbecas', 'l.apellido', 'l.nombre']);

        $matriculaIds = $matriculas
            ->map(fn ($m) => (int) $m->id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $cuotasPorMatricula = self::cuotasPorMatricula($matriculaIds, $idTerlec);

        $becaIds = $matriculas
            ->map(fn ($m) => (int) ($m->idCuotasbecas ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $becasPorId = $becaIds === []
            ? collect()
            : CuotasBeca::query()
                ->whereIn('id', $becaIds)
                ->pluck('porcentaje', 'id');

        $alumnos = [];
        foreach ($matriculas as $matricula) {
            $idMatricula = (int) $matricula->id;
            $cuotas = $cuotasPorMatricula->get($idMatricula, collect());

            $idBeca = (int) ($matricula->idCuotasbecas ?? 0);
            $porcBeca = (int) ($becasPorId[$idBeca] ?? 0);

            $alumnos[] = [
                'nombre' => mb_strtoupper(trim((string) $matricula->apellido.' '.(string) $matricula->nombre)),
                'porcBeca' => $porcBeca,
                'matricula' => self::celdaMatricula($cuotas),
                'meses' => self::celdasMeses($cuotas),
            ];
        }

        $nivelNombre = trim((string) ($curso->nivel?->nivel ?? ''));
        $cursec = trim((string) ($curso->cursec ?? ''));

        return [
            'header' => self::headerParaNivel($idNivel),
            'cursoLinea' => trim($cursec.($nivelNombre !== '' ? ' '.$nivelNombre : '')),
            'alumnos' => $alumnos,
        ];
    }

    /**
     * @param  list<int>  $matriculaIds
     * @return Collection<int, Collection<int, object>>
     */
    private static function cuotasPorMatricula(array $matriculaIds, int $idTerlec): Collection
    {
        if ($matriculaIds === []) {
            return collect();
        }

        return DB::table('cuotasgeneradas')
            ->whereIn('idMatricula', $matriculaIds)
            ->where('idTerlec', $idTerlec)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('idCuotastipo', 1)
                        ->whereIn('idCuotasmeses', self::MESES_CUOTA);
                })->orWhereIn('idCuotastipo', [2, 3]);
            })
            ->get(['idMatricula', 'idCuotastipo', 'idCuotasmeses', 'pagado', 'nroComp'])
            ->groupBy(fn ($row) => (int) $row->idMatricula);
    }

    /**
     * @param  Collection<int, object>  $cuotas
     * @return array{pagado: float, nroComp: int}
     */
    private static function celdaMatricula(Collection $cuotas): array
    {
        $filas = $cuotas->filter(fn ($c) => in_array((int) $c->idCuotastipo, [2, 3], true));

        return [
            'pagado' => round((float) $filas->sum(fn ($c) => (float) ($c->pagado ?? 0)), 2),
            'nroComp' => (int) $filas->max(fn ($c) => (int) ($c->nroComp ?? 0)),
        ];
    }

    /**
     * @param  Collection<int, object>  $cuotas
     * @return array<int, array{pagado: float, nroComp: int}>
     */
    private static function celdasMeses(Collection $cuotas): array
    {
        $mensuales = $cuotas->filter(fn ($c) => (int) $c->idCuotastipo === 1);
        $porMes = [];

        foreach (self::MESES_CUOTA as $mes) {
            $fila = $mensuales->first(fn ($c) => (int) $c->idCuotasmeses === $mes);
            $porMes[$mes] = [
                'pagado' => round((float) ($fila->pagado ?? 0), 2),
                'nroComp' => (int) ($fila->nroComp ?? 0),
            ];
        }

        return $porMes;
    }

    /**
     * @return array{
     *     insti: string,
     *     direccion: string,
     *     localidad: string,
     *     departamento: string,
     *     provincia: string,
     *     cuit: string,
     *     ee: string,
     *     logo_file: ?string
     * }
     */
    public static function headerParaNivel(int $idNivel): array
    {
        if ($idNivel < 1) {
            return self::headerVacio();
        }

        $ento = Ento::query()
            ->where('idNivel', $idNivel)
            ->first(['insti', 'direccion', 'localidad', 'departamento', 'provincia', 'cuit', 'cue', 'ee', 'logo_path']);

        $logoFile = null;
        $logoPath = trim((string) ($ento?->logo_path ?? ''));
        if ($logoPath !== '') {
            $abs = Storage::disk('public')->path($logoPath);
            if (is_string($abs) && $abs !== '' && file_exists($abs)) {
                $logoFile = $abs;
            }
        }

        if ($logoFile === null) {
            $fallback = public_path('img/3.png');
            if (is_file($fallback)) {
                $logoFile = $fallback;
            }
        }

        return [
            'insti' => trim((string) ($ento?->insti ?? '')),
            'direccion' => trim((string) ($ento?->direccion ?? '')),
            'localidad' => trim((string) ($ento?->localidad ?? '')),
            'departamento' => trim((string) ($ento?->departamento ?? '')),
            'provincia' => trim((string) ($ento?->provincia ?? '')),
            'cuit' => trim((string) ($ento?->cuit ?? '')),
            'ee' => trim((string) ($ento?->ee ?? '')),
            'logo_file' => $logoFile,
        ];
    }

    /**
     * @return array{
     *     insti: string,
     *     direccion: string,
     *     localidad: string,
     *     departamento: string,
     *     provincia: string,
     *     cuit: string,
     *     ee: string,
     *     logo_file: ?string
     * }
     */
    private static function headerVacio(): array
    {
        return [
            'insti' => '',
            'direccion' => '',
            'localidad' => '',
            'departamento' => '',
            'provincia' => '',
            'cuit' => '',
            'ee' => '',
            'logo_file' => null,
        ];
    }
}
