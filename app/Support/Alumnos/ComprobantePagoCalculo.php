<?php

namespace App\Support\Alumnos;

use App\Models\CuotaGenerada;
use App\Models\CuotasBeca;
use App\Models\CuotasImporte;
use App\Models\Ento;
use App\Support\Cuotas\ImputacionPagoCalculo;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionIdentUsuario448Nuevo;
use App\Support\Cuotas\Siro\SiroCodigoPagoElectronico;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Cálculo de importes, vencimientos y códigos del cupón (legacy FPDF imprimir()).
 */
final class ComprobantePagoCalculo
{
    /** Nivel administración en `ento` (legacy: idNivel = 5). */
    private const ID_NIVEL_ADMINISTRACION = 5;

    /**
     * @return array<string, mixed>|null
     */
    public static function paraCuotaGenerada(CuotaGenerada $registro, ?array $pdfHeader = null): ?array
    {
        $legajo = $registro->legajo;
        $curso = $registro->curso;
        $cuota = $registro->cuota;

        if ($legajo === null || $curso === null || $cuota === null) {
            return null;
        }

        $idLegajos = (int) $registro->idLegajos;
        $idCuotas = (int) $registro->idCuotas;
        $idCursos = (int) $registro->idCursos;
        $idNivel = (int) ($curso->idNivel ?? 0);
        $faltapa = (float) ($registro->faltapa ?? 0);

        if ($faltapa <= 0) {
            return null;
        }

        $venc1 = self::carbon($registro->venc1);
        $venc2 = self::carbon($registro->venc2);
        $venc3 = self::carbon($registro->venc3);
        $nueVenc = self::carbon($registro->nueVenc);

        $entoAdmin = Ento::query()
            ->where('idNivel', self::ID_NIVEL_ADMINISTRACION)
            ->first();

        $entoNivel = Ento::query()
            ->where('idNivel', $idNivel)
            ->first();

        $attrsEnto = $entoAdmin?->getAttributes() ?? [];
        $attrsEntoNivel = $entoNivel?->getAttributes() ?? [];
        $siroHabilitado = tenantCuotasSiroHabilitado();
        $cuentaSiroNivel = SiroCodigoPagoElectronico::cuentaRecaudadoraPorNivel($idNivel);

        $importes = CuotasImporte::query()
            ->where('idCuotas', $idCuotas)
            ->where('idCursos', $idCursos)
            ->first();

        $formula = self::formulaDesdeRegistro($importes);

        $fechaDeHoy = Carbon::today();
        $leyendaBonificada = '';
        $identConcepto = '1';

        [$importeVenc1, $identConcepto, $leyendaBonificada] = self::importePrimerVencimiento(
            $faltapa,
            $formula,
            $identConcepto,
            $leyendaBonificada,
        );

        [$importeVenc2, $identConcepto] = self::importeSegundoVencimiento(
            $faltapa,
            $formula,
            $identConcepto,
            $venc1,
            $venc2,
        );

        [$importeVenc3, $identConcepto] = self::importeTercerVencimiento(
            $faltapa,
            $formula,
            $identConcepto,
            $venc1,
            $venc3,
        );

        $cuponVencido = $venc3 !== null && $fechaDeHoy->gt($venc3);
        $nuevoImporte = null;
        $nuevoVencEsp = null;

        if ($cuponVencido) {
            $identConcepto = '4';
            $nuevoVenc = $nueVenc ?? $venc3;
            $nuevoVencEsp = $nuevoVenc?->format('d/m/Y');
            $diasRecargo4 = self::diasEntre($nuevoVenc, $venc1);

            $nuevoImporte = $faltapa + self::interesRecargo(
                $faltapa,
                (float) $formula['valor4'],
                $formula['porcan4'],
                $venc1,
                $fechaDeHoy,
                $diasRecargo4,
            );

            $importeVenc3 = $nuevoImporte;
            $venc3 = $nuevoVenc;
        }

        if ($siroHabilitado) {
            $ultUploadBarra = max(1, (int) ($registro->ultUpload ?? 0));
            if ($cuponVencido) {
                $barraPartes = self::partesCodigoBarrasVencido(
                    $idLegajos,
                    $idCuotas,
                    $ultUploadBarra,
                    $nuevoVenc ?? $venc3,
                    (float) $nuevoImporte,
                    $cuentaSiroNivel,
                );
            } else {
                $barraPartes = self::partesCodigoBarrasVigente(
                    $idLegajos,
                    $idCuotas,
                    $ultUploadBarra,
                    $venc1,
                    $importeVenc1,
                    $venc2,
                    $importeVenc2,
                    $venc3,
                    $importeVenc3,
                    $cuentaSiroNivel,
                );
            }

            $numeroCuenta = $barraPartes['numeroCuenta'];
            $barra = ComprobantePagoCodigoBarras::armar($barraPartes);
            $codigoPagoElectronico = SiroCodigoPagoElectronico::generar($idLegajos, $idNivel);
        } else {
            $numeroCuenta = '';
            $barra = '';
            $codigoPagoElectronico = '';
        }

        $idCuotasbecas = (int) ($registro->idCuotasbecas ?? 0);
        $leyendaBeca = '';
        if ($idCuotasbecas > 1) {
            $porcentaje = CuotasBeca::query()->where('id', $idCuotasbecas)->value('porcentaje');
            if ($porcentaje !== null) {
                $leyendaBeca = 'Cuota con '.trim((string) $porcentaje).' % de Ayuda Familiar';
            }
        }

        $ultUpload = (int) ($registro->ultUpload ?? 0);
        $nroComprobante = (int) ($registro->nroComp ?? 0);
        $identUsuarioPuro = str_pad((string) $idLegajos, 5, '0', STR_PAD_LEFT)
            .str_pad((string) $idCuotas, 3, '0', STR_PAD_LEFT);

        $qrConcepto = ($venc1 !== null && $fechaDeHoy->lte($venc1)) ? 3 : 1;
        $nroCliente = SiroCodigoPagoElectronico::bloqueLegajoNueveDigitos($idLegajos, $idNivel);
        $nroComprobanteQr = str_pad((string) $idCuotas, 11, '0', STR_PAD_LEFT)
            .$qrConcepto
            .str_pad((string) $idCuotas, 3, '0', STR_PAD_LEFT)
            .str_pad((string) $ultUpload, 2, '0', STR_PAD_LEFT)
            .str_pad((string) $idCuotas, 3, '0', STR_PAD_LEFT);

        $cadenaQr = $siroHabilitado
            ? ComprobantePagoSiroQr::obtenerCadena(
                $nroCliente.$numeroCuenta,
                $nroComprobanteQr,
            )
            : '';

        $cuit = self::formatearCuit(trim((string) ($attrsEnto['cuit'] ?? '')));

        return [
            'entoAdmin' => [
                'insti' => trim((string) ($attrsEnto['insti'] ?? config('tenant.nombre', ''))),
                'direccion' => trim((string) ($attrsEnto['direccion'] ?? '')),
                'localidad' => trim((string) ($attrsEnto['localidad'] ?? '')),
                'departamento' => trim((string) ($attrsEnto['departamento'] ?? '')),
                'cuit' => $cuit,
            ],
            'entoNivel' => [
                'siroMje' => trim((string) ($attrsEntoNivel['siroMje'] ?? '')),
                'insti' => trim((string) ($attrsEntoNivel['insti'] ?? '')),
            ],
            'nroComprobante' => $nroComprobante,
            'nroComprobanteTexto' => '00001-'.str_pad((string) $nroComprobante, 8, '0', STR_PAD_LEFT),
            'apellido' => mb_strtoupper(trim((string) ($legajo->apellido ?? ''))),
            'nombre' => mb_strtoupper(trim((string) ($legajo->nombre ?? ''))),
            'apellidoNombre' => mb_strtoupper(trim(
                trim((string) ($legajo->apellido ?? '')).' '.trim((string) ($legajo->nombre ?? '')),
            )),
            'cursec' => mb_strtoupper(trim((string) ($curso->cursec ?? $curso->nombreParaListado()))),
            'nivel' => mb_strtoupper(trim((string) ($curso->nivel?->nivel ?? ''))),
            'cuotaNombre' => mb_strtoupper(trim((string) ($cuota->nombre ?? ''))),
            'leyendaBeca' => $leyendaBeca,
            'leyendaBonificada' => $leyendaBonificada,
            'importeVenc1' => $importeVenc1,
            'importeVenc2' => $importeVenc2,
            'importeVenc3' => $importeVenc3,
            'importeVenc1Fmt' => ArancelesEscolares::formatearImporte($importeVenc1),
            'importeVenc2Fmt' => ArancelesEscolares::formatearImporte($importeVenc2),
            'importeVenc3Fmt' => ArancelesEscolares::formatearImporte($importeVenc3),
            'venc1Esp' => $venc1?->format('d/m/Y') ?? '',
            'venc2Esp' => $venc2?->format('d/m/Y') ?? '',
            'venc3Esp' => $venc3?->format('d/m/Y') ?? '',
            'cuponVencido' => $cuponVencido,
            'nuevoVencEsp' => $nuevoVencEsp,
            'nuevoImporteFmt' => $nuevoImporte !== null
                ? ArancelesEscolares::formatearImporte($nuevoImporte)
                : '',
            'codigoPagoElectronico' => $codigoPagoElectronico,
            'barra' => $barra,
            'cadenaQr' => $cadenaQr,
            'fechaImpresion' => Carbon::now()->format('d-m-y h:i'),
            'pdfHeader' => $pdfHeader ?? studentPdfHeaderData(),
        ];
    }

