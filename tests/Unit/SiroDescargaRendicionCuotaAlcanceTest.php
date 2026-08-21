<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionCuotaAlcance;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionCuotaAlcanceTest extends TestCase
{
    public function test_por_id_cero_devuelve_null(): void
    {
        $this->assertNull(SiroDescargaRendicionCuotaAlcance::porId(0));
        $this->assertNull(SiroDescargaRendicionCuotaAlcance::porId(-1));
    }

    public function test_por_legajo_y_cuota_invalidos_devuelve_null(): void
    {
        $this->assertNull(SiroDescargaRendicionCuotaAlcance::porLegajoYCuota(0, 88));
        $this->assertNull(SiroDescargaRendicionCuotaAlcance::porLegajoYCuota(100, 0));
    }
}
