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
