<?php

namespace App\Support\Cuotas;

use App\Models\CuotaGenerada;
use App\Models\CuotasMes;
use App\Models\Ento;
use App\Models\Familia;
use App\Models\Legajo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Utilidades compartidas entre facturación AFIP en pago y en devengamiento.
 */
final class FacturacionAfipComun
{
    /**
     * @param  list<CuotaGenerada>  $registros
     * @return array{0: string, 1: string}
     */
    public static function periodoServicioLote(array $registros): array
    {
        $desde = null;
        $hasta = null;

        foreach ($registros as $registro) {
            [$ini, $fin] = self::periodoServicio($registro);
            if ($desde === null || $ini < $desde) {
                $desde = $ini;
            }
            if ($hasta === null || $fin > $hasta) {
                $hasta = $fin;
            }
        }

        if ($desde === null || $hasta === null) {
            $hoy = Carbon::today()->format('Ymd');

            return [$hoy, $hoy];
        }

        return [$desde, $hasta];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function periodoServicio(CuotaGenerada $registro): array
    {
        $ano = (int) ($registro->terlec?->ano ?? schoolCtx()->terlecAno());
        if ($ano <= 0) {
            $ano = (int) Carbon::today()->year;
        }

        $mes = self::numeroMesDesdeRegistro($registro);
        if ($mes < 1 || $mes > 12) {
            $fecha = $registro->venc1 ?? Carbon::today();

            return [$fecha->copy()->startOfMonth()->format('Ymd'), $fecha->copy()->endOfMonth()->format('Ymd')];
        }

        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();

        return [$inicio->format('Ymd'), $fin->format('Ymd')];
    }

    public static function documentoNumerico(mixed $valor): int
    {
        $digits = preg_replace('/\D/', '', (string) $valor) ?? '';

        return (int) $digits;
    }

    public static function cursoTextoDesdeRegistro(CuotaGenerada $registro): string
    {
        if (! $registro->relationLoaded('curso')) {
            $registro->load([
                'curso:Id,cursec,c,s,idCurPlan,idTurnoClase',
                'curso.curplan:id,curPlanCurso',
                'curso.turnoClase:id,nombre',
            ]);
        }

        return mb_strtoupper(trim((string) ($registro->curso?->nombreParaListado() ?? '')));
    }

    /**
     * @return array{telefonoInstitucion: string, aporteEstatal: string}
     */
    public static function snapshotInstitucionalPdf(Ento $ento): array
    {
        return [
            'telefonoInstitucion' => trim((string) ($ento->telefono ?? '')),
            'aporteEstatal' => Schema::hasColumn('ento', 'aporteEstatal')
                ? trim((string) ($ento->aporteEstatal ?? ''))
                : '',
        ];
    }

    /**
     * Responsable económico impreso en la factura: campo `familias.responsable`.
     */
    public static function responsableEconomicoFamilia(Legajo $legajo): string
    {
        $idFamilia = (int) ($legajo->idFamilias ?? 0);
        if ($idFamilia <= 0) {
            return '';
        }

        if ($legajo->relationLoaded('familia')) {
            return trim((string) ($legajo->familia?->responsable ?? ''));
        }

        return trim((string) (Familia::query()->whereKey($idFamilia)->value('responsable') ?? ''));
    }

    /**
     * DNI del responsable económico impreso en la factura: campo `familias.dniResp`.
     */
    public static function dniRespDesdeFamilia(Legajo $legajo): string
    {
        if (! Schema::hasColumn('familias', 'dniResp')) {
            return '';
        }

        $idFamilia = (int) ($legajo->idFamilias ?? 0);
        if ($idFamilia <= 0) {
            return '';
        }

        if ($legajo->relationLoaded('familia')) {
            return trim((string) ($legajo->familia?->dniResp ?? ''));
        }

        return trim((string) (Familia::query()->whereKey($idFamilia)->value('dniResp') ?? ''));
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function responsablePago(Legajo $legajo): array
    {
        $nombre = trim((string) ($legajo->respAdmiNom ?? ''));
        $dni = self::documentoNumerico($legajo->respAdmiDni ?? null);

        if ($nombre === '' || $dni <= 0) {
            $nombre = trim((string) ($legajo->nombrepad ?? ''));
            $dni = self::documentoNumerico($legajo->dnipad ?? null);
        }
        if ($nombre === '' || $dni <= 0) {
            $nombre = trim((string) ($legajo->nombremad ?? ''));
            $dni = self::documentoNumerico($legajo->dnimad ?? null);
        }

        return [$nombre, $dni > 0 ? (string) $dni : ''];
    }

    public static function formatearFechaBarra(string $yyyymmdd): string
    {
        $raw = preg_replace('/\D/', '', $yyyymmdd) ?? '';
        if (strlen($raw) !== 8) {
            return Carbon::today()->format('Y/m/d');
        }

        return substr($raw, 0, 4).'/'.substr($raw, 4, 2).'/'.substr($raw, 6, 2);
    }

    public static function formatearFechaEnto(mixed $valor): string
    {
        $raw = trim((string) ($valor ?? ''));
        if ($raw === '') {
            return '';
        }

        if (str_contains($raw, '/')) {
            return $raw;
        }

        try {
            return Carbon::parse($raw)->format('d/m/Y');
        } catch (Throwable) {
            return $raw;
        }
    }

    public static function guardarMensajeCuota(CuotaGenerada $registro, string $mensaje): void
    {
        if (! Schema::hasColumn('cuotasgeneradas', 'mensajeResultado')) {
            return;
        }

        try {
            $registro->mensajeResultado = mb_substr($mensaje, 0, 500);
            $registro->save();
        } catch (Throwable) {
            // No bloquear el flujo por un fallo al guardar el mensaje auxiliar.
        }
    }

    private static function numeroMesDesdeRegistro(CuotaGenerada $registro): int
    {
        $idMes = (int) ($registro->idCuotasmeses ?? 0);
        if ($idMes > 0) {
            $mesCatalogo = CuotasMes::query()->find($idMes, ['mes']);
            $mes = self::mesDesdeEtiqueta((string) ($mesCatalogo?->mes ?? ''));
            if ($mes > 0) {
                return $mes;
            }
        }

        return self::mesDesdeEtiqueta((string) ($registro->cuota?->nombre ?? ''));
    }

    private static function mesDesdeEtiqueta(string $texto): int
    {
        $mapa = [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];

        $n = mb_strtolower(trim($texto));

        return $mapa[$n] ?? 0;
    }
}
