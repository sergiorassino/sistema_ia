<?php

namespace App\Support;

use App\Models\Inasistencia;
use App\Models\InasistenciaValor;
use App\Models\Matricula;
use App\Support\Alumnos\SinMatriculaAutogestionException;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Consulta de inasistencias del año lectivo activo.
 *
 * - Secretaría: {@see schoolCtx()}::terlecAno.
 * - Autogestión: {@see studentCtx()}::terlecAno y matrícula del alumno en sesión.
 */
final class InformeInasistencias
{
    public static function anoLectivo(): int
    {
        return (int) (schoolCtx()->terlecAno() ?? now()->year);
    }

    public static function anoLectivoAutogestion(): int
    {
        return (int) (studentCtx()->terlecAno() ?? now()->year);
    }

    public static function matriculaAutogestion(): ?Matricula
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        return Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idLegajos', (int) $ctx->idLegajo)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * ¿Hay matrícula con curso usable en el ciclo de autogestión?
     */
    public static function tieneMatriculaCursoAutogestion(): bool
    {
        $matricula = self::matriculaAutogestion();
        if ($matricula === null || (int) ($matricula->idCursos ?? 0) <= 0 || $matricula->curso === null) {
            return false;
        }

        $matricula->curso->loadMissing(['curplan', 'turnoClase']);

        return trim((string) $matricula->curso->nombreParaListado()) !== '';
    }

    /**
     * Nombre de curso del alumno en el ciclo de autogestión (solo vía matrícula).
     * Sin matrícula o curso usable: excepción — no hay fallback a otras tablas.
     *
     * @throws SinMatriculaAutogestionException
     */
    public static function cursoNombreAutogestion(): string
    {
        $matricula = self::matriculaAutogestion();
        if ($matricula === null || (int) ($matricula->idCursos ?? 0) <= 0 || $matricula->curso === null) {
            throw new SinMatriculaAutogestionException;
        }

        $matricula->curso->loadMissing(['curplan', 'turnoClase']);
        $curso = trim((string) $matricula->curso->nombreParaListado());
        if ($curso === '') {
            throw new SinMatriculaAutogestionException;
        }

        return $curso;
    }

    /**
     * @return array{desde: Carbon, hasta: Carbon}
     */
    public static function rangoFechasAno(): array
    {
        return self::rangoFechasParaAno(self::anoLectivo());
    }

    /**
     * @return array{desde: Carbon, hasta: Carbon}
     */
    public static function rangoFechasParaAno(int $ano): array
    {
        $desde = Carbon::create($ano, 1, 1)->startOfDay();
        $finAno = Carbon::create($ano, 12, 31)->endOfDay();
        $hasta = now()->year === $ano ? now()->copy()->endOfDay() : $finAno;

        if ($hasta->gt($finAno)) {
            $hasta = $finAno;
        }

        return compact('desde', 'hasta');
    }

    /**
     * Rango efectivo: año lectivo acotado por filtros opcionales (formato Y-m-d).
     *
     * @return array{desde: Carbon, hasta: Carbon}
     */
    public static function rangoFechasConFiltro(
        ?string $fechaDesde,
        ?string $fechaHasta,
        int $anoLectivo,
    ): array {
        $rangoAno = self::rangoFechasParaAno($anoLectivo);
        $desde = $rangoAno['desde']->copy();
        $hasta = $rangoAno['hasta']->copy();

        $parsedDesde = self::parseFechaFiltro($fechaDesde, $anoLectivo);
        if ($parsedDesde !== null) {
            $desde = $parsedDesde;
        }

        $parsedHasta = self::parseFechaFiltro($fechaHasta, $anoLectivo);
        if ($parsedHasta !== null) {
            $hasta = $parsedHasta->copy()->endOfDay();
        }

        if ($desde->gt($hasta)) {
            $hasta = $desde->copy()->endOfDay();
        }

        if ($desde->lt($rangoAno['desde'])) {
            $desde = $rangoAno['desde']->copy();
        }
        if ($hasta->gt($rangoAno['hasta'])) {
            $hasta = $rangoAno['hasta']->copy();
        }

        return compact('desde', 'hasta');
    }

    public static function parseFechaFiltro(?string $value, int $anoLectivo): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            $fecha = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $min = Carbon::create($anoLectivo, 1, 1)->startOfDay();
        $max = Carbon::create($anoLectivo, 12, 31)->endOfDay();

        if ($fecha->lt($min) || $fecha->gt($max)) {
            return null;
        }

