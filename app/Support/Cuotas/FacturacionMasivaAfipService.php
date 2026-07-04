<?php

namespace App\Support\Cuotas;

use App\Models\ComprobanteAfip;
use App\Models\CuotaGenerada;
use App\Models\Ento;
use App\Models\Legajo;
use App\Support\Cuotas\ConsultaAfipComprobanteService;
use App\Support\Afip\AfipCodigoBarras;
use App\Support\Afip\AfipCondicionIvaReceptor;
use App\Support\Afip\AfipWsfeEmision;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Facturación masiva AFIP por devengamiento (manual, por lote).
 */
final class FacturacionMasivaAfipService
{
    public const ESTADO_FACTURADO = 'Facturado';

    public const ESTADO_NOTA_CREDITO = 'Nota de crédito emitida';

    public const ESTADO_OMITIDO = 'Omitido';

    private const TAMANO_LOTE_AFIP = 50;

    /**
     * @param  list<int>  $cursoIds
     * @param  list<int>  $idCuotasPlantilla
     * @param  list<int>  $idLegajos  Legajos elegidos individualmente (se combinan con los de curso).
     * @return array{
     *     porCurso: array<int, array{cursoNombre: string, alumnos: list<array{idLegajo: int, etiqueta: string, estado: string, puedeFacturar: bool, importe: float, conceptos: string}>}>,
     *     total: int,
     *     totalAlumnos: int,
     *     cuotasNombre: string
     * }
     */
    public static function vistaPrevia(
        array $cursoIds,
        array $idCuotasPlantilla,
        array $idLegajos = [],
        string $tipoOperacion = ConsultaAfipComprobanteService::TIPO_FACTURA,
    ): array {
        $grupos = self::esNotaCredito($tipoOperacion)
            ? self::gruposNotaCredito($cursoIds, $idCuotasPlantilla, $idLegajos)
            : self::gruposFacturables($cursoIds, $idCuotasPlantilla, $idLegajos);
        $cuotasNombre = self::etiquetaCuotasSeleccionadas($idCuotasPlantilla);

        $porCurso = [];
        $total = 0;
        $totalAlumnos = 0;

        foreach ($grupos as $grupo) {
            $puede = $grupo['puedeFacturar'];
            if ($puede) {
                $total++;
            }

            $idCurso = (int) $grupo['idCurso'];
            $porCurso[$idCurso] ??= [
                'cursoNombre' => (string) $grupo['cursoNombre'],
                'alumnos' => [],
            ];
            $porCurso[$idCurso]['alumnos'][] = [
                'idLegajo' => (int) $grupo['idLegajo'],
                'etiqueta' => (string) $grupo['etiqueta'],
                'estado' => (string) $grupo['estado'],
                'puedeFacturar' => $puede,
                'importe' => (float) $grupo['importeTotal'],
                'conceptos' => (string) $grupo['conceptosTexto'],
            ];
            $totalAlumnos++;
        }

        ksort($porCurso);

        return [
            'porCurso' => $porCurso,
            'total' => $total,
            'totalAlumnos' => $totalAlumnos,
            'cuotasNombre' => $cuotasNombre,
        ];
    }

    /**
     * @param  list<int>  $cursoIds
     * @param  list<int>  $idCuotasPlantilla
     * @param  list<int>  $idLegajos
     * @return array{
     *     porCurso: array<int, array{cursoNombre: string, alumnos: list<array{idLegajo: int, etiqueta: string, estado: string, exito: bool, nroAfip?: string}>}>,
     *     facturados: int,
     *     noFacturados: int,
     *     cuotasNombre: string
     * }
     */
    public static function procesarEnCursos(
        array $cursoIds,
        array $idCuotasPlantilla,
        array $idLegajos = [],
        string $tipoOperacion = ConsultaAfipComprobanteService::TIPO_FACTURA,
    ): array {
        return self::esNotaCredito($tipoOperacion)
            ? self::notaCreditoEnCursos($cursoIds, $idCuotasPlantilla, $idLegajos)
            : self::facturarEnCursos($cursoIds, $idCuotasPlantilla, $idLegajos);
    }

