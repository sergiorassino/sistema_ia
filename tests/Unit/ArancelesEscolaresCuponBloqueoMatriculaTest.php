<?php

namespace Tests\Unit;

use App\Models\Cuota;
use App\Models\CuotaGenerada;
use App\Models\Matricula;
use App\Support\Alumnos\ArancelesEscolares;
use PHPUnit\Framework\TestCase;

class ArancelesEscolaresCuponBloqueoMatriculaTest extends TestCase
{
    public function test_cuota_mensual_se_descarga_aunque_la_sesion_este_bloqueada(): void
    {
        $cuota = new CuotaGenerada(['idCuotastipo' => 1]);

        $this->assertFalse(ArancelesEscolares::cuponBloqueadoPorMatricula($cuota, true));
    }

    public function test_reserva_no_se_descarga_con_bloqueo_de_la_sesion(): void
    {
        $cuota = new CuotaGenerada(['idCuotastipo' => 2]);

        $this->assertTrue(ArancelesEscolares::cuponBloqueadoPorMatricula($cuota, true));
    }

    public function test_matricula_no_se_descarga_con_bloqueo_administrativo_de_su_ciclo(): void
    {
        $cuota = new CuotaGenerada(['idCuotastipo' => 3]);
        $cuota->setRelation('matricula', new Matricula([
            'bloqmatr' => false,
            'bloqadmi' => true,
        ]));

        $this->assertTrue(ArancelesEscolares::cuponBloqueadoPorMatricula($cuota, false));
    }

    public function test_reserva_sin_bloqueos_se_puede_descargar(): void
    {
        $cuota = new CuotaGenerada(['idCuotastipo' => 2]);
        $cuota->setRelation('matricula', new Matricula([
            'bloqmatr' => false,
            'bloqadmi' => false,
        ]));

        $this->assertFalse(ArancelesEscolares::cuponBloqueadoPorMatricula($cuota, false));
    }

    public function test_tipo_sale_de_la_plantilla_si_la_generada_no_lo_tiene(): void
    {
        $cuota = new CuotaGenerada(['idCuotastipo' => 0]);
        $cuota->setRelation('cuota', new Cuota(['idCuotastipo' => 2]));
        $cuota->setRelation('matricula', new Matricula([
            'bloqmatr' => true,
            'bloqadmi' => true,
        ]));

        $this->assertTrue(ArancelesEscolares::cuponBloqueadoPorMatricula($cuota, false));
    }

    public function test_cuota_mensual_se_descarga_aunque_haya_deuda_familiar(): void
    {
        $cuota = new CuotaGenerada(['idCuotastipo' => 1]);

        $this->assertFalse(ArancelesEscolares::cuponBloqueadoPorDeudaFamiliar($cuota, true));
    }

    public function test_reserva_y_matricula_no_se_descargan_con_deuda_familiar(): void
    {
        $reserva = new CuotaGenerada(['idCuotastipo' => 2]);
        $matricula = new CuotaGenerada(['idCuotastipo' => 3]);

        $this->assertTrue(ArancelesEscolares::cuponBloqueadoPorDeudaFamiliar($reserva, true));
        $this->assertTrue(ArancelesEscolares::cuponBloqueadoPorDeudaFamiliar($matricula, true));
        $this->assertFalse(ArancelesEscolares::cuponBloqueadoPorDeudaFamiliar($reserva, false));
    }

    public function test_gestion_no_impide_una_cuota_mensual(): void
    {
        $cuota = new CuotaGenerada(['idCuotastipo' => 1]);

        $this->assertNull(ArancelesEscolares::impedimentoCuponGestion($cuota, true, true, 'Bloqueo'));
    }

    public function test_gestion_impide_reserva_por_bloqueo_antes_que_por_deuda(): void
    {
        $cuota = new CuotaGenerada(['idCuotastipo' => 2]);

        $impedimento = ArancelesEscolares::impedimentoCuponGestion($cuota, true, true, 'Cartel del nivel.');

        $this->assertSame('bloqueo', $impedimento['motivo'] ?? null);
        $this->assertSame('Cartel del nivel.', $impedimento['mensaje'] ?? null);
    }

    public function test_gestion_impide_matricula_por_deuda_familiar(): void
    {
        $cuota = new CuotaGenerada(['idCuotastipo' => 3]);
        $cuota->setRelation('matricula', new Matricula([
            'bloqmatr' => false,
            'bloqadmi' => false,
        ]));

        $impedimento = ArancelesEscolares::impedimentoCuponGestion($cuota, false, true);

        $this->assertSame('deuda', $impedimento['motivo'] ?? null);
        $this->assertSame(ArancelesEscolares::MENSAJE_CUPON_DEUDA_FAMILIAR_GESTION, $impedimento['mensaje'] ?? null);
    }

    public function test_mensaje_de_sesion_bloqueada_es_el_cartel_configurado(): void
    {
        $cuota = new CuotaGenerada(['idCuotastipo' => 2]);

        $mensaje = ArancelesEscolares::mensajeCuponBloqueadoPorMatricula(
            $cuota,
            true,
            'Cartel pedagógico del nivel.',
        );

        $this->assertSame('Cartel pedagógico del nivel.', $mensaje);
    }
}
