<?php

namespace App\Support\Cooperadora;

use App\Models\CoopIngreso;
use Illuminate\Support\Collection;

/**
 * Ingresos cooperadora (coop_ingresos) asociados a un estudiante.
 */
final class PagosEstudianteCooperadoraConsulta
{
    /**
     * @return Collection<int, CoopIngreso>
     */
    public static function ingresos(int $idLegajo): Collection
    {
        if (BusquedaEstudianteCooperadora::legajo($idLegajo) === null) {
            return collect();
        }

        return CoopIngreso::query()
            ->with([
                'rubro:id,nombre',
                'item:id,nombre',
                'medioPago:id,nombre',
            ])
            ->where('anulado', false)
            ->where('tipo', 'origen_estudiantes')
            ->where('id_legajo', $idLegajo)
            ->orderByDesc('fecha')
            ->orderByDesc('recibo_numero')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function filas(int $idLegajo): array
    {
        return self::ingresos($idLegajo)
            ->map(fn (CoopIngreso $ingreso): array => self::filaDesdeIngreso($ingreso))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function encabezadoEstudiante(int $idLegajo): ?array
    {
        $legajo = BusquedaEstudianteCooperadora::legajo($idLegajo);
        if ($legajo === null) {
            return null;
        }

        $matricula = BusquedaEstudianteCooperadora::matriculaActiva($idLegajo);

        return [
            'apellido' => trim((string) ($legajo->apellido ?? '')),
            'nombre' => trim((string) ($legajo->nombre ?? '')),
            'dni' => trim((string) ($legajo->dni ?? '')),
            'curso' => BusquedaEstudianteCooperadora::etiquetaCurso($matricula),
            'terlecAno' => BusquedaEstudianteCooperadora::etiquetaAnioCiclo(),
            'esHermanoCooperadora' => BusquedaEstudianteCooperadora::esHermanoCooperadora($idLegajo),
            'idMatriculaActiva' => $matricula !== null ? (int) $matricula->id : null,
        ];
    }

    /**
     * @return array{
     *     idIngreso: int,
     *     fecha: string,
     *     reciboNumero: string,
     *     rubro: string,
     *     item: string,
     *     concepto: string,
     *     pagadorNombre: string,
     *     pagadorVinculo: string,
     *     medioPago: string,
     *     importeBruto: string,
     *     descuentoPct: string,
     *     importe: string,
     *     reciboDestinatarioTexto: string,
     *     reciboDestinatarioEmail: string,
     *     reciboEmailEstado: string,
     *     reciboEmailEstadoEtiqueta: string,
     *     reciboEmailEnviadoAt: string,
     *     reciboEmailError: string,
     *     _importe: float
     * }
     */
    public static function filaDesdeIngreso(CoopIngreso $ingreso): array
    {
        $importe = round((float) ($ingreso->importe ?? 0), 2);
        $importeBruto = round((float) ($ingreso->importe_bruto ?? $importe), 2);
        $descuentoPct = round((float) ($ingreso->descuento_pct ?? 0), 2);

        $medioPago = trim((string) ($ingreso->medioPago?->nombre ?? ''));
        if ($medioPago === '') {
            $medioPago = trim((string) ($ingreso->medio_pago ?? ''));
        }

        $estado = trim((string) ($ingreso->recibo_email_estado ?? 'pendiente'));
        if ($estado === '') {
            $estado = 'pendiente';
        }

        $enviadoAt = '';
        if ($ingreso->recibo_email_enviado_at instanceof \Carbon\CarbonInterface) {
            $enviadoAt = $ingreso->recibo_email_enviado_at->format('d/m/Y H:i');
        }

        $email = mb_strtolower(trim((string) ($ingreso->pagador_email ?? '')));
        $nombrePagador = trim((string) ($ingreso->pagador_nombre ?? ''));

        $fila = [
            'idIngreso' => (int) $ingreso->id,
            'idReferenciaRecibo' => ReciboIngresosGrupo::idReferenciaPdf($ingreso),
            'fecha' => $ingreso->fecha?->format('d/m/Y') ?? '',
            'reciboNumero' => NumeroDocumentoCooperadora::formatearRecibo((int) $ingreso->recibo_numero),
            'rubro' => trim((string) ($ingreso->rubro?->nombre ?? '')),
            'item' => trim((string) ($ingreso->item?->nombre ?? '')),
            'concepto' => trim((string) ($ingreso->concepto ?? '')),
            'pagadorNombre' => $nombrePagador,
            'pagadorVinculo' => trim((string) ($ingreso->pagador_vinculo ?? '')),
            'medioPago' => $medioPago,
            'importeBruto' => self::formatearImporte($importeBruto),
            'descuentoPct' => self::formatearPorcentaje($descuentoPct),
            'importe' => self::formatearImporte($importe),
            'reciboDestinatarioEmail' => $email,
            'reciboEmailEstado' => $estado,
            'reciboEmailEstadoEtiqueta' => EnvioReciboCooperadora::etiquetaEstado($estado),
            'reciboEmailEnviadoAt' => $enviadoAt,
            'reciboEmailError' => trim((string) ($ingreso->recibo_email_error ?? '')),
            '_importe' => $importe,
        ];

        $fila['reciboDestinatarioTexto'] = self::textoReciboEnviadoA($nombrePagador, $email);

        return $fila;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{importe: string, cantidad: int}
     */
    public static function totalesDesdeFilas(array $filas): array
    {
        $importe = 0.0;
        foreach ($filas as $fila) {
            $importe += (float) ($fila['_importe'] ?? 0);
        }

        return [
            'importe' => self::formatearImporte($importe),
            'cantidad' => count($filas),
        ];
    }

    public static function textoReciboEnviadoA(string $nombrePagador, string $email): string
    {
        if ($nombrePagador !== '' && $email !== '') {
            return $nombrePagador.' <'.$email.'>';
        }

        if ($email !== '') {
            return $email;
        }

        if ($nombrePagador !== '') {
            return $nombrePagador;
        }

        return '—';
    }

    public static function formatearImporte(float $valor): string
    {
        return '$'.number_format($valor, 2, ',', '.');
    }

    public static function formatearPorcentaje(float $valor): string
    {
        $s = rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');

        return ($s === '' ? '0' : $s).'%';
    }
}