    public static function codigoPagoElectronico(int $idLegajos, int $idNivel): string
    {
        if (! tenantCuotasSiroHabilitado()) {
            return '';
        }

        return SiroCodigoPagoElectronico::generar($idLegajos, $idNivel);
    }

    /**
     * @return array{
     *     signo1: string, valor1: float, porcan1: string,
     *     signo2: string, valor2: float, porcan2: string,
     *     signo3: string, valor3: float, porcan3: string,
     *     signo4: string, valor4: float, porcan4: string
     * }
     */
    private static function formulaDesdeRegistro(?CuotasImporte $importes): array
    {
        return [
            'signo1' => trim((string) ($importes->signo1v ?? '+')),
            'valor1' => (float) ($importes->valor1v ?? 0),
            'porcan1' => trim((string) ($importes->porcan1v ?? '%')),
            'signo2' => trim((string) ($importes->signo2v ?? '+')),
            'valor2' => (float) ($importes->valor2v ?? 0),
            'porcan2' => trim((string) ($importes->porcan2v ?? '%')),
            'signo3' => trim((string) ($importes->signo3v ?? '+')),
            'valor3' => (float) ($importes->valor3v ?? 0),
            'porcan3' => trim((string) ($importes->porcan3v ?? '%')),
            'signo4' => trim((string) ($importes->signo4v ?? '+')),
            'valor4' => (float) ($importes->valor4v ?? 0),
            'porcan4' => trim((string) ($importes->porcan4v ?? '%')),
        ];
    }

