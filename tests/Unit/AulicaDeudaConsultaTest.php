<?php

namespace Tests\Unit;

use App\Support\Aulica\AulicaDeudaConsulta;
use App\Support\Aulica\AulicaDni;
use App\Support\Aulica\AulicaSaldoPersona;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AulicaDeudaConsultaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenant.slug' => 'montecristo',
            'tenant.aulica_deuda.habilitado' => true,
            'tenant.aulica_deuda.ambiente' => 'test',
            'tenant.aulica_deuda.cache_saldos_segundos' => 60,
            'services.aulica.username' => 'user@test',
            'services.aulica.password' => 'secret',
            'services.aulica.codigo' => 'codigo-institucion',
            'services.aulica.ambiente' => 'test',
        ]);

        Cache::flush();
    }

    public function test_dni_invalido_se_descarta(): void
    {
        $this->assertNull(AulicaDni::normalizar(''));
        $this->assertNull(AulicaDni::normalizar(0));
        $this->assertNull(AulicaDni::normalizar('-'));
        $this->assertNull(AulicaDni::normalizar('123'));
        $this->assertSame('30111222', AulicaDni::normalizar('30.111.222'));
    }

    public function test_consulta_por_dni_estudiante_y_familia(): void
    {
        $this->fakeAulica([
            [
                'idPersona' => 10,
                'saldo' => 1500.5,
                'nroDoc' => '30111222',
                'tipoDoc' => 'DNI',
                'nombre' => 'Juan',
                'apellido' => 'Perez',
            ],
            [
                'idPersona' => 11,
                'saldo' => 800,
                'nroDoc' => '40111222',
                'tipoDoc' => 'DNI',
                'nombre' => 'Ana',
                'apellido' => 'Perez',
            ],
        ]);

        $resultado = (new AulicaDeudaConsulta)->paraDnis('30111222', '20111222');

        $this->assertTrue($resultado->consultaOk);
        $this->assertTrue($resultado->tieneDeuda());
        $this->assertEqualsWithDelta(1500.5, $resultado->saldoEstudiante(), 0.01);
        $this->assertEqualsWithDelta(2300.5, $resultado->saldoGrupoFamiliar(), 0.01);
        $this->assertCount(1, $resultado->hermanosConDeuda());
        $this->assertStringContainsString('1.500,50', $resultado->mensajeVisible());
        $this->assertStringContainsString('Perez, Ana', $resultado->mensajeVisible());

        $modal = $resultado->paraModal('DNI del tutor');
        $this->assertSame('POST', $modal['metodo']);
        $this->assertStringContainsString('/alumnos/ctacte/saldos', $modal['endpoint']);
        $this->assertSame('30111222', $modal['consultas'][0]['nro_doc']);
        $this->assertSame('DNI', $modal['consultas'][0]['tipo_doc']);
        $this->assertSame('20111222', $modal['consultas'][1]['nro_doc']);
        $this->assertSame('DNI del tutor', $modal['consultas'][1]['origen']);
        $this->assertTrue($modal['tiene_deuda']);
        $this->assertFalse($modal['puede_emitir']);
        $this->assertSame('$ 1.500,50', $modal['estudiante'][0]['saldo_texto']);
        $this->assertTrue($modal['encontrado_estudiante']);
        $this->assertSame('Perez', $modal['estudiante'][0]['apellido']);
        $this->assertSame('Juan', $modal['estudiante'][0]['nombre']);
    }

    public function test_respuesta_con_envoltorio_items(): void
    {
        Http::fake([
            'pau-develop-authserver.aulicatest.com.ar/externalauth/authenticate' => Http::response([
                'accessToken' => 'tok',
                'refreshToken' => 'ref',
                'expirationDate' => (string) (time() + 3600),
            ]),
            'pau-develop-externalapi.aulicatest.com.ar/alumnos/ctacte/saldos' => Http::response([
                'items' => [
                    [
                        'idPersona' => 331896,
                        'saldo' => 1237000.0,
                        'nroDoc' => '52054290',
                        'tipoDoc' => 'DNI',
                        'nombre' => 'JOAQUIN',
                        'apellido' => 'MOLINA',
                    ],
                ],
            ]),
        ]);

        $resultado = (new AulicaDeudaConsulta)->paraDnis('52054290', '27296626');

        $this->assertTrue($resultado->consultaOk);
        $this->assertTrue($resultado->tieneDeuda());
        $this->assertCount(1, $resultado->estudiante);
        $this->assertSame('MOLINA', $resultado->estudiante[0]->apellido);
        $this->assertSame('JOAQUIN', $resultado->estudiante[0]->nombre);
        $this->assertSame('52054290', $resultado->estudiante[0]->nroDoc);
        $this->assertEqualsWithDelta(1237000.0, $resultado->saldoEstudiante(), 0.01);

        $modal = $resultado->paraModal();
        $this->assertTrue($modal['encontrado_estudiante']);
        $this->assertFalse($modal['puede_emitir']);
        $this->assertSame('MOLINA', $modal['estudiante'][0]['apellido']);
        $this->assertSame('JOAQUIN', $modal['estudiante'][0]['nombre']);
    }

    public function test_404_significa_sin_deuda(): void
    {
        Http::fake([
            'pau-develop-authserver.aulicatest.com.ar/externalauth/authenticate' => Http::response([
                'accessToken' => 'tok',
                'refreshToken' => 'ref',
                'expirationDate' => (string) (time() + 3600),
            ]),
            'pau-develop-externalapi.aulicatest.com.ar/alumnos/ctacte/saldos' => Http::response(['mensaje' => 'No se encontró'], 404),
        ]);

        $resultado = (new AulicaDeudaConsulta)->paraDnis('30111222', '20111222');

        $this->assertTrue($resultado->consultaOk);
        $this->assertFalse($resultado->tieneDeuda());
        $this->assertSame('', $resultado->mensajeVisible());

        $modal = $resultado->paraModal();
        $this->assertTrue($modal['puede_emitir']);
        $this->assertFalse($modal['encontrado_estudiante']);
        $this->assertSame([], $modal['estudiante']);
        $this->assertSame([], $modal['grupo_familiar']);
        $this->assertStringContainsString('no encontró al estudiante', $modal['mensaje']);
    }

    public function test_saldo_cero_no_es_deuda(): void
    {
        $persona = AulicaSaldoPersona::desdeRespuesta([
            'idPersona' => 1,
            'saldo' => 0,
            'nroDoc' => '30111222',
            'nombre' => 'Juan',
            'apellido' => 'Perez',
        ]);

        $this->assertFalse($persona->tieneDeuda());
        $this->assertSame('$ 0,00', $persona->saldoFormateado());
    }

    public function test_mapea_campos_en_pascal_case(): void
    {
        $persona = AulicaSaldoPersona::desdeRespuesta([
            'IdPersona' => 9,
            'Saldo' => '250,50',
            'NroDoc' => '30111222',
            'TipoDoc' => 'DNI',
            'Nombre' => 'LUCIA',
            'Apellido' => 'GARCIA',
        ]);

        $this->assertSame(9, $persona->idPersona);
        $this->assertEqualsWithDelta(250.50, $persona->saldo, 0.01);
        $this->assertSame('30111222', $persona->nroDoc);
        $this->assertSame('LUCIA', $persona->nombre);
        $this->assertSame('GARCIA', $persona->apellido);
        $this->assertSame('GARCIA, LUCIA', $persona->nombreCompleto());
    }

    /**
     * @param  list<array<string, mixed>>  $saldos
     */
    private function fakeAulica(array $saldos): void
    {
        Http::fake([
            'pau-develop-authserver.aulicatest.com.ar/externalauth/authenticate' => Http::response([
                'accessToken' => 'tok',
                'refreshToken' => 'ref',
                'expirationDate' => (string) (time() + 3600),
            ]),
            'pau-develop-externalapi.aulicatest.com.ar/alumnos/ctacte/saldos' => Http::response($saldos),
        ]);
    }
}
