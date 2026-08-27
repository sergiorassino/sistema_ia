<?php

namespace App\Support\Mora;

use App\Models\CuotaGenerada;
use App\Support\Cuotas\CuotasFormato;
use App\Support\Cuotas\GestionAranceles;
use App\Support\Cuotas\ImputacionPagoCalculo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Datos del PDF «Estado de deuda» por estudiante (mismo cálculo que el listado familiar).
 */
final class EstadoDeudaEstudianteDatos
{
    /**
     * @return array<string, mixed>|null
     */
    public static function paraEstudiante(int $idLegajo): ?array
    {
        $legajo = EstadoDeudaEstudianteListado::estudianteEnAlcance($idLegajo);
        if ($legajo === null) {
            return null;
        }

        $filas = self::filasDeuda($idLegajo);
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

            $curso = $registro->curso;
            $becaEtiqueta = GestionAranceles::etiquetaBeca($registro);
            if ($becaEtiqueta === '') {
                $becaEtiqueta = 'C/E';
            }

            $filasPdf[] = [
                'nro' => $nro,
                'estudiante' => mb_strtoupper(trim(
                    trim((string) ($legajo->apellido ?? '')).' '.trim((string) ($legajo->nombre ?? '')),
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

        $estudianteEtiqueta = EstadoDeudaEstudianteListado::apellidoNombre($legajo);
        $familia = EstadoDeudaEstudianteListado::familiaAsignada($legajo->familia);
        $apellidoFamilia = trim((string) ($familia?->apellido ?? ''));
        $responsable = trim((string) ($familia?->responsable ?? ''));

        $familiaLinea = $estudianteEtiqueta;
        if ($familia !== null) {
            $familiaParte = mb_strtoupper(trim(
                $apellidoFamilia.($apellidoFamilia !== '' && $responsable !== '' ? ' - ' : '').$responsable,
            ));
            if ($familiaParte !== '') {
                $familiaLinea = trim($estudianteEtiqueta.' — FAMILIA '.$familiaParte);
            }
        } else {
            $familiaLinea = trim($estudianteEtiqueta.' — SIN FAMILIA ASIGNADA');
        }

        return [
            'pdfHeader' => schoolPdfHeaderData(),
            'tituloDocumento' => 'Estado de deuda por estudiante',
            'fechaInforme' => Carbon::now()->format('d/m/Y'),
            'familiaEtiqueta' => $estudianteEtiqueta !== '' ? $estudianteEtiqueta : 'estudiante',
            'responsableEtiqueta' => mb_strtoupper($responsable),
            'familiaLinea' => mb_strtoupper($familiaLinea),
            'filas' => $filasPdf,
            'totales' => [
                'importe' => CuotasFormato::formatearImporte($totImporte),
                'interes' => CuotasFormato::formatearImporte($totInteres),
                'aPagar' => CuotasFormato::formatearImporte($totAPagar),
            ],
        ];
    }

    /**
     * Totales «a pagar» (saldo + interés/bonificación) agrupados por legajo.
     *
     * @param  array<int>  $idsLegajos
     * @return array<int, float>
     */
    public static function totalesAPagarPorLegajos(array $idsLegajos): array
    {
        $idsLegajos = array_values(array_unique(array_filter(
            array_map('intval', $idsLegajos),
            fn (int $id) => $id > 0,
        )));

        $porLegajo = array_fill_keys($idsLegajos, 0.0);

        if ($idsLegajos === []) {
            return [];
        }

        $filas = self::consultaFilasDeuda($idsLegajos)->get();

        foreach ($filas as $registro) {
            $idLegajo = (int) $registro->idLegajos;
            if ($idLegajo > 0 && array_key_exists($idLegajo, $porLegajo)) {
                $porLegajo[$idLegajo] += self::aPagarDesdeRegistro($registro);
            }
        }

        return $porLegajo;
    }

    /**
     * @return Collection<int, CuotaGenerada>
     */
    private static function filasDeuda(int $idLegajo): Collection
    {
        return self::consultaFilasDeuda([$idLegajo])
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
                fn (CuotaGenerada $r) => (int) $r->id,
            ])
            ->values();
    }

    /**
     * @param  array<int>  $idsLegajos
     * @return Builder<CuotaGenerada>
     */
    private static function consultaFilasDeuda(array $idsLegajos): Builder
    {
        return CuotaGenerada::query()
            ->where('faltapa', '>', 0)
            ->where('importe', '>', 0)
            ->whereIn('idLegajos', $idsLegajos);
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
