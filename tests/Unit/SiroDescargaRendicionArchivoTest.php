<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionArchivo;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionLinea;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SiroDescargaRendicionArchivoTest extends TestCase
{
    public function test_normalizar_nombre_archivo_recorta_y_quita_espacios(): void
    {
        $nombre = SiroDescargaRendicionArchivo::normalizarNombreArchivo('  CobranzasSiro_Cta. 1105_20260624txt.txt  ');

        $this->assertSame('CobranzasSiro_Cta. 1105_20260624txt.txt', $nombre);
    }

    public function test_normalizar_nombre_archivo_respeta_maximo_cincuenta(): void
    {
        $nombre = SiroDescargaRendicionArchivo::normalizarNombreArchivo(str_repeat('A', 60));

        $this->assertSame(50, mb_strlen($nombre));
    }

    public function test_motivo_duplicado_en_planilla_detecta_cadena_y_id_pago(): void
    {
        $linea = [
            'cadenaPago' => str_repeat('X', 272),
            'idPagoSiro' => '1234567890',
        ];
        $indice = [
            'cadenas' => [],
            'idsPago' => ['1234567890' => true],
        ];

        $motivo = $this->invocarMotivoDuplicadoEnPlanilla($linea, $indice);

        $this->assertSame('El pago SIRO 1234567890 ya fue cargado en esta planilla.', $motivo);
    }

    public function test_motivo_duplicado_en_planilla_detecta_cadena_exacta(): void
    {
        $cadena = str_repeat('Y', 126);
        $linea = SiroDescargaRendicionLinea::parsear($cadena);
        $this->assertNotNull($linea);

        $indice = [
            'cadenas' => [$cadena => true],
            'idsPago' => [],
        ];

        $motivo = $this->invocarMotivoDuplicadoEnPlanilla($linea, $indice);

        $this->assertSame('Registro ya cargado en esta planilla.', $motivo);
    }

    public function test_motivo_duplicado_en_planilla_retorna_null_si_no_existe(): void
    {
        $linea = [
            'cadenaPago' => str_repeat('Z', 272),
            'idPagoSiro' => '9876543210',
        ];
        $indice = [
            'cadenas' => [],
            'idsPago' => [],
        ];

        $motivo = $this->invocarMotivoDuplicadoEnPlanilla($linea, $indice);

        $this->assertNull($motivo);
    }

    public function test_obs_para_formulario_prioriza_pago_repetido_y_omite_match_provisorio(): void
    {
        $obs = SiroDescargaRendicionArchivo::obsParaFormularioPlanilla(
            [SiroDescargaRendicionArchivo::mensajePagoRepetidoPlanilla('0110709841', 1151)],
            [
                'Match provisorio (puesta en marcha): se eligió cupones_a_pagar.id_factura 00000999000008802088.',
                'La cuota ya estaba saldada al descargar; posible pago doble.',
            ],
        );

        $this->assertSame(
            'PAGO DUPLICADO: Pago repetido: pagado por primera vez en planilla 1151 (SIRO 0110709841).'
            .' | La cuota ya estaba saldada al descargar; posible pago doble.',
            $obs,
        );
    }

    public function test_obs_para_formulario_sin_avisos_relevantes_retorna_null(): void
    {
        $obs = SiroDescargaRendicionArchivo::obsParaFormularioPlanilla(
            [],
            ['Match provisorio (puesta en marcha): se eligió cupones_a_pagar.id_factura X.'],
        );

        $this->assertNull($obs);
    }

    public function test_es_advertencia_match_provisorio(): void
    {
        $this->assertTrue(SiroDescargaRendicionArchivo::esAdvertenciaMatchProvisorio(
            'Match provisorio (puesta en marcha): texto',
        ));
        $this->assertTrue(SiroDescargaRendicionArchivo::esAdvertenciaMatchProvisorio(
            'Provisorio upload cercano → 0000091900008802088 · importes OK',
        ));
        $this->assertTrue(SiroDescargaRendicionArchivo::esAdvertenciaMatchProvisorio(
            'PROVISORIO 1 (upload cercano) · Importe rendición: $1.000,00',
        ));
        $this->assertFalse(SiroDescargaRendicionArchivo::esAdvertenciaMatchProvisorio(
            'Pago repetido: pagado por primera vez en planilla 1151.',
        ));
        $this->assertFalse(SiroDescargaRendicionArchivo::esAdvertenciaMatchProvisorio(
            'PAGO DUPLICADO: Pago repetido: pagado por primera vez en planilla 1151.',
        ));
    }

    public function test_detalle_pago_duplicado_antepone_prefijo_y_junta_avisos(): void
    {
        $detalle = SiroDescargaRendicionArchivo::detallePagoDuplicado(
            [SiroDescargaRendicionArchivo::mensajePagoRepetidoPlanilla('0110709841', 1151)],
            [
                'Match provisorio (puesta en marcha): se eligió cupones_a_pagar.id_factura X.',
                'La cuota ya estaba saldada al descargar; posible pago doble.',
            ],
        );

        $this->assertSame(
            'PAGO DUPLICADO: Pago repetido: pagado por primera vez en planilla 1151 (SIRO 0110709841).'
            .' | La cuota ya estaba saldada al descargar; posible pago doble.',
            $detalle,
        );
    }

    public function test_detalle_pago_duplicado_sin_avisos_retorna_null(): void
    {
        $this->assertNull(SiroDescargaRendicionArchivo::detallePagoDuplicado(
            [],
            ['Match provisorio (puesta en marcha): texto'],
        ));
    }

    public function test_componer_detalle_pone_duplicado_antes_que_provisorio(): void
    {
        $detalle = SiroDescargaRendicionArchivo::componerDetalleEncontrado(
            'PAGO DUPLICADO: La cuota ya tiene otro pago en este archivo (registro 12); posible pago doble.',
            'PROVISORIO: id_factura archivo: 000009990000088308801088 - Importe archivo: $999,00 - id_factura cupones_a_pagar: 000009990000088308801088 - importes cupones_a_pagar: 1v $999,00  2v $999,00  3v $999,00. RESOLVIENDO POR: provisorio 1 — upload cercano (449)',
        );

        $this->assertStringStartsWith('PAGO DUPLICADO:', $detalle);
        $this->assertStringContainsString(' | PROVISORIO:', $detalle);
    }

    public function test_es_aviso_pago_duplicado(): void
    {
        $this->assertTrue(SiroDescargaRendicionArchivo::esAvisoPagoDuplicado(
            'La cuota ya estaba saldada al descargar; posible pago doble.',
        ));
        $this->assertTrue(SiroDescargaRendicionArchivo::esAvisoPagoDuplicado(
            SiroDescargaRendicionArchivo::mensajePagoRepetidoPlanilla('0110709841', 1151),
        ));
        $this->assertFalse(SiroDescargaRendicionArchivo::esAvisoPagoDuplicado(
            'PROVISORIO: id_factura archivo: X.',
        ));
        $this->assertTrue(SiroDescargaRendicionArchivo::esAvisoPagoDuplicado(
            SiroDescargaRendicionArchivo::mensajePagoDuplicadoCuotaPlanilla(1148),
        ));
        $this->assertTrue(SiroDescargaRendicionArchivo::esAvisoPagoDuplicado(
            SiroDescargaRendicionArchivo::mensajePagoDuplicadoMismoArchivo(6),
        ));
    }

    public function test_pago_repetido_en_el_mismo_archivo_se_registra_con_aviso(): void
    {
        $aviso = SiroDescargaRendicionArchivo::mensajePagoDuplicadoMismoArchivo(6);
        $detalle = SiroDescargaRendicionArchivo::detallePagoDuplicado([$aviso]);

        $this->assertSame(
            'PAGO DUPLICADO: La cuota ya tiene otro pago en este archivo (registro 6); se registra igual (posible pago doble).',
            $detalle,
        );
        $this->assertSame(
            'PAGO DUPLICADO: '.$aviso,
            SiroDescargaRendicionArchivo::obsParaFormularioPlanilla([$aviso]),
        );

        $avisoSiro = SiroDescargaRendicionArchivo::mensajePagoDuplicadoIdSiroMismoArchivo('0111221833', 6);
        $this->assertStringContainsString('0111221833', $avisoSiro);
        $this->assertStringContainsString('registro 6', $avisoSiro);
        $this->assertStringStartsWith(
            'PAGO DUPLICADO:',
            (string) SiroDescargaRendicionArchivo::detallePagoDuplicado([$avisoSiro, $aviso]),
        );

        $linea = [
            'cadenaPago' => str_repeat('A', 272),
            'idPagoSiro' => '0111221833',
        ];
        $indiceVacio = ['cadenas' => [], 'idsPago' => []];
        $this->assertNull($this->invocarMotivoDuplicadoEnPlanilla($linea, $indiceVacio));
    }

    public function test_leyenda_corta_obs_de_pago_duplicado(): void
    {
        $this->assertSame('', SiroDescargaRendicionArchivo::leyendaCortaObs(null));
        $this->assertSame('PAGO DUPLICADO', SiroDescargaRendicionArchivo::leyendaCortaObs(
            'PAGO DUPLICADO: Pago repetido: pagado por primera vez en planilla 1148 (SIRO 0110709841).',
        ));
        $this->assertSame('PAGO DUPLICADO', SiroDescargaRendicionArchivo::leyendaCortaObs(
            SiroDescargaRendicionArchivo::mensajePagoDuplicadoCuotaPlanilla(1148),
        ));
    }

    public function test_patron_like_id_pago_siro_ancla_en_posicion_227(): void
    {
        $patron = SiroDescargaRendicionArchivo::patronLikeIdPagoSiro('0110570000');

        $this->assertSame(226, substr_count($patron, '_'));
        $this->assertStringEndsWith('0110570000%', $patron);

        // Cadena con el id solo en el código de barras (pos ~51) NO debe matchear.
        $cadenaFalsa = str_repeat('0', 51).'0110570000'.str_repeat('0', 215);
        $this->assertFalse($this->likeSqlSimple($cadenaFalsa, $patron));

        // Cadena con el id en posiciones 227–236 (0-based 226) SÍ debe matchear.
        $cadenaReal = str_repeat('0', 226).'0110570000'.str_repeat('0', 40);
        $this->assertTrue($this->likeSqlSimple($cadenaReal, $patron));
    }

    /**
     * Emula LIKE de SQL con `_` = un carácter y `%` = cualquier cola (ASCII).
     */
    private function likeSqlSimple(string $valor, string $patron): bool
    {
        $regex = '/^';
        $len = strlen($patron);
        for ($i = 0; $i < $len; $i++) {
            $ch = $patron[$i];
            if ($ch === '_') {
                $regex .= '.';
            } elseif ($ch === '%') {
                $regex .= '.*';
            } else {
                $regex .= preg_quote($ch, '/');
            }
        }
        $regex .= '$/';

        return preg_match($regex, $valor) === 1;
    }

    /**
     * @param  array{idPagoSiro: string, cadenaPago: string}  $linea
     * @param  array{cadenas: array<string, true>, idsPago: array<string, true>}  $indice
     */
    private function invocarMotivoDuplicadoEnPlanilla(array $linea, array $indice): ?string
    {
        $metodo = new ReflectionMethod(SiroDescargaRendicionArchivo::class, 'motivoDuplicadoEnPlanilla');
        $metodo->setAccessible(true);

        return $metodo->invoke(null, $linea, $indice);
    }
}
