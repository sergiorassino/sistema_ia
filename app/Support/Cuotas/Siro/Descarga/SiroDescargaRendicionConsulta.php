<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\PlanillaDescargaCuota;
use App\Models\RendicionRoela;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Consultas del ABM de planillas de descarga SIRO.
 */
final class SiroDescargaRendicionConsulta
{
    public static function ultimoNroPlanilla(): int
    {
        return (int) (PlanillaDescargaCuota::query()->max('nroPlanilla') ?? 0);
    }

    public static function sugerirNroPlanilla(): int
    {
        return self::ultimoNroPlanilla() + 1;
    }

    public function listarPlanillas(string $busqueda = '', int $porPagina = 20): LengthAwarePaginator
    {
        $q = PlanillaDescargaCuota::query()->orderByDesc('nroPlanilla');

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            if (ctype_digit($busqueda)) {
                $q->where('nroPlanilla', (int) $busqueda);
            } else {
                $q->where('nombreArchivo', 'like', '%'.$busqueda.'%');
            }
        }

        return $q->paginate($porPagina);
    }

    public function planillaPorNro(int $nroPlanilla): ?PlanillaDescargaCuota
    {
        return PlanillaDescargaCuota::query()->where('nroPlanilla', $nroPlanilla)->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, RendicionRoela>
     */
    public function rendicionesDePlanilla(int $nroPlanilla)
    {
        return RendicionRoela::query()
            ->with(['legajo', 'cuota', 'curso.nivel', 'tipoPago', 'beca'])
            ->where('nroPlanilla', $nroPlanilla)
            ->orderBy('id')
            ->get();
    }

    public function totalCobradoPlanilla(int $nroPlanilla): float
    {
        return round((float) RendicionRoela::query()
            ->where('nroPlanilla', $nroPlanilla)
            ->sum('pagado'), 2);
    }
}
