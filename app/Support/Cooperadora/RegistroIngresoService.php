<?php

namespace App\Support\Cooperadora;

use App\Models\CoopIngreso;
use App\Models\CoopItemIngreso;
use App\Models\CoopRubroIngreso;
use Illuminate\Support\Collection;
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
        $resultado = self::registrarLote([$datos], [
            'pagador_nombre' => $datos['pagador_nombre'],
            'fecha' => $datos['fecha'],
            'id_medio_pago' => (int) $datos['id_medio_pago'],
            'id_legajo' => $datos['id_legajo'] ?? null,
            'id_matricula' => $datos['id_matricula'] ?? null,
        ]);

        return $resultado['lider'];
    }

    /**
     * Registra varios ingresos con un único número de recibo (impresión conjunta).
     *
     * @param  list<array{
     *   tipo: string,
     *   id_rubro: int,
     *   id_item: int,
     *   id_legajo?: int|null,
     *   id_matricula?: int|null,
     *   concepto?: string|null,
     *   importe_bruto?: float|null,
     *   importe?: float|null,
     *   descuento_pct?: float|null,
     * }>  $lineas
     * @param  array{
     *   pagador_nombre: string,
     *   pagador_vinculo?: string|null,
     *   pagador_email?: string|null,
     *   fecha: string,
     *   id_medio_pago: int,
     *   id_legajo?: int|null,
     *   id_matricula?: int|null,
     * }  $comun
     * @return array{lider: CoopIngreso, ingresos: Collection<int, CoopIngreso>}
     */
    public static function registrarLote(array $lineas, array $comun): array
    {
        abort_if($lineas === [], 422);

        return DB::transaction(function () use ($lineas, $comun) {
            $reciboNum = NumeroDocumentoCooperadora::reservarRecibo();
            $medio = MedioPagoCooperadora::resolver((int) $comun['id_medio_pago']);
            abort_unless($medio !== null, 422);

            $ingresos = collect();
            foreach ($lineas as $datos) {
                $ingresos->push(self::crearIngreso($datos, $comun, $reciboNum, $medio, null));
            }

            if ($ingresos->count() > 1) {
                $liderId = (int) $ingresos->first()->id;
                CoopIngreso::query()
                    ->whereIn('id', $ingresos->pluck('id'))
                    ->update(['recibo_grupo_id' => $liderId]);
                $ingresos = $ingresos->map(function (CoopIngreso $ingreso) use ($liderId) {
                    $ingreso->recibo_grupo_id = $liderId;

                    return $ingreso;
                });
            }

            return [
                'lider' => $ingresos->first(),
                'ingresos' => $ingresos,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $comun
     * @param  array{id: int, nombre: string}  $medio
     */
    private static function crearIngreso(array $datos, array $comun, int $reciboNum, array $medio, ?int $reciboGrupoId): CoopIngreso
    {
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

        $idLegajo = isset($datos['id_legajo'])
            ? (int) $datos['id_legajo']
            : (int) ($comun['id_legajo'] ?? 0);
        $idLegajo = $idLegajo > 0 ? $idLegajo : null;

        $idMatricula = isset($datos['id_matricula'])
            ? (int) $datos['id_matricula']
            : (int) ($comun['id_matricula'] ?? 0);
        $idMatricula = $idMatricula > 0 ? $idMatricula : null;

        $concepto = trim((string) ($datos['concepto'] ?? ''));
        if ($concepto === '') {
            $concepto = self::armarConcepto($tipo, $item, (int) ($idLegajo ?? 0), (int) ($idMatricula ?? 0));
        }

        return CoopIngreso::query()->create([
            'tipo' => $tipo,
            'id_rubro' => $rubro->id,
            'id_item' => $item->id,
            'id_legajo' => $idLegajo,
            'id_matricula' => $idMatricula,
            'pagador_nombre' => mb_strtoupper(trim((string) $comun['pagador_nombre']), 'UTF-8'),
            'pagador_vinculo' => self::normalizarPagadorVinculo($comun['pagador_vinculo'] ?? null),
            'pagador_email' => self::normalizarPagadorEmail($comun['pagador_email'] ?? null),
            'recibo_email_estado' => 'pendiente',
            'fecha' => $comun['fecha'],
            'concepto' => $concepto,
            'importe_bruto' => $importeBruto,
            'descuento_pct' => $descuentoPct,
            'importe' => $importe,
            'importe_letras' => ImporteEnLetrasEs::pesos($importe),
            'recibo_numero' => $reciboNum,
            'recibo_grupo_id' => $reciboGrupoId,
            'id_medio_pago' => $medio['id'],
            'medio_pago' => $medio['nombre'],
            'id_profesor' => (int) schoolCtx()->idProfesor,
            'anulado' => false,
        ]);
    }

    private static function armarConcepto(string $tipo, ?CoopItemIngreso $item, int $idLegajo, int $idMatricula): string
    {
        $partes = [];
        $nombreItem = trim((string) ($item?->nombre ?? ''));
        $anio = BusquedaEstudianteCooperadora::etiquetaAnioCiclo();

        if ($nombreItem !== '') {
            if ($tipo === 'origen_estudiantes') {
                $partes[] = $nombreItem.' del año '.$anio;
            } else {
                $partes[] = $nombreItem;
            }
        }

        if ($idLegajo > 0) {
            $legajo = BusquedaEstudianteCooperadora::legajo($idLegajo);
            if ($legajo !== null) {
                $partes[] = 'Estudiante: '.$legajo->apellido.', '.$legajo->nombre;
            }
        }

        if ($idMatricula > 0) {
            $matricula = BusquedaEstudianteCooperadora::matriculaActiva($idLegajo);
            if ($matricula === null) {
                $matricula = \App\Models\Matricula::query()->with('curso')->find($idMatricula);
            }
            $curso = BusquedaEstudianteCooperadora::etiquetaCurso($matricula);
            if ($curso !== '') {
                $partes[] = 'Curso: '.$curso;
            }
        }

        return implode(', ', array_filter($partes)) ?: 'Ingreso cooperadora';
    }

    private static function normalizarPagadorVinculo(mixed $vinculo): ?string
    {
        $vinculo = is_string($vinculo) ? trim($vinculo) : '';

        return in_array($vinculo, ResponsablesLegajoCooperadora::VINCULOS, true) ? $vinculo : null;
    }

    private static function normalizarPagadorEmail(mixed $email): ?string
    {
        $email = is_string($email) ? mb_strtolower(trim($email), 'UTF-8') : '';

        return $email !== '' ? $email : null;
    }
}