    public static function facturarEnCursos(array $cursoIds, array $idCuotasPlantilla, array $idLegajos = []): array
    {
        if (! tenantCuotasFacturacionAfipEnDevengamiento()) {
            return self::resultadoVacio($idCuotasPlantilla, 'La facturación AFIP por devengamiento no está habilitada.');
        }

        if (! Schema::hasTable('comprobanteafip')) {
            return self::resultadoVacio($idCuotasPlantilla, 'La tabla comprobanteafip no existe.');
        }

        $config = tenantCuotasFacturacionAfipConfig();
        if ($config === null) {
            return self::resultadoVacio($idCuotasPlantilla, 'Falta configurar la facturación AFIP.');
        }

        $ento = self::entoInstitucional();
        if ($ento === null) {
            return self::resultadoVacio($idCuotasPlantilla, 'Faltan datos AFIP institucionales.');
        }

        $ptoVta = (int) ($ento->ptoVta ?? 0);
        if ($ptoVta <= 0) {
            return self::resultadoVacio($idCuotasPlantilla, 'Falta el punto de venta AFIP.');
        }

        $grupos = collect(self::gruposFacturables($cursoIds, $idCuotasPlantilla, $idLegajos))
            ->filter(fn (array $g) => $g['puedeFacturar'])
            ->values();

        $porCurso = [];
        $facturados = 0;
        $noFacturados = 0;
        $cuotasNombre = self::etiquetaCuotasSeleccionadas($idCuotasPlantilla);
        $hoy = Carbon::today();
        $fechaYmd = $hoy->format('Ymd');
        $simulado = ! empty($config['simular']);
        $sufijoSimulado = $simulado ? ' (simulado, sin envío a AFIP)' : '';

        $condicionAlumnoDefault = trim((string) ($ento->condicionIva ?? ''));
        if ($condicionAlumnoDefault === '') {
            $condicionAlumnoDefault = (string) ($config['condicion_iva_alumno'] ?? 'Consumidor Final');
        }
        $condicionIvaEmisor = ComprobanteAfipDatos::condIvaInstDesdeEnto($ento);

        foreach ($grupos->chunk(self::TAMANO_LOTE_AFIP) as $loteGrupos) {
            $payloadAfip = [];
            $metaLote = [];

            foreach ($loteGrupos as $grupo) {
                /** @var Legajo $legajo */
                $legajo = $grupo['legajo'];
                /** @var list<CuotaGenerada> $registros */
                $registros = $grupo['registros'];
                $docNro = FacturacionAfipComun::documentoNumerico($legajo->dni ?? null);
                [$fechaDesde, $fechaHasta] = FacturacionAfipComun::periodoServicioLote($registros);
                $condicionIvaId = AfipCondicionIvaReceptor::idDesdeEtiqueta(
                    $condicionAlumnoDefault,
                    (int) ($config['condicion_iva_receptor_id'] ?? 5),
                );

                $payloadAfip[] = [
                    'cuit' => (string) $ento->cuit,
                    'pto_vta' => $ptoVta,
                    'doc_nro' => $docNro,
                    'importe' => (float) $grupo['importeTotal'],
                    'fecha_yyyymmdd' => $fechaYmd,
                    'fch_serv_desde' => $fechaDesde,
                    'fch_serv_hasta' => $fechaHasta,
                    'condicion_iva_receptor_id' => $condicionIvaId,
                ];
                $metaLote[] = $grupo;
            }

            try {
                $respuestasAfip = AfipWsfeEmision::emitirReciboLote($config, $payloadAfip);
            } catch (Throwable $e) {
                foreach ($metaLote as $grupo) {
                    self::registrarResultadoCurso($porCurso, $grupo, false, 'Error AFIP: '.$e->getMessage());
                    $noFacturados++;
                }

                continue;
            }

            foreach ($respuestasAfip as $idx => $respuesta) {
                $grupo = $metaLote[$idx] ?? null;
                if ($grupo === null) {
                    continue;
                }

                if (empty($respuesta['ok'])) {
                    $msg = (string) ($respuesta['mensaje'] ?? 'AFIP rechazó el comprobante.');
                    foreach ($grupo['registros'] as $registro) {
                        FacturacionAfipComun::guardarMensajeCuota($registro, 'Error AFIP: '.$msg);
                    }
                    self::registrarResultadoCurso($porCurso, $grupo, false, $msg);
                    $noFacturados++;

                    continue;
                }

                $cae = (string) ($respuesta['cae'] ?? '');
                $vtoCaeYmd = (string) ($respuesta['cae_fch_vto'] ?? '');
                $nroRecibo = (int) ($respuesta['cbte_hasta'] ?? 0);
                [$fechaDesde, $fechaHasta] = FacturacionAfipComun::periodoServicioLote($grupo['registros']);

                try {
                    $nroFormateado = self::persistirComprobanteDevengamiento(
                        $grupo,
                        $ento,
                        $config,
                        $grupo['legajo'],
                        $ptoVta,
                        $hoy,
                        $fechaDesde,
                        $fechaHasta,
                        $cae,
                        $vtoCaeYmd,
                        $nroRecibo,
                        $condicionAlumnoDefault,
                        $condicionIvaEmisor,
                        $sufijoSimulado,
                    );
                    self::registrarResultadoCurso($porCurso, $grupo, true, self::ESTADO_FACTURADO, $nroFormateado);
                    $facturados++;
                } catch (Throwable $e) {
                    self::registrarResultadoCurso($porCurso, $grupo, false, 'Error al guardar: '.$e->getMessage());
                    $noFacturados++;
                }
            }
        }

        ksort($porCurso);

        return [
            'porCurso' => $porCurso,
            'facturados' => $facturados,
            'noFacturados' => $noFacturados,
            'cuotasNombre' => $cuotasNombre,
        ];
    }

