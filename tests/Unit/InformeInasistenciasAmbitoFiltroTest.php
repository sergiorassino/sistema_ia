<?php

namespace Tests\Unit;

use App\Support\InformeInasistencias;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InformeInasistenciasAmbitoFiltroTest extends TestCase
{
    public function test_ambito_desconocido_queda_en_todas(): void
    {
        $this->assertSame(InformeInasistencias::AMBITO_TODAS, InformeInasistencias::ambitoFiltroValido(null));
        $this->assertSame(InformeInasistencias::AMBITO_TODAS, InformeInasistencias::ambitoFiltroValido(''));
        $this->assertSame(InformeInasistencias::AMBITO_TODAS, InformeInasistencias::ambitoFiltroValido('otro'));
        $this->assertSame(InformeInasistencias::AMBITO_CLASE, InformeInasistencias::ambitoFiltroValido('CLASE'));
        $this->assertSame(InformeInasistencias::AMBITO_EDUCACION_FISICA, InformeInasistencias::ambitoFiltroValido('edfis'));
    }

    public function test_etiquetas_de_ambito(): void
    {
        $this->assertSame('Todas', InformeInasistencias::etiquetaFiltroAmbito('todas'));
        $this->assertSame('A clase', InformeInasistencias::etiquetaFiltroAmbito('clase'));
        $this->assertSame('A educación física', InformeInasistencias::etiquetaFiltroAmbito('edfis'));
    }

    public function test_rango_invertido_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);

        InformeInasistencias::rangoSolicitado('2026-09-30', '2026-03-01');
    }

    public function test_rango_vacio_no_acota(): void
    {
        $this->assertSame([null, null], InformeInasistencias::rangoSolicitado('', null));
        $this->assertSame(['2026-03-01', null], InformeInasistencias::rangoSolicitado('2026-03-01', ''));
    }
}