    /**
     * @param  array<string, mixed>  $formula
     * @return array{0: float, 1: string, 2: string}
     */
    private static function importePrimerVencimiento(
        float $faltapa,
        array $formula,
        string $identConcepto,
        string $leyendaBonificada,
    ): array {
        if ($formula['signo1'] === '+') {
            $identConcepto = '1';

            return [$faltapa, $identConcepto, $leyendaBonificada];
        }

        $identConcepto = '3';
        $tmpBonificacion = $formula['porcan1'] === '%'
            ? ($faltapa * $formula['valor1']) / 100
            : $formula['valor1'];
        if ($formula['valor1'] > 0) {
            $leyendaBonificada = '(Bonificado)';
        }

        return [$faltapa - $tmpBonificacion, $identConcepto, $leyendaBonificada];
    }

    /**
     * @param  array<string, mixed>  $formula
     * @return array{0: float, 1: string}
     */
    private static function importeSegundoVencimiento(
        float $faltapa,
        array $formula,
        string $identConcepto,
        ?CarbonInterface $venc1,
        ?CarbonInterface $venc2,
    ): array {
        if ($formula['signo2'] === '+') {
            $identConcepto = '1';
            $dias = self::diasEntre($venc2, $venc1);
            $interes = self::interesRecargo(
                $faltapa,
                (float) $formula['valor2'],
                $formula['porcan2'],
                $venc1,
                $venc2 ?? Carbon::today(),
                $dias,
            );

            return [$faltapa + $interes, $identConcepto];
        }

        $identConcepto = '3';
        $tmpBonificacion = $formula['porcan2'] === '%'
            ? ($faltapa * $formula['valor2']) / 100
            : $formula['valor2'];

        return [$faltapa - $tmpBonificacion, $identConcepto];
    }

    /**
     * @param  array<string, mixed>  $formula
     * @return array{0: float, 1: string}
     */
    private static function importeTercerVencimiento(
        float $faltapa,
        array $formula,
        string $identConcepto,
        ?CarbonInterface $venc1,
        ?CarbonInterface $venc3,
    ): array {
        if ($formula['signo3'] === '+') {
            $identConcepto = '1';
            $dias = self::diasEntre($venc3, $venc1);
            $interes = self::interesRecargo(
                $faltapa,
                (float) $formula['valor3'],
                $formula['porcan3'],
                $venc1,
                $venc3 ?? Carbon::today(),
                $dias,
            );

            return [$faltapa + $interes, $identConcepto];
        }

        $identConcepto = '3';
        $tmpBonificacion = $formula['porcan3'] === '%'
            ? ($faltapa * $formula['valor3']) / 100
            : $formula['valor3'];

        return [$faltapa - $tmpBonificacion, $identConcepto];
    }

