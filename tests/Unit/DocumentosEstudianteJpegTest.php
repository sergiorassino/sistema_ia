<?php

namespace Tests\Unit;

use App\Support\Alumnos\DocumentosEstudianteAutogestion;
use PHPUnit\Framework\TestCase;

class DocumentosEstudianteJpegTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD no está disponible.');
        }
    }

    public function test_achica_el_lado_largo_y_pesa_menos_que_el_jpg_original(): void
    {
        $origen = $this->jpegTemporal(2400, 1800, 95);
        $original = (string) file_get_contents($origen);

        $bin = DocumentosEstudianteAutogestion::comprimirJpegABinario($origen);
        $info = getimagesizefromstring($bin);

        $this->assertIsArray($info);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
        $this->assertLessThanOrEqual(DocumentosEstudianteAutogestion::JPEG_LADO_MAX_PX, $info[0]);
        $this->assertLessThanOrEqual(DocumentosEstudianteAutogestion::JPEG_LADO_MAX_PX, $info[1]);
        $this->assertSame(1200, $info[0]);
        $this->assertSame(900, $info[1]);
        $this->assertLessThan(strlen($original), strlen($bin));

        @unlink($origen);
    }

    public function test_no_agranda_una_foto_chica_y_la_regraba_en_jpeg(): void
    {
        $origen = $this->jpegTemporal(400, 300, 95);
        $original = (string) file_get_contents($origen);

        $bin = DocumentosEstudianteAutogestion::comprimirJpegABinario($origen);
        $info = getimagesizefromstring($bin);

        $this->assertIsArray($info);
        $this->assertSame(400, $info[0]);
        $this->assertSame(300, $info[1]);
        $this->assertLessThan(strlen($original), strlen($bin));

        @unlink($origen);
    }

    public function test_rota_jpeg_90_grados_horario_y_pasa_la_mitad_izquierda_arriba(): void
    {
        $origen = $this->jpegMitades(80, 40);
        $bin = DocumentosEstudianteAutogestion::rotarImagenABinario($origen);
        $info = getimagesizefromstring($bin);

        $this->assertIsArray($info);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
        $this->assertSame(40, $info[0]);
        $this->assertSame(80, $info[1]);

        $imagen = imagecreatefromstring($bin);
        $this->assertNotFalse($imagen);
        $arriba = imagecolorsforindex($imagen, imagecolorat($imagen, 20, 8));
        $abajo = imagecolorsforindex($imagen, imagecolorat($imagen, 20, 70));
        imagedestroy($imagen);

        $this->assertGreaterThan(180, $arriba['red']);
        $this->assertLessThan(80, $arriba['blue']);
        $this->assertGreaterThan(180, $abajo['blue']);
        $this->assertLessThan(80, $abajo['red']);
        $this->assertLessThan(strlen((string) file_get_contents($origen)), strlen($bin));

        @unlink($origen);
    }

    public function test_al_rotar_vuelve_a_comprimir_el_lado_largo(): void
    {
        $origen = $this->jpegTemporal(2400, 1800, 95);
        $original = (string) file_get_contents($origen);

        $bin = DocumentosEstudianteAutogestion::rotarImagenABinario($origen);
        $info = getimagesizefromstring($bin);

        $this->assertIsArray($info);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
        $this->assertSame(900, $info[0]);
        $this->assertSame(1200, $info[1]);
        $this->assertLessThanOrEqual(DocumentosEstudianteAutogestion::JPEG_LADO_MAX_PX, max($info[0], $info[1]));
        $this->assertLessThan(strlen($original), strlen($bin));

        @unlink($origen);
    }

    private function jpegTemporal(int $ancho, int $alto, int $calidad): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        $this->assertNotFalse($imagen);

        for ($y = 0; $y < $alto; $y += 8) {
            for ($x = 0; $x < $ancho; $x += 8) {
                $color = imagecolorallocate($imagen, random_int(0, 255), random_int(0, 255), random_int(0, 255));
                imagefilledrectangle($imagen, $x, $y, min($ancho - 1, $x + 7), min($alto - 1, $y + 7), $color);
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'docjpg');
        $this->assertNotFalse($ruta);
        $destino = $ruta.'.jpg';
        @unlink($ruta);
        $this->assertTrue(imagejpeg($imagen, $destino, $calidad));
        imagedestroy($imagen);

        return $destino;
    }

    private function jpegMitades(int $ancho, int $alto): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        $this->assertNotFalse($imagen);
        $rojo = imagecolorallocate($imagen, 255, 0, 0);
        $azul = imagecolorallocate($imagen, 0, 0, 255);
        imagefilledrectangle($imagen, 0, 0, (int) ($ancho / 2) - 1, $alto - 1, $rojo);
        imagefilledrectangle($imagen, (int) ($ancho / 2), 0, $ancho - 1, $alto - 1, $azul);

        $ruta = tempnam(sys_get_temp_dir(), 'docrot');
        $this->assertNotFalse($ruta);
        $destino = $ruta.'.jpg';
        @unlink($ruta);
        $this->assertTrue(imagejpeg($imagen, $destino, 95));
        imagedestroy($imagen);

        return $destino;
    }
}
