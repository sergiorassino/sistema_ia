<?php

namespace App\Support\Alumnos;

use App\Models\ComprobanteAfip;
use App\Models\CuotaGenerada;
use App\Support\Cuotas\ComprobantesAfipCuotaService;
use App\Support\Cuotas\GestionAranceles;
use App\Support\InformeInasistencias;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Cuotas pendientes de pago — portal familia (autogestión).
 */
final class ArancelesEscolares
{
    /**
     * @return Collection<int, CuotaGenerada>
     */
    public static function cuotasPendientes(): Collection
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return collect();
        }

        return CuotaGenerada::query()
            ->with(self::relacionesCuotaListado())
            ->where('idLegajos', (int) $ctx->idLegajo)
            ->where('faltapa', '>', 0)
            ->orderBy('venc1')
            ->orderBy('id')
            ->get();
    }

    /**
     * Historial completo del estudiante en sesión (pagadas e impagas, todos los ciclos).
     *
     * @return Collection<int, CuotaGenerada>
     */
    public static function cuotasHistorial(): Collection
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return collect();
        }

        return GestionAranceles::cuotasHistorial((int) $ctx->idLegajo);
    }

    /**
     * Datos del estudiante y código de pago electrónico para el encabezado del listado.
     *
     * @return array{
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string,
     *     nivel: string,
     *     codigoPagoElectronico: string
     * }|null
     */
    public static function encabezadoAutogestion(): ?array
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $legajo = $ctx->alumno();
        if ($legajo === null) {
            return null;
        }

        $curso = '';
        if (InformeInasistencias::tieneMatriculaCursoAutogestion()) {
            $curso = InformeInasistencias::cursoNombreAutogestion();
        }

        $idLegajo = (int) $ctx->idLegajo;
        $idNivel = (int) $ctx->idNivel;

        return [
            'apellido' => mb_strtoupper(trim((string) ($legajo->apellido ?? ''))),
            'nombre' => mb_strtoupper(trim((string) ($legajo->nombre ?? ''))),
            'dni' => self::formatearDni($legajo->dni ?? ''),
            'curso' => mb_strtoupper($curso),
            'nivel' => mb_strtoupper(trim((string) $ctx->nivelNombre())),
            'codigoPagoElectronico' => self::codigoPagoElectronicoSeguro($idLegajo, $idNivel),
        ];
    }

    private static function codigoPagoElectronicoSeguro(int $idLegajo, int $idNivel): string
    {
        if (! tenantCuotasSiroHabilitado()) {
            return '';
        }

        try {
            return ComprobantePagoPdf::codigoPagoElectronico($idLegajo, $idNivel);
        } catch (\App\Support\Cuotas\Siro\SiroConfiguracionIncompletaException) {
            return '';
        }
    }

    public static function cuotaPendienteParaAutogestion(int $idCuotaGenerada): ?CuotaGenerada
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        return CuotaGenerada::query()
            ->with(array_merge(self::relacionesCuotaListado(), [
                'legajo:id,apellido,nombre,dni,legajo',
            ]))
            ->where('id', $idCuotaGenerada)
            ->where('idLegajos', (int) $ctx->idLegajo)
            ->where('faltapa', '>', 0)
            ->first();
    }

    /**
     * Cuota con saldo pendiente — Gestión de aranceles (Administración).
     *
     * Mismo alcance que el listado del estudiante ({@see GestionAranceles::cuotaParaGestion}):
     * ciclo activo por año lectivo e impagas de años anteriores (no solo idTerlec de sesión).
     */
    public static function cuotaPendienteParaAdministracion(int $idCuotaGenerada, int $idLegajo): ?CuotaGenerada
    {
        $registro = GestionAranceles::cuotaParaGestion($idCuotaGenerada, $idLegajo)
            ?? GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo);

        if ($registro === null || (float) ($registro->faltapa ?? 0) <= 0) {
            return null;
        }

        return $registro;
    }

    public static function formatearImporte(float|int|string|null $valor): string
    {
        return number_format((float) ($valor ?? 0), 2, ',', '.');
    }

    public static function formatearDni(mixed $dni): string
    {
        $raw = trim((string) ($dni ?? ''));
        if ($raw === '') {
            return '';
        }

        if (ctype_digit($raw)) {
            return number_format((float) $raw, 0, '', '.');
        }

        return $raw;
    }

    public static function formatearFecha(mixed $fecha): string
    {
        if (self::esFechaVacia($fecha)) {
            return '';
        }

        if ($fecha instanceof CarbonInterface) {
            return $fecha->format('d/m/Y');
        }

        $raw = trim((string) ($fecha ?? ''));

        try {
            return Carbon::parse($raw)->format('d/m/Y');
        } catch (\Throwable) {
            return $raw;
        }
    }

    /**
     * Fecha sin valor en BD legacy (null, '', 0000-00-00 o Carbon inválido p. ej. año -1).
     */
    public static function esFechaVacia(mixed $fecha): bool
    {
        if ($fecha === null) {
            return true;
        }

        if ($fecha instanceof CarbonInterface) {
            return $fecha->year < 1900;
        }

        $raw = trim((string) $fecha);
        if ($raw === '' || $raw === '0000-00-00') {
            return true;
        }

        if (str_starts_with($raw, '0000-') || str_starts_with($raw, '-0001')) {
            return true;
        }

        try {
            return Carbon::parse($raw)->year < 1900;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Cuota no reimprimible en autogestión:
     * - 1º y 2º venc. vencidos sin fecha "actualizada al" (nueVenc), o
     * - 1º, 2º venc. y actualizada al también vencidos.
     */
    public static function cuotaVencidaParaReimpresion(CuotaGenerada $registro): bool
    {
        $hoy = Carbon::today();
        $venc1 = self::parseFecha($registro->venc1);
        $venc2 = self::parseFecha($registro->venc2);
        $nueVenc = self::parseFecha($registro->nueVenc);

        if ($venc1 === null || $venc2 === null) {
            return false;
        }

        if ($hoy->lte($venc1) || $hoy->lte($venc2)) {
            return false;
        }

        if ($nueVenc === null) {
            return true;
        }

        return $hoy->gt($nueVenc);
    }

    public static function mensajeCuotaVencidaReimpresion(): string
    {
        return 'Esta cuota está vencida y no puede reimprimirse. '
            .'Comuníquese con administración para obtener un cupón actualizado.';
    }

    /**
     * Facturas AFIP vigentes por cuota — portal familia (pendientes e historial).
     * Solo incluye la factura activa; si fue anulada por nota de crédito, no figura.
     *
     * @param  Collection<int, CuotaGenerada>  $cuotas
     * @return array<int, ComprobanteAfip> idCuotaGenerada => factura
     */
    public static function facturasAfipVigentes(Collection $cuotas): array
    {
        if (! ComprobantesAfipCuotaService::moduloDisponible() || $cuotas->isEmpty()) {
            return [];
        }

        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return [];
        }

        $idLegajo = (int) $ctx->idLegajo;
        $idsCuotas = $cuotas
            ->filter(fn (CuotaGenerada $cuota): bool => (int) ($cuota->idLegajos ?? 0) === $idLegajo)
            ->map(fn (CuotaGenerada $cuota) => (int) $cuota->id)
            ->values()
            ->all();

        return ComprobantesAfipCuotaService::facturasVigentesPorCuotasGeneradas($idsCuotas);
    }

    /**
     * @return array<int, string|\Closure>
     */
    private static function relacionesCuotaListado(): array
    {
        return [
            'legajo:id,apellido,nombre,dni',
            'terlec:id,ano',
            'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
            'curso.curplan:id,curPlanCurso',
            'curso.turnoClase:id,nombre',
            'curso.nivel:id,nivel',
            'cuota:id,nombre,orden',
        ];
    }

    private static function parseFecha(mixed $fecha): ?CarbonInterface
    {
        if (self::esFechaVacia($fecha)) {
            return null;
        }

        if ($fecha instanceof CarbonInterface) {
            return $fecha->copy()->startOfDay();
        }

        $raw = trim((string) ($fecha ?? ''));

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