        return $fecha;
    }

    /** Valor Y-m-d para inputs type="date" (límites del año lectivo). */
    public static function fechaMinimaAno(int $anoLectivo): string
    {
        return Carbon::create($anoLectivo, 1, 1)->toDateString();
    }

    public static function fechaMaximaAno(int $anoLectivo): string
    {
        return self::rangoFechasParaAno($anoLectivo)['hasta']->toDateString();
    }

    public static function filtroFechasActivo(?string $fechaDesde, ?string $fechaHasta): bool
    {
        return trim((string) $fechaDesde) !== '' || trim((string) $fechaHasta) !== '';
    }

    /**
     * Variables para `pdf.informe-inasistencias`.
     *
     * @return array{
     *     ano: int,
     *     alumnoLinea: string,
     *     dni: string,
     *     cursoLabel: string,
     *     fechaDesde: string,
     *     fechaHasta: string,
     *     filtroFechasActivo: bool,
     *     etiquetaTipoFiltro: string,
     *     inasistencias: Collection<int, Inasistencia>,
     *     resumen: InasistenciasResumen,
     *     totalesCatalogo: list<array{id: int, concepto: string, total: float}>
     * }
     */
    public static function datosPdf(
        Matricula $matricula,
        ?int $idTipo,
        int $anoLectivo,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
    ): array {
        $idTipo = self::tipoFiltroValido($idTipo);
        $rango = self::rangoFechasConFiltro($fechaDesde, $fechaHasta, $anoLectivo);
        $inasistencias = self::inasistenciasDelAno(
            (int) $matricula->id,
            $idTipo,
            $anoLectivo,
            $fechaDesde,
            $fechaHasta,
        );
        $legajo = $matricula->legajo;
        $alumnoLinea = mb_strtoupper(trim(
            (string) ($legajo?->apellido ?? '').' '.(string) ($legajo?->nombre ?? '')
        ));

        return [
            'ano' => $anoLectivo,
            'alumnoLinea' => $alumnoLinea,
            'dni' => trim((string) ($legajo?->dni ?? '')),
            'cursoLabel' => mb_strtoupper(trim((string) ($matricula->curso?->nombreParaListado() ?? ''))),
            'fechaDesde' => $rango['desde']->format('d/m/Y'),
            'fechaHasta' => $rango['hasta']->format('d/m/Y'),
            'filtroFechasActivo' => self::filtroFechasActivo($fechaDesde, $fechaHasta),
            'etiquetaTipoFiltro' => self::etiquetaFiltroTipos($idTipo),
            'inasistencias' => $inasistencias,
            'resumen' => InasistenciasResumen::desdeColeccion($inasistencias),
            'totalesCatalogo' => InasistenciasResumen::totalesCatalogo($inasistencias),
        ];
    }

    /** @return Collection<int, InasistenciaValor> */
    public static function tiposDisponibles(): Collection
    {
        return InasistenciaValor::query()
            ->orderBy('concepto')
            ->get(['id', 'concepto']);
    }

    public static function tipoFiltroValido(?int $idTipo): ?int
    {
        if ($idTipo === null || $idTipo <= 0) {
            return null;
        }

        $existe = InasistenciaValor::query()->whereKey($idTipo)->exists();

        return $existe ? $idTipo : null;
    }

    public static function etiquetaFiltroTipos(?int $idTipo): string
    {
        if ($idTipo === null || $idTipo <= 0) {
            return '(Todos los tipos)';
        }

        $concepto = trim((string) (InasistenciaValor::query()->whereKey($idTipo)->value('concepto') ?? ''));

        return $concepto !== '' ? "({$concepto})" : '(Tipo seleccionado)';
    }

    /** @return Collection<int, Inasistencia> */
    public static function inasistenciasDelAno(
        int $idMatricula,
        ?int $idTipo = null,
        ?int $anoLectivo = null,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
    ): Collection {
        $ano = $anoLectivo ?? self::anoLectivo();
        $rango = self::rangoFechasConFiltro($fechaDesde, $fechaHasta, $ano);
        $idTipo = self::tipoFiltroValido($idTipo);

        $query = Inasistencia::query()
            ->with('valorTipo')
            ->where('idMatricula', $idMatricula)
            ->whereBetween('fecha', [
                $rango['desde']->toDateString(),
                $rango['hasta']->toDateString(),
            ]);

        if ($idTipo !== null) {
            $query->where('tipo', (string) $idTipo);
        }

        return $query
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }
}