    /**
     * @param  list<int>  $cursoIds
     * @param  list<int>  $idCuotasPlantilla
     * @param  list<int>  $idLegajos
     * @return list<array<string, mixed>>
     */
    private static function gruposFacturables(array $cursoIds, array $idCuotasPlantilla, array $idLegajos = []): array
    {
        $idCuotasPlantilla = array_values(array_unique(array_filter(
            array_map('intval', $idCuotasPlantilla),
            fn (int $id) => $id > 0,
        )));

        if ($idCuotasPlantilla === []) {
            return [];
        }

        $alumnos = self::alumnosParaAlcance($cursoIds, $idLegajos);
        if ($alumnos->isEmpty()) {
            return [];
        }

        $idTerlec = (int) schoolCtx()->idTerlec;
        $grupos = [];

        foreach ($alumnos as $alumno) {
            $idLegajo = (int) $alumno->id_legajo;
            $legajo = GestionAranceles::legajoParaGestion($idLegajo);
            if ($legajo === null) {
                continue;
            }

            $registros = CuotaGenerada::query()
                ->with(['cuota:id,nombre', 'terlec:id,ano', 'curso:Id,cursec,c,s,idCurPlan,idTurnoClase'])
                ->where('idLegajos', $idLegajo)
                ->where('idTerlec', $idTerlec)
                ->whereIn('idCuotas', $idCuotasPlantilla)
                ->orderBy('idCuotas')
                ->get();

            if ($registros->isEmpty()) {
                $grupos[] = self::filaGrupo(
                    $alumno,
                    $legajo,
                    [],
                    false,
                    'Sin cuotas generadas para las plantillas seleccionadas.',
                );

                continue;
            }

            $registrosFacturables = [];
            $importeTotal = 0.0;
            $conceptos = [];
            $omitidos = [];

            foreach ($registros as $registro) {
                $importe = round((float) ($registro->importe ?? 0), 2);
                if ($importe <= 0) {
                    $omitidos[] = trim((string) ($registro->cuota?->nombre ?? 'Cuota')).' (importe cero)';

                    continue;
                }

                if (ComprobantesAfipCuotaService::facturaVigentePorCuotaGenerada((int) $registro->id) !== null) {
                    $omitidos[] = trim((string) ($registro->cuota?->nombre ?? 'Cuota')).' (ya facturada)';

                    continue;
                }

                $registrosFacturables[] = $registro;
                $importeTotal += $importe;
                $conceptos[] = mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? 'CUOTA')));
            }

            if ($registrosFacturables === []) {
                $msg = $omitidos !== []
                    ? implode('; ', $omitidos)
                    : 'No hay conceptos facturables.';
                $grupos[] = self::filaGrupo($alumno, $legajo, [], false, $msg);

                continue;
            }

            $docNro = FacturacionAfipComun::documentoNumerico($legajo->dni ?? null);
            if ($docNro <= 0) {
                $grupos[] = self::filaGrupo($alumno, $legajo, $registrosFacturables, false, 'DNI inválido del estudiante.');

                continue;
            }

            $aFacturar = [];
            foreach ($registrosFacturables as $registro) {
                $aFacturar[] = trim((string) ($registro->cuota?->nombre ?? 'Cuota')).' (se facturará)';
            }

            $estado = implode('; ', $aFacturar);
            if ($omitidos !== []) {
                $estado .= '. Omitidos: '.implode('; ', $omitidos);
            }

            $grupos[] = self::filaGrupo(
                $alumno,
                $legajo,
                $registrosFacturables,
                true,
                $estado,
                $importeTotal,
                implode(', ', $conceptos),
            );
        }

        return $grupos;
    }

    /**
     * Grupos anulables con nota de crédito (una NC por factura vigente y estudiante).
     *
     * @param  list<int>  $cursoIds
     * @param  list<int>  $idCuotasPlantilla
     * @param  list<int>  $idLegajos
     * @return list<array<string, mixed>>
     */
    private static function gruposNotaCredito(array $cursoIds, array $idCuotasPlantilla, array $idLegajos = []): array
    {
        $idCuotasPlantilla = array_values(array_unique(array_filter(
            array_map('intval', $idCuotasPlantilla),
            fn (int $id) => $id > 0,
        )));

        if ($idCuotasPlantilla === []) {
            return [];
        }

        $alumnos = self::alumnosParaAlcance($cursoIds, $idLegajos);
        if ($alumnos->isEmpty()) {
            return [];
        }

        $idTerlec = (int) schoolCtx()->idTerlec;
        $grupos = [];

        foreach ($alumnos as $alumno) {
            $idLegajo = (int) $alumno->id_legajo;
            $legajo = GestionAranceles::legajoParaGestion($idLegajo);
            if ($legajo === null) {
                continue;
            }

            $registros = CuotaGenerada::query()
                ->with(['cuota:id,nombre', 'terlec:id,ano', 'curso:Id,cursec,c,s,idCurPlan,idTurnoClase'])
                ->where('idLegajos', $idLegajo)
                ->where('idTerlec', $idTerlec)
                ->whereIn('idCuotas', $idCuotasPlantilla)
                ->orderBy('idCuotas')
                ->get();

            if ($registros->isEmpty()) {
                $grupos[] = self::filaGrupo(
                    $alumno,
                    $legajo,
                    [],
                    false,
                    'Sin cuotas generadas para las plantillas seleccionadas.',
                );

                continue;
            }

            $porFactura = [];
            $sinFactura = [];

            foreach ($registros as $registro) {
                $factura = ComprobantesAfipCuotaService::facturaVigentePorCuotaGenerada((int) $registro->id);
                $nombreCuota = trim((string) ($registro->cuota?->nombre ?? 'Cuota'));

                if ($factura === null) {
                    $sinFactura[] = $nombreCuota.' (sin factura vigente)';

                    continue;
                }

                $idFactura = (int) $factura->idComprobanteAfip;
                $porFactura[$idFactura] ??= [
                    'factura' => $factura,
                    'registros' => [],
                    'conceptos' => [],
                ];
                $porFactura[$idFactura]['registros'][] = $registro;
                $porFactura[$idFactura]['conceptos'][] = mb_strtoupper($nombreCuota);
            }

            if ($porFactura === []) {
                $msg = $sinFactura !== []
                    ? implode('; ', $sinFactura)
                    : 'No hay facturas vigentes para anular.';
                $grupos[] = self::filaGrupo($alumno, $legajo, [], false, $msg);

                continue;
            }

            foreach ($porFactura as $grupoFactura) {
                /** @var ComprobanteAfip $factura */
                $factura = $grupoFactura['factura'];
                /** @var list<CuotaGenerada> $registrosFactura */
                $registrosFactura = $grupoFactura['registros'];
                $importe = round((float) ($factura->importePagado ?? 0), 2);

                if ($importe <= 0) {
                    $grupos[] = self::filaGrupo(
                        $alumno,
                        $legajo,
                        $registrosFactura,
                        false,
                        'La factura vigente no tiene importe para anular.',
                        0.0,
                        '',
                        $factura,
                    );

                    continue;
                }

                $aAnular = [];
                foreach ($registrosFactura as $registro) {
                    $aAnular[] = trim((string) ($registro->cuota?->nombre ?? 'Cuota')).' (se anulará con NC)';
                }

                $estado = implode('; ', $aAnular);
                if ($sinFactura !== []) {
                    $estado .= '. Sin factura: '.implode('; ', $sinFactura);
                }

                $grupos[] = self::filaGrupo(
                    $alumno,
                    $legajo,
                    $registrosFactura,
                    true,
                    $estado,
                    $importe,
                    implode(', ', array_unique($grupoFactura['conceptos'])),
                    $factura,
                );
            }
        }

        return $grupos;
    }

    /**
     * @param  list<int>  $cursoIds
     * @param  list<int>  $idCuotasPlantilla
     * @param  list<int>  $idLegajos
     * @return array{
     *     porCurso: array<int, array{cursoNombre: string, alumnos: list<array{idLegajo: int, etiqueta: string, estado: string, exito: bool, nroAfip?: string}>}>,
     *     facturados: int,
     *     noFacturados: int,
     *     cuotasNombre: string
     * }
     */
    public static function notaCreditoEnCursos(array $cursoIds, array $idCuotasPlantilla, array $idLegajos = []): array
    {
        if (! tenantCuotasFacturacionAfipEnDevengamiento()) {
            return self::resultadoVacio($idCuotasPlantilla, 'La facturación AFIP por devengamiento no está habilitada.');
        }

        if (! Schema::hasTable('comprobanteafip')) {
            return self::resultadoVacio($idCuotasPlantilla, 'La tabla comprobanteafip no existe.');
        }

        $config = tenantCuotasFacturacionAfipConfig();
        if ($config === null) {
            return self::resultadoVacio($idCuotasPlantilla, 'Falta configurar la facturación AFIP.');
        }

        $tipoNc = (int) ($config['nota_credito_tipo'] ?? 12);
        if ($tipoNc <= 0) {
            return self::resultadoVacio($idCuotasPlantilla, 'Falta configurar el tipo de nota de crédito AFIP.');
        }

        $grupos = collect(self::gruposNotaCredito($cursoIds, $idCuotasPlantilla, $idLegajos))
            ->filter(fn (array $g) => $g['puedeFacturar'])
            ->values();

        $porCurso = [];
        $facturados = 0;
        $noFacturados = 0;
        $cuotasNombre = self::etiquetaCuotasSeleccionadas($idCuotasPlantilla);

        foreach ($grupos as $grupo) {
            /** @var ComprobanteAfip $factura */
            $factura = $grupo['factura'];
            /** @var list<CuotaGenerada> $registros */
            $registros = $grupo['registros'];
            $primerRegistro = $registros[0] ?? null;

            if ($primerRegistro === null) {
                self::registrarResultadoCurso($porCurso, $grupo, false, 'No hay cuotas asociadas a la factura.');
                $noFacturados++;

                continue;
            }

            $resultado = FacturacionAfipImputacionPago::emitirNotaCredito(
                $primerRegistro,
                (int) $grupo['idLegajo'],
                $factura,
            );

            if (! ($resultado['ok'] ?? false)) {
                self::registrarResultadoCurso(
                    $porCurso,
                    $grupo,
                    false,
                    (string) ($resultado['mensaje'] ?? 'No se pudo emitir la nota de crédito.'),
                );
                $noFacturados++;

                continue;
            }

            $nroAfip = null;
            if (isset($resultado['idComprobanteAfip'])) {
                $nc = ComprobanteAfip::query()->find((int) $resultado['idComprobanteAfip']);
                if ($nc !== null) {
                    $nroAfip = ComprobantesAfipCuotaService::numeroFormateado($nc);
                }
            }

            self::registrarResultadoCurso($porCurso, $grupo, true, self::ESTADO_NOTA_CREDITO, $nroAfip);
            $facturados++;
        }

        ksort($porCurso);

        return [
            'porCurso' => $porCurso,
            'facturados' => $facturados,
            'noFacturados' => $noFacturados,
            'cuotasNombre' => $cuotasNombre,
        ];
    }

    private static function esNotaCredito(string $tipoOperacion): bool
    {
        return $tipoOperacion === ConsultaAfipComprobanteService::TIPO_NOTA_CREDITO;
    }

    /**
     * Estudiantes regulares de los cursos + legajos elegidos individualmente (sin duplicar).
     *
     * @param  list<int>  $cursoIds
     * @param  list<int>  $idLegajos
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function alumnosParaAlcance(array $cursoIds, array $idLegajos): \Illuminate\Support\Collection
    {
        $porLegajo = [];

        if ($cursoIds !== []) {
            foreach (GeneracionMasivaCuotasConsulta::alumnosRegularesPorCursos($cursoIds) as $alumno) {
                $porLegajo[(int) $alumno->id_legajo] = $alumno;
            }
        }

        foreach (array_values(array_unique(array_filter(
            array_map('intval', $idLegajos),
            fn (int $id) => $id > 0,
        ))) as $idLegajo) {
            if (isset($porLegajo[$idLegajo])) {
                continue;
            }

            $fila = GeneracionMasivaCuotasConsulta::filaAlumnoDesdeLegajo($idLegajo);
            if ($fila !== null) {
                $porLegajo[$idLegajo] = $fila;
            }
        }

        return collect(array_values($porLegajo));
    }

    /**
     * @param  list<CuotaGenerada>  $registros
     * @return array<string, mixed>
     */
    private static function nombreGrupoCurso(object $alumno): string
    {
        $nombre = trim((string) ($alumno->curso_nombre ?? ''));

        return $nombre !== '' ? $nombre : 'Estudiantes individuales';
    }

    /**
     * @param  list<CuotaGenerada>  $registros
     * @return array<string, mixed>
     */
    private static function filaGrupo(
        object $alumno,
        Legajo $legajo,
        array $registros,
        bool $puedeFacturar,
        string $estado,
        float $importeTotal = 0.0,
        string $conceptosTexto = '',
        ?ComprobanteAfip $factura = null,
    ): array {
        return [
            'idLegajo' => (int) $alumno->id_legajo,
            'idCurso' => (int) $alumno->id_curso,
            'cursoNombre' => self::nombreGrupoCurso($alumno),
            'etiqueta' => GeneracionMasivaCuotasConsulta::etiquetaAlumno($alumno),
            'legajo' => $legajo,
            'registros' => $registros,
            'factura' => $factura,
            'puedeFacturar' => $puedeFacturar,
            'estado' => $estado,
            'importeTotal' => round($importeTotal, 2),
            'conceptosTexto' => $conceptosTexto,
        ];
    }

    /**
     * @param  array<string, mixed>  $grupo
     */
    private static function persistirComprobanteDevengamiento(
        array $grupo,
        Ento $ento,
        array $config,
        Legajo $legajo,
        int $ptoVta,
        Carbon $hoy,
        string $fechaDesde,
        string $fechaHasta,
        string $cae,
        string $vtoCaeYmd,
        int $nroRecibo,
        string $condicionAlumno,
        string $condicionIvaEmisor,
        string $sufijoSimulado,
    ): string {
        /** @var list<CuotaGenerada> $registros */
        $registros = $grupo['registros'];
        $importeTotal = (float) $grupo['importeTotal'];
        $conceptos = [];
        $importesLinea = [];
        $idsCuotas = [];

        foreach ($registros as $registro) {
            $importe = round((float) ($registro->importe ?? 0), 2);
            $conceptos[] = mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? 'CUOTA')));
            $importesLinea[] = number_format($importe, 2, '.', '');
            $idsCuotas[] = (int) $registro->id;
        }

        $docNro = FacturacionAfipComun::documentoNumerico($legajo->dni ?? null);
        $nombreResp = FacturacionAfipComun::responsableEconomicoFamilia($legajo);
        $dniResp = FacturacionAfipComun::dniRespDesdeFamilia($legajo);
        $nombreAlumno = mb_strtoupper(trim(($legajo->apellido ?? '').' '.($legajo->nombre ?? '')));
        $conceptoPrincipal = count($conceptos) === 1 ? $conceptos[0] : 'CUOTAS ESCOLARES';
        $codigoBarras = AfipCodigoBarras::generar(
            (string) $ento->cuit,
            (int) $config['cbte_tipo'],
            $ptoVta,
            $cae,
            $vtoCaeYmd,
        );

        $primerRegistro = $registros[0];
        $snapshotInst = FacturacionAfipComun::snapshotInstitucionalPdf($ento);
        $cursoAlumno = FacturacionAfipComun::cursoTextoDesdeRegistro($primerRegistro);

        DB::transaction(function () use (
            $registros,
            $ento,
            $legajo,
            $ptoVta,
            $config,
            $importeTotal,
            $fechaDesde,
            $fechaHasta,
            $hoy,
            $cae,
            $vtoCaeYmd,
            $nroRecibo,
            $codigoBarras,
            $nombreResp,
            $dniResp,
            $conceptoPrincipal,
            $conceptos,
            $importesLinea,
            $nombreAlumno,
            $docNro,
            $condicionAlumno,
            $condicionIvaEmisor,
            $sufijoSimulado,
            $idsCuotas,
            $primerRegistro,
            $snapshotInst,
            $cursoAlumno,
        ): void {
            ComprobanteAfip::query()->create([
                'nombreInstitucion' => trim((string) $ento->insti),
                'razonSocial' => trim((string) $ento->insti),
                'cuitInstitucion' => preg_replace('/\D/', '', (string) $ento->cuit),
                'domicilioComercial' => $ento->domicilioComercialCompleto(),
                'condicionIvaInstitucion' => $condicionIvaEmisor,
                'telefonoInstitucion' => $snapshotInst['telefonoInstitucion'],
                'aporteEstatal' => $snapshotInst['aporteEstatal'],
                'puntoVenta' => $ptoVta,
                'ingresosBrutos' => trim((string) ($ento->ingresosBrutos ?? '')),
                'fechaInicioActividades' => FacturacionAfipComun::formatearFechaEnto($ento->fechaInicioAct ?? null),
                'nombreAlumno' => $nombreAlumno,
                'dni' => (string) $docNro,
                'nombreResp' => $nombreResp,
                'dniResp' => $dniResp,
                'cursoAlumno' => $cursoAlumno,
                'condicionIvaAlumno' => $condicionAlumno,
                'condicionVenta' => '',
                'fechaDesde' => FacturacionAfipComun::formatearFechaBarra($fechaDesde),
                'fechaHasta' => FacturacionAfipComun::formatearFechaBarra($fechaHasta),
                'fechaEmision' => $hoy->format('Y/m/d'),
                'fechaVencimiento' => $hoy->format('Y/m/d'),
                'tipoComprobante' => (int) $config['cbte_tipo'],
                'docTipoAfip' => (int) ($config['doc_tipo'] ?? 96),
                'codigoBarras' => $codigoBarras,
                'nroRecibo' => $nroRecibo,
                'cae' => $cae,
                'vtoCae' => FacturacionAfipComun::formatearFechaBarra($vtoCaeYmd),
                'importePagado' => $importeTotal,
                'interesPagado' => 0.0,
                'idCbteAsoc' => (int) $primerRegistro->id,
                'concepto' => $conceptoPrincipal,
                'subConceptos' => implode('|', $conceptos),
                'importeSubConceptos' => implode('|', $importesLinea),
                'saldoRestante' => implode(',', $idsCuotas),
                'idCuotasPagos' => 0,
            ]);

            foreach ($registros as $registro) {
                FacturacionAfipComun::guardarMensajeCuota(
                    $registro,
                    'Comprobante AFIP emitido. CAE '.$cae.$sufijoSimulado,
                );
            }
        });

        return str_pad((string) $ptoVta, 4, '0', STR_PAD_LEFT)
            .'-'
            .str_pad((string) $nroRecibo, 8, '0', STR_PAD_LEFT);
    }

    private static function entoInstitucional(): ?Ento
    {
        $ento = Ento::query()
            ->where('idNivel', (int) schoolCtx()->idNivel)
            ->first([
                'insti',
                'direccion',
                'localidad',
                'provincia',
                'telefono',
                'cuit',
                'condIvaInst',
                'aporteEstatal',
                'condicionIva',
                'ptoVta',
                'ingresosBrutos',
                'fechaInicioAct',
            ]);

        if ($ento === null || trim((string) $ento->cuit) === '') {
            return null;
        }

        return $ento;
    }

    /**
     * @param  list<int>  $idCuotasPlantilla
     */
    private static function etiquetaCuotasSeleccionadas(array $idCuotasPlantilla): string
    {
        if ($idCuotasPlantilla === []) {
            return '';
        }

        $nombres = \App\Models\Cuota::query()
            ->whereIn('id', $idCuotasPlantilla)
            ->orderBy('orden')
            ->orderBy('id')
            ->pluck('nombre')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->values()
            ->all();

        return implode(', ', $nombres);
    }

    /**
     * @param  array<int, array<string, mixed>>  $porCurso
     * @param  array<string, mixed>  $grupo
     */
    private static function registrarResultadoCurso(
        array &$porCurso,
        array $grupo,
        bool $exito,
        string $estado,
        ?string $nroAfip = null,
    ): void {
        $idCurso = (int) ($grupo['idCurso'] ?? 0);
        $porCurso[$idCurso] ??= [
            'cursoNombre' => (string) ($grupo['cursoNombre'] ?? '—'),
            'alumnos' => [],
        ];

        $fila = [
            'idLegajo' => (int) $grupo['idLegajo'],
            'etiqueta' => (string) $grupo['etiqueta'],
            'estado' => $estado,
            'exito' => $exito,
        ];
        if ($nroAfip !== null) {
            $fila['nroAfip'] = $nroAfip;
        }

        $porCurso[$idCurso]['alumnos'][] = $fila;
    }

    /**
     * @param  list<int>  $idCuotasPlantilla
     * @return array{porCurso: array{}, facturados: int, noFacturados: int, cuotasNombre: string}
     */
    private static function resultadoVacio(array $idCuotasPlantilla, string $mensaje): array
    {
        return [
            'porCurso' => [
                0 => [
                    'cursoNombre' => '—',
                    'alumnos' => [[
                        'idLegajo' => 0,
                        'etiqueta' => $mensaje,
                        'estado' => $mensaje,
                        'exito' => false,
                    ]],
                ],
            ],
            'facturados' => 0,
            'noFacturados' => 0,
            'cuotasNombre' => self::etiquetaCuotasSeleccionadas($idCuotasPlantilla),
        ];
    }
}
