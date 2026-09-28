<?php

namespace App\Support\Mora;

use App\Models\Legajo;
use App\Support\OrdenAlfabeticoEstudiante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Estudiantes matriculados del ciclo activo para emitir la constancia de libre deuda.
 */
final class LibreDeudaFamiliarListado
{
    public const POR_PAGINA = 50;

    /**
     * @return LengthAwarePaginator<int, Legajo>
     */
    public static function listarEstudiantes(string $termino = '', int $idNivel = 0, bool $soloSinDeuda = false): LengthAwarePaginator
    {
        $idNivel = EstadoDeudaEstudianteListado::normalizarIdNivel($idNivel);

        $query = EstadoDeudaEstudianteListado::consultarEstudiantes($termino, $idNivel, false);

        if ($soloSinDeuda) {
            $query->whereDoesntHave('cuotasGeneradas', function (Builder $cuota) {
                $cuota->where('faltapa', '>', 0)
                    ->where('importe', '>', 0);
            });
        }

        $query->getQuery()->orders = [];
        OrdenAlfabeticoEstudiante::orderBy($query, 'apellido', 'nombre');
        $query->orderBy('id');

        return $query
            ->paginate(self::POR_PAGINA)
            ->withQueryString();
    }
}
