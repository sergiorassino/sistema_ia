<?php

namespace App\Support\Cuotas;

use App\Models\Cuota;
use App\Models\CuotaPago;
use App\Models\CuotaTipoPago;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Consulta y armado de datos para el PDF «Listado de pagos por fecha».
 */
final class ListadoPagosPorFechaDatos
{
    /**
     * @return Collection<int, CuotaTipoPago>
     */
    public static function mediosDePagoParaSelector(): Collection
    {
        return GestionAranceles::mediosDePago();
    }

    /**
     * Cuotas del ciclo lectivo activo, ordenadas por nro. de orden.
     *
     * @return Collection<int, Cuota>
     */
    public static function cuotasDelCicloParaSelector(): Collection
    {
        $idTerlec = CuotasPlantillaCatalog::idTerlecActivo();
        if ($idTerlec < 1) {
            return collect();
        }

        return Cuota::query()
            ->where('idTerlec', $idTerlec)
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'nombre', 'orden']);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     fechaDesde: string,
     *     fechaHasta: string,
     *     idMedioPago: int,
     *     idCuota: int,
     *     titMedioPago: string,
     *     titFiltroCuota: string,
     *     fechaDesdeEtiqueta: string,
     *     fechaHastaEtiqueta: string
     * }
     */
    public static function normalizarFiltros(array $input): array
    {
        $fechaDesde = self::parseFechaInput($input['fecha_desde'] ?? $input['fechaDesde'] ?? null);
        $fechaHasta = self::parseFechaInput($input['fecha_hasta'] ?? $input['fechaHasta'] ?? null);

        if ($fechaDesde === null || $fechaHasta === null) {
            throw ValidationException::withMessages([
                'fecha_desde' => 'Indique fecha de pago desde y hasta.',
            ]);
        }

        if ($fechaHasta->lt($fechaDesde)) {
            throw ValidationException::withMessages([
                'fecha_hasta' => 'La fecha hasta no puede ser anterior a la fecha desde.',
            ]);
        }

        $idMedioPago = (int) ($input['medio'] ?? $input['idMedioPago'] ?? 0);
        $idCuota = (int) ($input['cuota'] ?? $input['idCuota'] ?? 0);

        $mediosPermitidos = self::mediosDePagoParaSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($idMedioPago !== 0 && ! in_array($idMedioPago, $mediosPermitidos, true)) {
            throw ValidationException::withMessages([
                'medio' => 'Medio de pago no válido.',
            ]);
        }

        $cuotasPermitidas = self::cuotasDelCicloParaSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($idCuota !== 0 && ! in_array($idCuota, $cuotasPermitidas, true)) {
            throw ValidationException::withMessages([
                'cuota' => 'Cuota no válida para el ciclo lectivo activo.',
            ]);
        }

        return [
            'fechaDesde' => $fechaDesde->toDateString(),
            'fechaHasta' => $fechaHasta->toDateString(),
            'idMedioPago' => $idMedioPago,
            'idCuota' => $idCuota,
            'titMedioPago' => self::tituloMedioPago($idMedioPago),
            'titFiltroCuota' => self::tituloCuota($idCuota),
            'fechaDesdeEtiqueta' => $fechaDesde->format('d/m/Y'),
            'fechaHastaEtiqueta' => $fechaHasta->format('d/m/Y'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros  Salida de normalizarFiltros()
     * @return array<string, mixed>|null
     */
    public static function build(array $filtros): ?array
    {
        $idTerlec = CuotasPlantillaCatalog::idTerlecActivo();
        if ($idTerlec < 1) {
            return null;
        }

        $filas = self::consultaPagos($filtros, $idTerlec)
            ->get()
            ->map(fn (CuotaPago $pago): array => self::filaDesdePago($pago))
            ->values()
            ->all();

        $totImporte = 0.0;
        $totBonif = 0.0;
        $totInte = 0.0;

        foreach ($filas as $fila) {
            $totImporte += (float) ($fila['_importe'] ?? 0);
            $totBonif += (float) ($fila['_bonificacion'] ?? 0);
            $totInte += (float) ($fila['_interes'] ?? 0);
        }

        return [
            'pdfHeader' => schoolPdfHeaderData(),
            'ano' => (int) schoolCtx()->terlecAno(),
            'fechaDesdeEtiqueta' => (string) ($filtros['fechaDesdeEtiqueta'] ?? ''),
            'fechaHastaEtiqueta' => (string) ($filtros['fechaHastaEtiqueta'] ?? ''),
            'titMedioPago' => (string) ($filtros['titMedioPago'] ?? 'TODOS'),
            'titFiltroCuota' => (string) ($filtros['titFiltroCuota'] ?? 'TODAS'),
            'filas' => $filas,
            'totales' => [
                'importe' => CuotasFormato::formatearImporte($totImporte),
                'bonificacion' => CuotasFormato::formatearImporte($totBonif),
                'interes' => CuotasFormato::formatearImporte($totInte),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function consultaPagos(array $filtros, int $idTerlec): Builder
    {
        $query = CuotaPago::query()
            ->select([
                'cuotaspagos.id',
                'cuotaspagos.idCuotasGeneradas',
                'cuotaspagos.fechhora',
                'cuotaspagos.importe',
                'cuotaspagos.bonificacion',
                'cuotaspagos.interes',
                'cuotaspagos.idCuotastipopago',
            ])
            ->join('cuotasgeneradas', 'cuotaspagos.idCuotasGeneradas', '=', 'cuotasgeneradas.id')
            ->join('cuotas', 'cuotasgeneradas.idCuotas', '=', 'cuotas.id')
            ->join('legajos', 'cuotasgeneradas.idLegajos', '=', 'legajos.id')
            ->join('cursos', 'cuotasgeneradas.idCursos', '=', 'cursos.Id')
            ->join('cuotastipopago', 'cuotaspagos.idCuotastipopago', '=', 'cuotastipopago.id')
            ->where('cuotasgeneradas.idTerlec', $idTerlec)
            ->whereDate('cuotaspagos.fechhora', '>=', (string) ($filtros['fechaDesde'] ?? ''))
            ->whereDate('cuotaspagos.fechhora', '<=', (string) ($filtros['fechaHasta'] ?? ''))
            ->with([
                'tipoPago:id,tipoPago,abrev',
                'cuotaGenerada.cuota:id,nombre',
                'cuotaGenerada.legajo:id,apellido,nombre',
                'cuotaGenerada.curso:Id,cursec',
            ]);

        $idMedioPago = (int) ($filtros['idMedioPago'] ?? 0);
        if ($idMedioPago > 0) {
            $query->where('cuotaspagos.idCuotastipopago', $idMedioPago);
        }

        $idCuota = (int) ($filtros['idCuota'] ?? 0);
        if ($idCuota > 0) {
            $query->where('cuotas.id', $idCuota);
        }

        return $query
            ->orderBy('cuotaspagos.fechhora')
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->orderBy('cuotaspagos.id');
    }

    /**
     * @return array{
     *     estudiante: string,
     *     curso: string,
     *     fechaPago: string,
     *     medioPago: string,
     *     cuota: string,
     *     importe: string,
     *     bonificacion: string,
     *     interes: string,
     *     _importe: float,
     *     _bonificacion: float,
     *     _interes: float
     * }
     */
    private static function filaDesdePago(CuotaPago $pago): array
    {
        $importe = round((float) ($pago->importe ?? 0), 2);
        $bonificacion = round((float) ($pago->bonificacion ?? 0), 2);
        $interes = round((float) ($pago->interes ?? 0), 2);

        $apellido = trim((string) ($pago->cuotaGenerada?->legajo?->apellido ?? ''));
        $nombre = trim((string) ($pago->cuotaGenerada?->legajo?->nombre ?? ''));
        $estudiante = trim($apellido.' '.$nombre);

        $medioPago = trim((string) ($pago->tipoPago?->tipoPago ?? ''));
        if ($medioPago === '') {
            $medioPago = trim((string) ($pago->tipoPago?->abrev ?? ''));
        }

        return [
            'estudiante' => $estudiante,
            'curso' => trim((string) ($pago->cuotaGenerada?->curso?->cursec ?? '')),
            'fechaPago' => CuotasFormato::formatearFechaHora($pago->fechhora),
            'medioPago' => $medioPago,
            'cuota' => trim((string) ($pago->cuotaGenerada?->cuota?->nombre ?? '')),
            'importe' => CuotasFormato::formatearImporte($importe),
            'bonificacion' => CuotasFormato::formatearImporte($bonificacion),
            'interes' => CuotasFormato::formatearImporte($interes),
            '_importe' => $importe,
            '_bonificacion' => $bonificacion,
            '_interes' => $interes,
        ];
    }

    private static function tituloMedioPago(int $idMedioPago): string
    {
        if ($idMedioPago === 0) {
            return 'TODOS';
        }

        $medio = CuotaTipoPago::query()->find($idMedioPago, ['id', 'tipoPago']);

        return trim((string) ($medio?->tipoPago ?? '')) !== ''
            ? trim((string) $medio->tipoPago)
            : 'TODOS';
    }

    private static function tituloCuota(int $idCuota): string
    {
        if ($idCuota === 0) {
            return 'TODAS';
        }

        $cuota = Cuota::query()
            ->whereKey($idCuota)
            ->where('idTerlec', CuotasPlantillaCatalog::idTerlecActivo())
            ->first(['id', 'nombre']);

        return trim((string) ($cuota?->nombre ?? '')) !== ''
            ? trim((string) $cuota->nombre)
            : 'TODAS';
    }

    private static function parseFechaInput(mixed $valor): ?Carbon
    {
        $raw = trim((string) ($valor ?? ''));
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