    /**
     * @return array<string, string>
     */
    private static function partesCodigoBarrasVigente(
        int $idLegajos,
        int $idCuotas,
        int $ultUpload,
        ?CarbonInterface $venc1,
        float $importeVenc1,
        ?CarbonInterface $venc2,
        float $importeVenc2,
        ?CarbonInterface $venc3,
        float $importeVenc3,
        string $cuentaSiroNivel,
    ): array {
        $identUsuario = SiroDescargaRendicionIdentUsuario448Nuevo::armar($idCuotas, $idLegajos, $ultUpload);

        return [
            'empresaServicio' => '0448',
            'identUsuario' => $identUsuario,
            'fecha1erVenc' => ComprobantePagoCodigoBarras::fechaCodigo($venc1),
            'importe1erVenc' => ComprobantePagoCodigoBarras::importeCodigo($importeVenc1),
            'dias2doVenc' => str_pad((string) self::diasEntre($venc2, $venc1), 2, '0', STR_PAD_LEFT),
            'importe2doVenc' => ComprobantePagoCodigoBarras::importeCodigo($importeVenc2),
            'numeroCuenta' => $cuentaSiroNivel,
            'importe3erVenc' => ComprobantePagoCodigoBarras::importeCodigo($importeVenc3),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function partesCodigoBarrasVencido(
        int $idLegajos,
        int $idCuotas,
        int $ultUpload,
        ?CarbonInterface $nuevoVenc,
        float $nuevoImporte,
        string $cuentaSiroNivel,
    ): array {
        $identUsuario = SiroDescargaRendicionIdentUsuario448Nuevo::armar($idCuotas, $idLegajos, $ultUpload);
        $importe = ComprobantePagoCodigoBarras::importeCodigo($nuevoImporte);

        return [
            'empresaServicio' => '0448',
            'identUsuario' => $identUsuario,
            'fecha1erVenc' => ComprobantePagoCodigoBarras::fechaCodigo($nuevoVenc),
            'importe1erVenc' => $importe,
            'dias2doVenc' => '00',
            'importe2doVenc' => $importe,
            'numeroCuenta' => $cuentaSiroNivel,
            'importe3erVenc' => $importe,
        ];
    }

    private static function diasEntre(?CarbonInterface $fechaMayor, ?CarbonInterface $fechaMenor): int
    {
        if ($fechaMayor === null || $fechaMenor === null) {
            return 0;
        }

        return max(0, $fechaMenor->diffInDays($fechaMayor, false));
    }

    /**
     * Recargo según tipo porcan ($, %, m, p).
     */
    private static function interesRecargo(
        float $faltapa,
        float $valor,
        string $porcan,
        ?CarbonInterface $venc1,
        CarbonInterface $fechaReferencia,
        int $dias,
    ): float {
        if ($porcan === '%') {
            return self::interesRecargoPorcent($faltapa, $valor, $dias);
        }

        if ($porcan === 'm') {
            $meses = ImputacionPagoCalculo::mesesMoraAcumuladaDesdeVenc1($venc1, $fechaReferencia);

            return $valor * $meses;
        }

        if ($porcan === 'p') {
            $meses = ImputacionPagoCalculo::mesesMoraAcumuladaDesdeVenc1($venc1, $fechaReferencia);

            return (($faltapa * $valor) / 100) * $meses;
        }

        return $valor;
    }

    /**
     * Recargo porcentual sobre saldo; en modo diario se multiplica por días de mora.
     */
    private static function interesRecargoPorcent(float $faltapa, float $valorPorcent, int $dias): float
    {
        $base = ($faltapa * $valorPorcent) / 100;

        return tenantCuotasInteresMoraEsDiario() ? $base * $dias : $base;
    }

    private static function carbon(mixed $fecha): ?CarbonInterface
    {
        if ($fecha instanceof CarbonInterface) {
            return $fecha;
        }

        $raw = trim((string) ($fecha ?? ''));
        if ($raw === '' || $raw === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function formatearCuit(string $cuit): string
    {
        $digits = preg_replace('/\D+/', '', $cuit) ?? '';
        if (strlen($digits) < 11) {
            return $cuit;
        }

        return substr($digits, 0, 2).'-'.substr($digits, 2, 8).'-'.substr($digits, 10, 1);
    }
}
