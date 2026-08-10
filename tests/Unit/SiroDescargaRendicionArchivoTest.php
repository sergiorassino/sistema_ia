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
