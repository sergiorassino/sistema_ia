<?php

namespace App\Support\Cuotas;

use App\Models\ComprobanteAfip;
use App\Models\CuotaGenerada;
use App\Models\CuotaPago;
use App\Models\CuotasBeca;
use App\Models\Ento;
use App\Support\Afip\AfipComprobanteQrUrl;
use App\Support\Afip\AfipCondicionIvaReceptor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Arma los datos del PDF de comprobante AFIP a partir de un pago imputado.
 */
final class ComprobanteAfipDatos
{
    /**
     * @return array<string, mixed>|null
     */
    public static function paraComprobanteRegistro(int $idComprobanteAfip, int $idLegajo): ?array
    {
        $comprobante = ComprobanteAfip::query()->find($idComprobanteAfip);
        if ($comprobante === null || ! self::comprobantePerteneceAlLegajo($comprobante, $idLegajo)) {
            return null;
        }

        return self::datosDesdeModelo($comprobante);
    }

    public static function comprobantePerteneceAlLegajo(ComprobanteAfip $comprobante, int $idLegajo): bool
    {
        $idCuotaAsoc = (int) ($comprobante->idCbteAsoc ?? 0);
        if ($idCuotaAsoc > 0 && GestionAranceles::cuotaDelLegajo($idCuotaAsoc, $idLegajo) !== null) {
            return true;
        }

        foreach (self::idsPagosVinculados($comprobante) as $idPago) {
            $pago = CuotaPago::query()->find($idPago);
            if ($pago === null) {
                continue;
            }

            $idCuotaGenerada = (int) ($pago->idCuotasGeneradas ?? 0);
            if ($idCuotaGenerada > 0 && GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo) !== null) {
                return true;
            }
        }

        $legajo = GestionAranceles::legajoParaGestion($idLegajo);
        if ($legajo !== null) {
            $dniComprobante = preg_replace('/\D/', '', (string) ($comprobante->dni ?? ''));
            $dniLegajo = preg_replace('/\D/', '', (string) ($legajo->dni ?? ''));
            if ($dniComprobante !== '' && $dniLegajo !== '' && $dniComprobante === $dniLegajo) {
                return true;
            }
        }

