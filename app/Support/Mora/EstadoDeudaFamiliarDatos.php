<?php

namespace App\Support\Mora;

use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Models\CuotaGenerada;
use App\Models\Familia;
use App\Support\Cuotas\CuotasFormato;
use App\Support\Cuotas\GestionAranceles;
use App\Support\Cuotas\ImputacionPagoCalculo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Datos del PDF «Estado de deuda» por familia (legacy FPDF estado de deuda).
 *
 * Interés diario en tramo 4: hasta la fecha de hoy (como imputarPago legacy),
 * no hasta nueVenc/venc3 del cupón.
 */
final class EstadoDeudaFamiliarDatos
{
    /**
     * @return array<string, mixed>|null
     */
    public static function paraFamilia(int $idFamilia): ?array
    {
        $familia = self::familiaValida($idFamilia);
        if ($familia === null) {
            return null;
        }

        $filas = self::filasDeuda($idFamilia);
        $totImporte = 0.0;
        $totInteres = 0.0;
        $totAPagar = 0.0;

        $filasPdf = [];
        $nro = 0;

        foreach ($filas as $registro) {
            $nro++;
            $calc = ImputacionPagoCalculo::calcular(
                $registro,
                (float) ($registro->faltapa ?? 0),
                Carbon::today(),
                null,
                true,
            );

            $importe = round((float) ($registro->importe ?? 0), 2);
            $interes = round((float) $calc['interes'], 2);
            $aPagar = round((float) $calc['aPagar'], 2);

            $totImporte += $importe;
            $totInteres += $interes;
            $totAPagar += $aPagar;

            $legajo = $registro->legajo;
            $curso = $registro->curso;
            $becaEtiqueta = GestionAranceles::etiquetaBeca($registro);
            if ($becaEtiqueta === '') {
                $becaEtiqueta = 'C/E';
            }

            $filasPdf[] = [
                'nro' => $nro,
                'estudiante' => mb_strtoupper(trim(
                    trim((string) ($legajo?->apellido ?? '')).' '.trim((string) ($legajo?->nombre ?? '')),
                )),
                'cuota' => mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? ''))),
                'curso' => mb_strtoupper(trim((string) ($curso?->cursec ?? $curso?->nombreParaListado() ?? ''))),
                'nivel' => mb_strtoupper(trim((string) ($curso?->nivel?->abrev ?? ''))),
                'ano' => (string) ($registro->terlec?->ano ?? ''),
                'beca' => mb_strtoupper($becaEtiqueta),
                'venc1' => CuotasFormato::formatearFecha($registro->venc1),
                'importe' => CuotasFormato::formatearImporte($importe),
                'interes' => CuotasFormato::formatearImporte($interes),
                'aPagar' => CuotasFormato::formatearImporte($aPagar),
            ];
        }

        $apellidoFamilia = trim((string) ($familia->apellido ?? ''));
        $responsable = trim((string) ($familia->responsable ?? ''));

        return [
            'pdfHeader' => schoolPdfHeaderData(),
            'fechaInforme' => Carbon::now()->format('d/m/Y'),
            'familiaEtiqueta' => mb_strtoupper($apellidoFamilia),
            'responsableEtiqueta' => mb_strtoupper($responsable),
            'familiaLinea' => mb_strtoupper(trim(
                $apellidoFamilia.($apellidoFamilia !== '' && $responsable !== '' ? ' - ' : '').$responsable,
            )),
            'filas' => $filasPdf,
            'totales' => [
                'importe' => CuotasFormato::formatearImporte($totImporte),
                'interes' => CuotasFormato::formatearImporte($totInteres),
                'aPagar' => CuotasFormato::formatearImporte($totAPagar),
            ],
        ];
    }

    public static function familiaValida(int $idFamilia): ?Familia
    {
        if ($idFamilia <= 0 || $idFamilia === LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR) {
            return null;
        }

        return Familia::query()->find($idFamilia, ['id', 'apellido', 'responsable']);
    }

    /**
     * Totales «a pagar» (saldo + interés/bonificación) agrupados por familia y por legajo.
     *
     * @param  array<int>  $idsFamilias
     * @return array{
     *     porFamilia: array<int, float>,
     *     porLegajo: array<int, float>,
     * }
     */
    public static function totalesAPagarPorFamilias(array $idsFamilias): array
    {
        $idsFamilias = array_values(array_unique(array_filter(
            array_map('intval', $idsFamilias),
            fn (int $id) => $id > 0,
        )));

        $porFamilia = array_fill_keys($idsFamilias, 0.0);
        $porLegajo = [];

        if ($idsFamilias === []) {
            return ['porFamilia' => [], 'porLegajo' => []];
        }

        $filas = self::consultaFilasDeuda($idsFamilias)->get();

        foreach ($filas as $registro) {
            $aPagar = self::aPagarDesdeRegistro($registro);
            $idLegajo = (int) $registro->idLegajos;
            $idFamilia = (int) ($registro->legajo?->idFamilias ?? 0);

            if ($idLegajo > 0) {
                $porLegajo[$idLegajo] = ($porLegajo[$idLegajo] ?? 0.0) + $aPagar;
            }

            if ($idFamilia > 0 && array_key_exists($idFamilia, $porFamilia)) {
                $porFamilia[$idFamilia] += $aPagar;
            }
        }

        return [
            'porFamilia' => $porFamilia,
            'porLegajo' => $porLegajo,
        ];
    }

    /**
     * @return Collection<int, CuotaGenerada>
     */
    private static function filasDeuda(int $idFamilia): Collection
    {
        return self::consultaFilasDeuda($idFamilia)
            ->with([
                'cuota:id,nombre,orden',
                'terlec:id,ano',
                'curso:Id,cursec,idNivel',
                'curso.nivel:id,abrev',
                'beca:id,nombreBeca',
            ])
            ->get()
            ->sortBy([
                fn (CuotaGenerada $r) => self::claveOrdenVenc1($r),
                fn (CuotaGenerada $r) => mb_strtoupper(trim((string) ($r->legajo?->apellido ?? ''))),
                fn (CuotaGenerada $r) => mb_strtoupper(trim((string) ($r->legajo?->nombre ?? ''))),
                fn (CuotaGenerada $r) => (int) $r->id,
            ])
            ->values();
    }

    /**
     * @param  int|array<int>  $idsFamilias
     * @return Builder<CuotaGenerada>
     */
    private static function consultaFilasDeuda(int|array $idsFamilias): Builder
    {
        return CuotaGenerada::query()
            ->where('faltapa', '>', 0)
            ->where('importe', '>', 0)
            ->whereHas('legajo', function ($q) use ($idsFamilias) {
                if (is_array($idsFamilias)) {
                    $q->whereIn('idFamilias', $idsFamilias);
                } else {
                    $q->where('idFamilias', $idsFamilias);
                }
            })
            ->with(['legajo:id,apellido,nombre,idFamilias']);
    }

    private static function aPagarDesdeRegistro(CuotaGenerada $registro): float
    {
        $calc = ImputacionPagoCalculo::calcular(
            $registro,
            (float) ($registro->faltapa ?? 0),
            Carbon::today(),
            null,
            true,
        );

        return round((float) $calc['aPagar'], 2);
    }

    /** Clave de orden ascendente por 1.er vencimiento (fechas vacías al final). */
    private static function claveOrdenVenc1(CuotaGenerada $registro): string
    {
        $venc1 = $registro->venc1;
        if ($venc1 instanceof \Carbon\CarbonInterface) {
            return $venc1->format('Y-m-d');
        }

        $raw = trim((string) ($venc1 ?? ''));
        if ($raw === '' || str_starts_with($raw, '0000-') || str_starts_with($raw, '-0001')) {
            return '9999-99-99';
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return '9999-99-99';
        }
    }
}
