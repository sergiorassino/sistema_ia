<?php

namespace App\Support\Cuotas;

use App\Models\CuotaGenerada;
use App\Support\Alumnos\ArancelesEscolares;
use Illuminate\Support\Collection;

/**
 * Datos para el PDF «Cuotas adeudadas» del estudiante (Administración y portal familia).
 */
final class CuotasAdeudadasEstudianteDatos
{
    public const MODO_ADMIN = 'admin';

    public const MODO_AUTOGESTION = 'autogestion';

    /**
     * @return array<string, mixed>|null
     */
    public static function paraAdministracion(int $idLegajo): ?array
    {
        if (GestionAranceles::legajoParaGestion($idLegajo) === null) {
            return null;
        }

        $encabezado = GestionAranceles::encabezadoEstudiante($idLegajo);
        if ($encabezado === null) {
            return null;
        }

        $cuotas = self::filtrarAdeudadas(GestionAranceles::cuotasDelEstudiante($idLegajo));
        if ($cuotas->isEmpty()) {
            return null;
        }

        $totales = GestionAranceles::totalizarSaldosAdeudados($cuotas);

        return [
            'modo' => self::MODO_ADMIN,
            'pdfHeader' => schoolPdfHeaderData(),
            'fechaImpresion' => now()->format('d/m/Y H:i'),
            'apellidoNombre' => mb_strtoupper(trim(($encabezado['apellido'] ?? '').' '.($encabezado['nombre'] ?? ''))),
            'dni' => (string) ($encabezado['dni'] ?? ''),
            'curso' => (string) ($encabezado['curso'] ?? ''),
            'terlecAno' => (string) ($encabezado['terlecAno'] ?? schoolCtx()->terlecAno()),
            'becaResumen' => (string) ($encabezado['becaResumen'] ?? ''),
            'codigoPagoElectronico' => '',
            'filas' => self::filasAdministracion($cuotas),
            'totales' => [
                'neto' => CuotasFormato::formatearImporte($totales['neto']),
                'conIntereses' => CuotasFormato::formatearImporte($totales['conIntereses']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function paraAutogestion(): ?array
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $encabezado = ArancelesEscolares::encabezadoAutogestion();
        if ($encabezado === null) {
            return null;
        }

        $cuotas = self::filtrarAdeudadas(ArancelesEscolares::cuotasPendientes());
        if ($cuotas->isEmpty()) {
            return null;
        }

        $totales = GestionAranceles::totalizarSaldosAdeudados($cuotas);

        return [
            'modo' => self::MODO_AUTOGESTION,
            'pdfHeader' => studentPdfHeaderData(),
            'fechaImpresion' => now()->format('d/m/Y H:i'),
            'apellidoNombre' => mb_strtoupper(trim($encabezado['apellido'].', '.$encabezado['nombre'])),
            'dni' => (string) ($encabezado['dni'] ?? ''),
            'curso' => (string) ($encabezado['curso'] ?? ''),
            'nivel' => (string) ($encabezado['nivel'] ?? ''),
            'terlecAno' => (string) ($ctx->terlecAno() ?? ''),
            'becaResumen' => '',
            'codigoPagoElectronico' => (string) ($encabezado['codigoPagoElectronico'] ?? ''),
            'filas' => self::filasAutogestion($cuotas),
            'totales' => [
                'neto' => ArancelesEscolares::formatearImporte($totales['neto']),
                'conIntereses' => ArancelesEscolares::formatearImporte($totales['conIntereses']),
            ],
        ];
    }

    /**
     * @param  Collection<int, CuotaGenerada>  $cuotas
     * @return Collection<int, CuotaGenerada>
     */
    private static function filtrarAdeudadas(Collection $cuotas): Collection
    {
        return $cuotas->filter(
            fn (CuotaGenerada $registro) => (float) ($registro->faltapa ?? 0) > 0
                && (float) ($registro->importe ?? 0) > 0,
        )->values();
    }

    /**
     * @param  Collection<int, CuotaGenerada>  $cuotas
     * @return list<array<string, string>>
     */
    private static function filasAdministracion(Collection $cuotas): array
    {
        $filas = [];

        foreach ($cuotas as $registro) {
            $nivelTexto = mb_strtoupper(trim((string) ($registro->curso?->nivel?->nivel ?? '')));
            [$nivelLinea1, $nivelLinea2] = CuotasFormato::nivelEnDosLineas($nivelTexto);
            $nivel = trim($nivelLinea1.' '.($nivelLinea2 !== '' ? $nivelLinea2 : ''));

            $filas[] = [
                'ano' => (string) ($registro->terlec?->ano ?? ''),
                'nivel' => $nivel,
                'curso' => mb_strtoupper(trim((string) ($registro->curso?->nombreParaListado() ?? ''))),
                'cuota' => mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? ''))),
                'beca' => GestionAranceles::etiquetaBeca($registro),
                'venc1' => CuotasFormato::formatearFecha($registro->venc1),
                'venc2' => CuotasFormato::formatearFecha($registro->venc2),
                'vencAct' => CuotasFormato::formatearFecha($registro->nueVenc),
                'importe' => CuotasFormato::formatearImporte($registro->importe),
                'bonificacion' => CuotasFormato::formatearImporte($registro->bonificacion),
                'interes' => CuotasFormato::formatearImporte($registro->interes),
                'pagado' => CuotasFormato::formatearImporte($registro->pagado),
                'saldo' => CuotasFormato::formatearImporte($registro->faltapa),
            ];
        }

        return $filas;
    }

    /**
     * @param  Collection<int, CuotaGenerada>  $cuotas
     * @return list<array<string, string>>
     */
    private static function filasAutogestion(Collection $cuotas): array
    {
        $filas = [];

        foreach ($cuotas as $registro) {
            $filas[] = [
                'apellidoNombre' => mb_strtoupper(trim(trim((string) ($registro->legajo->apellido ?? '')).', '.trim((string) ($registro->legajo->nombre ?? '')))),
                'dni' => ArancelesEscolares::formatearDni($registro->legajo->dni ?? ''),
                'curso' => mb_strtoupper(trim((string) ($registro->curso?->nombreParaListado() ?? ''))),
                'nivel' => mb_strtoupper(trim((string) ($registro->curso?->nivel?->nivel ?? ''))),
                'ano' => (string) ($registro->terlec?->ano ?? ''),
                'cuota' => mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? ''))),
                'venc1' => ArancelesEscolares::formatearFecha($registro->venc1),
                'venc2' => ArancelesEscolares::formatearFecha($registro->venc2),
                'vencAct' => ArancelesEscolares::formatearFecha($registro->nueVenc),
                'saldo' => ArancelesEscolares::formatearImporte($registro->faltapa),
            ];
        }

        return $filas;
    }
}
