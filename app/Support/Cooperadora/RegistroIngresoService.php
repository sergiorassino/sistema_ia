<?php

namespace App\Support\Cooperadora;

use App\Models\CoopIngreso;
use App\Models\CoopItemIngreso;
use App\Models\CoopRubroIngreso;
use App\Models\Legajo;
use Illuminate\Support\Facades\DB;

final class RegistroIngresoService
{
    /**
     * @param  array{
     *   tipo: string,
     *   id_rubro: int,
     *   id_item: int,
     *   id_legajo?: int|null,
     *   id_matricula?: int|null,
     *   pagador_nombre: string,
     *   fecha: string,
     *   concepto?: string|null,
     *   importe_bruto?: float|null,
     *   importe?: float|null,
     *   descuento_pct?: float|null,
     *   id_medio_pago: int,
     * }  $datos
     */
    public static function registrar(array $datos): CoopIngreso
    {
        return DB::transaction(function () use ($datos) {
            $rubro = CoopRubroIngreso::query()->findOrFail((int) $datos['id_rubro']);
            $tipo = (string) $datos['tipo'];
            abort_unless($rubro->tipo === $tipo, 422);

            $item = CoopItemIngreso::query()
                ->where('id_rubro', $rubro->id)
                ->findOrFail((int) $datos['id_item']);

            $importeBruto = isset($datos['importe_bruto'])
                ? round((float) $datos['importe_bruto'], 2)
                : round((float) ($item->precio ?? $datos['importe'] ?? 0), 2);

            $descuentoPct = round((float) ($datos['descuento_pct'] ?? 0), 2);
            $importe = isset($datos['importe'])
                ? round((float) $datos['importe'], 2)
                : DescuentoHermanos::importeConDescuento($importeBruto, $descuentoPct);

            $concepto = trim((string) ($datos['concepto'] ?? ''));
            if ($concepto === '') {
                $concepto = self::armarConcepto($tipo, $item, (int) ($datos['id_legajo'] ?? 0), (int) ($datos['id_matricula'] ?? 0));
            }

            $reciboNum = NumeroDocumentoCooperadora::reservarRecibo();
            $medio = MedioPagoCooperadora::resolver((int) $datos['id_medio_pago']);
            abort_unless($medio !== null, 422);

            return CoopIngreso::query()->create([
                'tipo' => $tipo,
                'id_rubro' => $rubro->id,
                'id_item' => $item->id,
                'id_legajo' => $datos['id_legajo'] ?? null,
                'id_matricula' => $datos['id_matricula'] ?? null,
                'pagador_nombre' => mb_strtoupper(trim((string) $datos['pagador_nombre']), 'UTF-8'),
                'fecha' => $datos['fecha'],
                'concepto' => $concepto,
                'importe_bruto' => $importeBruto,
                'descuento_pct' => $descuentoPct,
                'importe' => $importe,
                'importe_letras' => ImporteEnLetrasEs::pesos($importe),
                'recibo_numero' => $reciboNum,
                'id_medio_pago' => $medio['id'],
                'medio_pago' => $medio['nombre'],
                'id_profesor' => (int) schoolCtx()->idProfesor,
                'anulado' => false,
            ]);
        });
    }

    private static function armarConcepto(string $tipo, ?CoopItemIngreso $item, int $idLegajo, int $idMatricula): string
    {
        $partes = [];
        $nombreItem = trim((string) ($item?->nombre ?? ''));
        $anio = BusquedaEstudianteCooperadora::etiquetaAnioCiclo();

        if ($nombreItem !== '') {
            if ($tipo === 'por_alumno') {
                $partes[] = 'Pago de contribución correspondiente a '.$nombreItem.' del año '.$anio;
            } elseif ($tipo === 'uniforme') {
                $partes[] = 'Venta de '.$nombreItem.' — año '.$anio;
            } else {
                $partes[] = $nombreItem;
            }
        }

        if ($idLegajo > 0) {
            $legajo = Legajo::query()->find($idLegajo);
            if ($legajo) {
                $partes[] = 'Alumno: '.BusquedaEstudianteCooperadora::nombrePagadorDesdeLegajo($legajo);
            }
        }

        if ($idMatricula > 0) {
            $matricula = BusquedaEstudianteCooperadora::matriculaActiva($idLegajo);
            if ($matricula === null && $idMatricula > 0) {
                $matricula = \App\Models\Matricula::query()->with('curso')->find($idMatricula);
            }
            $curso = BusquedaEstudianteCooperadora::etiquetaCurso($matricula);
            if ($curso !== '') {
                $partes[] = 'Curso: '.$curso;
            }
        }

        return implode(', ', array_filter($partes)) ?: 'Ingreso cooperadora';
    }
}