        return false;
    }

    /**
     * PDF comprobante AFIP — portal familia (validación alineada al listado de cuotas).
     *
     * @return array<string, mixed>|null
     */
    public static function paraAutogestion(int $idComprobanteAfip, int $idCuotaGenerada): ?array
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $idLegajo = (int) $ctx->idLegajo;
        if (! ComprobantesAfipCuotaService::comprobanteVinculadoACuotaDelEstudiante(
            $idComprobanteAfip,
            $idCuotaGenerada,
            $idLegajo,
        )) {
            return null;
        }

        // Solo la factura vigente: si hay NC que la anuló, no se descarga.
        $facturaVigente = ComprobantesAfipCuotaService::facturaVigentePorCuotaGenerada($idCuotaGenerada);
        if ($facturaVigente === null
            || (int) $facturaVigente->idComprobanteAfip !== $idComprobanteAfip) {
            return null;
        }

        return self::datosDesdeModelo($facturaVigente);
    }

    /**
     * @return list<int>
     */
    private static function idsPagosVinculados(ComprobanteAfip $comprobante): array
    {
        $ids = [];
        $principal = (int) ($comprobante->idCuotasPagos ?? 0);
        if ($principal > 0) {
            $ids[] = $principal;
        }

        $saldo = trim((string) ($comprobante->saldoRestante ?? ''));
        if ($saldo !== '') {
            foreach (explode(',', $saldo) as $parte) {
                $id = (int) trim($parte);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function paraPago(CuotaPago $pago, int $idLegajo): ?array
    {
        $idCuotaGenerada = (int) ($pago->idCuotasGeneradas ?? 0);
        if ($idCuotaGenerada <= 0 || GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo) === null) {
            return null;
        }

        $comprobante = ComprobanteAfip::query()
            ->vinculadoAPago((int) $pago->id)
            ->orderByDesc('idComprobanteAfip')
            ->first();

        if ($comprobante === null) {
            return null;
        }

        return self::datosDesdeModelo($comprobante);
    }

    /**
     * @return array<string, mixed>
     */
    private static function datosDesdeModelo(ComprobanteAfip $comprobante): array
    {
        $config = tenantCuotasFacturacionAfipConfig();
        $docTipo = (int) ($comprobante->docTipoAfip ?? 0);
        if ($docTipo <= 0) {
            $docTipo = (int) ($config['doc_tipo'] ?? 96);
        }
        $tipoComprobante = (int) ($comprobante->tipoComprobante ?? 15);
        $ptoVta = (int) ($comprobante->puntoVenta ?? 0);
        $nroRecibo = (int) ($comprobante->nroRecibo ?? 0);
        $importe = round((float) ($comprobante->importePagado ?? 0), 2);
        $fechaEmision = self::fechaEncabezado((string) ($comprobante->fechaEmision ?? ''));
        $fechaYmd = self::fechaABarrraAYmd($fechaEmision);
        $fechaQr = self::fechaParaQr($fechaEmision);
        $vtoCae = self::fechaEncabezado((string) ($comprobante->vtoCae ?? ''));
        $condicionIvaId = AfipCondicionIvaReceptor::idDesdeEtiqueta(
            (string) ($comprobante->condicionIvaAlumno ?? ''),
            (int) ($config['condicion_iva_receptor_id'] ?? 5),
        );

        $nombreInstitucion = trim((string) ($comprobante->nombreInstitucion ?? ''));
        $condicionIvaInstitucion = trim((string) ($comprobante->condicionIvaInstitucion ?? ''));
        $telefonoInstitucion = trim((string) ($comprobante->telefonoInstitucion ?? ''));
        $aporteEstatal = trim((string) ($comprobante->aporteEstatal ?? ''));
        $cursoTexto = mb_strtoupper(trim((string) ($comprobante->cursoAlumno ?? '')));
        $registroCuota = self::registroCuotaAsociado($comprobante);

        if ($nombreInstitucion === '' || $condicionIvaInstitucion === '' || $telefonoInstitucion === '' || $aporteEstatal === '' || $cursoTexto === '') {
            $ento = self::entoParaComprobantePdf();
            if ($nombreInstitucion === '') {
                $nombreInstitucion = trim((string) ($ento?->insti ?? ''));
            }
            if ($condicionIvaInstitucion === '') {
                $condicionIvaInstitucion = self::condIvaInstDesdeEnto($ento);
            }
            if ($telefonoInstitucion === '') {
                $telefonoInstitucion = trim((string) ($ento?->telefono ?? ''));
            }
            if ($aporteEstatal === '') {
                $aporteEstatal = self::aporteEstatalDesdeEnto($ento);
            }
            if ($cursoTexto === '') {
                $cursoTexto = mb_strtoupper(trim((string) ($registroCuota?->curso?->nombreParaListado() ?? '')));
            }
        }

        // En el PDF, la leyenda de beca se imprime debajo del concepto facturado (si aplica).
        $becaPorcentaje = null;
        $becaImporteOriginalFmt = null;
        $idBeca = (int) ($registroCuota?->idCuotasbecas ?? 0);
        if ($registroCuota !== null && $idBeca > 1) {
            $porcentaje = null;
            if ($registroCuota->relationLoaded('beca') && $registroCuota->beca !== null) {
                $porcentaje = (float) ($registroCuota->beca->porcentaje ?? 0);
            } else {
                $porcentaje = CuotasBeca::query()->whereKey($idBeca)->value('porcentaje');
                if ($porcentaje !== null) {
                    $porcentaje = (float) $porcentaje;
                }
            }

            $porcentaje = $porcentaje !== null ? max(0.0, min(100.0, $porcentaje)) : null;
            $importeNeto = round((float) ($registroCuota->importe ?? 0), 2);
            if ($porcentaje !== null && $porcentaje > 0 && $importeNeto > 0) {
                $factor = 1.0 - ($porcentaje / 100.0);
                $importeOriginal = $factor > 0.0 ? round($importeNeto / $factor, 2) : $importeNeto;
                $becaPorcentaje = (int) round($porcentaje);
                $becaImporteOriginalFmt = number_format($importeOriginal, 2, ',', '.');
            }
        }

        $nombreResp = trim((string) ($comprobante->nombreResp ?? ''));
        $dniResp = trim((string) ($comprobante->dniResp ?? ''));
        if ($dniResp !== '') {
            $dniResp = CuotasFormato::formatearDni($dniResp);
        }

        // En el PDF, el DNI junto a la cuota es el del alumno; el receptor AFIP es dniResp.
        $dniAlumno = '';
        if ($registroCuota !== null) {
            if (! $registroCuota->relationLoaded('legajo')) {
                $registroCuota->load(['legajo:id,dni']);
            }
            $dniAlumno = trim((string) ($registroCuota->legajo?->dni ?? ''));
            if ($dniAlumno !== '') {
                $dniAlumno = CuotasFormato::formatearDni($dniAlumno);
            }
        }

        $urlQr = AfipComprobanteQrUrl::generar([
            'fecha_yyyy_mm_dd' => $fechaQr,
            'cuit' => (string) ($comprobante->cuitInstitucion ?? ''),
            'pto_vta' => $ptoVta,
            'tipo_cmp' => $tipoComprobante,
            'nro_cmp' => $nroRecibo,
            'importe' => $importe,
            'doc_tipo' => $docTipo,
            'doc_nro' => (string) ($comprobante->dni ?? ''),
            'cae' => (string) ($comprobante->cae ?? ''),
        ]);

        return [
            'nombreInstitucion' => $nombreInstitucion,
            'razonSocial' => trim((string) ($comprobante->razonSocial ?? '')),
            'telefonoInstitucion' => $telefonoInstitucion,
            'tipoComprobante' => $tipoComprobante,
            'puntoVenta' => $ptoVta,
            'nroComprobante' => $nroRecibo,
            'numeroComprobanteTexto' => ($ptoVta > 0 && $nroRecibo > 0)
                ? str_pad((string) $ptoVta, 5, '0', STR_PAD_LEFT)
                    .'-'
                    .str_pad((string) $nroRecibo, 8, '0', STR_PAD_LEFT)
                : '',
            'puntoVentaTexto' => $ptoVta > 0
                ? str_pad((string) $ptoVta, 5, '0', STR_PAD_LEFT)
                : '',
            'numeroComprobanteSolo' => $nroRecibo > 0
                ? str_pad((string) $nroRecibo, 8, '0', STR_PAD_LEFT)
                : '',
            'cuitInstitucion' => trim((string) ($comprobante->cuitInstitucion ?? '')),
            'domicilioComercial' => trim((string) ($comprobante->domicilioComercial ?? '')),
            'ingresosBrutos' => trim((string) ($comprobante->ingresosBrutos ?? '')),
            'fechaInicioActividades' => self::fechaEncabezado((string) ($comprobante->fechaInicioActividades ?? '')),
            'condicionIvaInstitucion' => $condicionIvaInstitucion,
            'aporteEstatal' => $aporteEstatal,
            'fechaEmision' => $fechaEmision,
            'docNro' => $dniAlumno,
            'docTipo' => $docTipo,
            'nombreCliente' => trim((string) ($comprobante->nombreAlumno ?? '')),
            'nombreResp' => $nombreResp,
            'dniResp' => $dniResp,
            'cursoTexto' => $cursoTexto,
            'cuotaTexto' => self::textoCuotasDesdeComprobante($comprobante),
            'condicionIvaReceptorId' => $condicionIvaId,
            'condicionIvaReceptorTexto' => trim((string) ($comprobante->condicionIvaAlumno ?? '')),
            'muestraCondicionVenta' => trim((string) ($comprobante->condicionVenta ?? '')) !== '',
            'condicionVenta' => self::etiquetaCondicionVenta((string) ($comprobante->condicionVenta ?? '')),
            'concepto' => trim((string) ($comprobante->concepto ?? '')),
            'importe' => $importe,
            'importeFmt' => number_format($importe, 2, ',', '.'),
            'lineas' => self::lineasDesdeComprobante($comprobante),
            'becaPorcentaje' => $becaPorcentaje,
            'becaImporteOriginalFmt' => $becaImporteOriginalFmt,
            'cae' => trim((string) ($comprobante->cae ?? '')),
            'vtoCae' => $vtoCae,
            'urlQr' => $urlQr,
            'fechaYmd' => $fechaYmd,
        ];
    }

    /**
     * @return list<array{concepto: string, importe: float, importeFmt: string}>
     */
    private static function lineasDesdeComprobante(ComprobanteAfip $comprobante): array
    {
        $subs = trim((string) ($comprobante->subConceptos ?? ''));
        $imps = trim((string) ($comprobante->importeSubConceptos ?? ''));

        if ($subs === '' || $imps === '') {
            $importe = round((float) ($comprobante->importePagado ?? 0), 2);

            return [[
                'concepto' => trim((string) ($comprobante->concepto ?? '')),
                'importe' => $importe,
                'importeFmt' => number_format($importe, 2, ',', '.'),
            ]];
        }

        $nombres = explode('|', $subs);
        $importes = explode('|', $imps);
        $lineas = [];

        foreach ($nombres as $idx => $nombre) {
            $concepto = trim((string) $nombre);
            if ($concepto === '') {
                continue;
            }

            $rawImporte = trim((string) ($importes[$idx] ?? '0'));
            $importe = round((float) str_replace(',', '.', $rawImporte), 2);

            $lineas[] = [
                'concepto' => $concepto,
                'importe' => $importe,
                'importeFmt' => number_format($importe, 2, ',', '.'),
            ];
        }

        if ($lineas === []) {
            $importe = round((float) ($comprobante->importePagado ?? 0), 2);

            return [[
                'concepto' => trim((string) ($comprobante->concepto ?? '')),
                'importe' => $importe,
                'importeFmt' => number_format($importe, 2, ',', '.'),
            ]];
        }

        return $lineas;
    }

    private static function registroCuotaAsociado(ComprobanteAfip $comprobante): ?CuotaGenerada
    {
        $id = (int) ($comprobante->idCbteAsoc ?? 0);
        if ($id <= 0) {
            return null;
        }

        return CuotaGenerada::query()
            ->with([
                'cuota:id,nombre',
                'legajo:id,dni',
                'curso:Id,cursec,c,s,idCurPlan,idTurnoClase',
                'curso.curplan:id,curPlanCurso',
                'curso.turnoClase:id,nombre',
            ])
            ->find($id);
    }

    private static function idNivelParaEnto(): int
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            $idNivel = (int) (studentCtx()->idNivel ?? 0);
        }

        return $idNivel;
    }

    /** @return list<string> */
    private static function columnasEntoParaPdf(): array
    {
        $columnas = ['insti', 'telefono'];
        if (Schema::hasColumn('ento', 'condIvaInst')) {
            $columnas[] = 'condIvaInst';
        }
        if (Schema::hasColumn('ento', 'aporteEstatal')) {
            $columnas[] = 'aporteEstatal';
        }

        return $columnas;
    }

    private static function entoParaComprobantePdf(): ?Ento
    {
        $idNivel = self::idNivelParaEnto();
        if ($idNivel <= 0) {
            return null;
        }

        return Ento::query()
            ->where('idNivel', $idNivel)
            ->first(self::columnasEntoParaPdf());
    }

    public static function condIvaInstDesdeEnto(?Ento $ento): string
    {
        if ($ento === null || ! Schema::hasColumn('ento', 'condIvaInst')) {
            return '';
        }

        return trim((string) ($ento->condIvaInst ?? ''));
    }

    public static function aporteEstatalDesdeEnto(?Ento $ento): string
    {
        if ($ento === null || ! Schema::hasColumn('ento', 'aporteEstatal')) {
            return '';
        }

        return trim((string) ($ento->aporteEstatal ?? ''));
    }

    /**
     * Fecha del encabezado: solo formatea lo cargado; sin fecha de hoy ni valores por defecto.
     */
    private static function fechaEncabezado(string $valor): string
    {
        $raw = trim($valor);
        if ($raw === '') {
            return '';
        }

        if (str_contains($raw, '/')) {
            $partes = explode('/', $raw);
            if (count($partes) === 3 && strlen($partes[2]) === 4) {
                return sprintf('%02d/%02d/%04d', (int) $partes[0], (int) $partes[1], (int) $partes[2]);
            }

            if (count($partes) === 3 && strlen($partes[0]) === 4) {
                return sprintf('%02d/%02d/%04d', (int) $partes[2], (int) $partes[1], (int) $partes[0]);
            }

            return $raw;
        }

        try {
            return Carbon::parse($raw)->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    /** Fecha ISO para QR AFIP; solo uso técnico del pie, no del encabezado impreso. */
    private static function fechaParaQr(string $fechaEncabezado): string
    {
        $fechaEncabezado = trim($fechaEncabezado);
        if ($fechaEncabezado === '') {
            return Carbon::today()->format('Y-m-d');
        }

        return self::fechaABarraAIso($fechaEncabezado);
    }

    private static function textoCuotasDesdeComprobante(ComprobanteAfip $comprobante): string
    {
        $subs = trim((string) ($comprobante->subConceptos ?? ''));
        if ($subs !== '' && ! ctype_digit(str_replace('|', '', $subs))) {
            return str_replace('|', ', ', $subs);
        }

        return mb_strtoupper(trim((string) ($comprobante->concepto ?? '')));
    }

    private static function etiquetaCondicionVenta(string $valor): string
    {
        $n = mb_strtolower(trim($valor));

        return match ($n) {
            'contado' => 'Contado',
            'cuenta corriente', 'cuenta_corriente' => 'Cuenta Corriente',
            default => $valor !== '' ? mb_convert_case($valor, MB_CASE_TITLE, 'UTF-8') : 'Contado',
        };
    }

    private static function fechaABarrraAYmd(string $fechaBarra): string
    {
        $partes = explode('/', $fechaBarra);
        if (count($partes) !== 3) {
            return '';
        }

        return sprintf('%04d%02d%02d', (int) $partes[2], (int) $partes[1], (int) $partes[0]);
    }

    private static function fechaABarraAIso(string $fechaBarra): string
    {
        $partes = explode('/', $fechaBarra);
        if (count($partes) !== 3) {
            return '';
        }

        return sprintf('%04d-%02d-%02d', (int) $partes[2], (int) $partes[1], (int) $partes[0]);
    }
}
