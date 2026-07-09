<?php

namespace App\Support\Arca;

use App\Models\Ento;
use App\Support\Afip\AfipPadronA13Consulta;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Consulta CUIT/CUIL asociados a un DNI en ARCA (Padrón A13).
 */
final class ConsultaCuitPorDniService
{
    public static function moduloDisponible(): bool
    {
        return tenantArcaPadronHabilitado();
    }

    /**
     * @return array{
     *     ok: bool,
     *     mensaje: string,
     *     simulado?: bool,
     *     dni?: string,
     *     cuits?: list<string>
     * }
     */
    public static function consultar(string $dni): array
    {
        if (! self::moduloDisponible()) {
            return ['ok' => false, 'mensaje' => 'La consulta ARCA no está habilitada para este colegio.'];
        }

        $config = tenantArcaPadronConfig();
        if ($config === null) {
            return ['ok' => false, 'mensaje' => 'Faltan certificados AFIP/ARCA en parámetros del sistema.'];
        }

        $dniNumerico = (int) (preg_replace('/\D/', '', $dni) ?? '');
        if ($dniNumerico <= 0 || $dniNumerico > 99999999) {
            return ['ok' => false, 'mensaje' => 'Ingrese un DNI válido (hasta 8 dígitos).'];
        }

        $columnasEnto = ['cuit'];
        if (Schema::hasColumn('ento', 'cuitFact')) {
            $columnasEnto[] = 'cuitFact';
        }

        $ento = Ento::query()
            ->where('idNivel', (int) schoolCtx()->idNivel)
            ->first($columnasEnto);

        if ($ento === null) {
            return ['ok' => false, 'mensaje' => 'Faltan datos institucionales (ento) para consultar ARCA.'];
        }

        if ($ento->cuitParaFacturar() === '') {
            return ['ok' => false, 'mensaje' => 'Falta configurar el CUIT de la institución en parámetros del sistema.'];
        }

        $cuitInstitucion = preg_replace('/\D/', '', $ento->cuitParaFacturar()) ?? '';

        try {
            $cuits = AfipPadronA13Consulta::cuitsPorDni($config, $cuitInstitucion, $dniNumerico);
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'Error al consultar en ARCA: '.$e->getMessage()];
        }

        if ($cuits === []) {
            return [
                'ok' => false,
                'mensaje' => 'ARCA no devolvió CUIT/CUIL para el DNI ingresado.',
            ];
        }

        $simulado = (bool) ($config['simular'] ?? false);

        return [
            'ok' => true,
            'mensaje' => $simulado
                ? 'Consulta en modo simulación (no se contactó a ARCA).'
                : 'Consulta realizada correctamente.',
            'simulado' => $simulado,
            'dni' => (string) $dniNumerico,
            'cuits' => $cuits,
        ];
    }
}
