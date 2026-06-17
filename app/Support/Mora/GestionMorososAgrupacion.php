<?php

namespace App\Support\Mora;

use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Models\CuotaGenerada;
use Illuminate\Support\Collection;

/**
 * Agrupa cuotas adeudadas por familia real o, si no hay familia asignada, por estudiante (legajo).
 */
final class GestionMorososAgrupacion
{
    /**
     * @param  Collection<int, CuotaGenerada>  $registros
     * @return Collection<string, Collection<int, CuotaGenerada>>
     */
    public static function porFamiliaOEstudiante(Collection $registros): Collection
    {
        return $registros->groupBy(fn (CuotaGenerada $r) => self::claveGrupo($r));
    }

    public static function claveGrupo(CuotaGenerada $registro): string
    {
        $idFamilia = (int) ($registro->legajo?->idFamilias ?? 0);
        if ($idFamilia > 0 && $idFamilia !== LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR) {
            return 'f:'.$idFamilia;
        }

        $idLegajo = (int) ($registro->idLegajos ?? 0);
        if ($idLegajo > 0) {
            return 'l:'.$idLegajo;
        }

        return 'x:0';
    }

    public static function claveEsValida(string $clave): bool
    {
        return $clave !== 'x:0';
    }

    /**
     * @param  Collection<int, CuotaGenerada>  $items
     */
    public static function tituloSeccion(Collection $items): string
    {
        $clave = self::claveGrupo($items->first() ?? new CuotaGenerada());

        if (str_starts_with($clave, 'l:')) {
            $legajo = $items->first()?->legajo;
            $nombre = mb_strtoupper(trim(
                trim((string) ($legajo?->apellido ?? '')).' '.trim((string) ($legajo?->nombre ?? '')),
            ));

            return $nombre !== '' ? 'Estudiante: '.$nombre : 'Estudiante sin familia asignada';
        }

        $familia = $items->first()?->legajo?->familia;
        $apellido = trim((string) ($familia?->apellido ?? ''));
        $responsable = trim((string) ($familia?->responsable ?? ''));

        return mb_strtoupper(trim(
            'Familia / Responsable: '.$apellido
            .($apellido !== '' && $responsable !== '' ? ' - ' : '')
            .$responsable,
        ));
    }

    /**
     * @param  Collection<int, CuotaGenerada>  $items
     */
    public static function familiaLinea(Collection $items): string
    {
        $clave = self::claveGrupo($items->first() ?? new CuotaGenerada());

        if (str_starts_with($clave, 'l:')) {
            $legajo = $items->first()?->legajo;
            $nombre = trim(
                trim((string) ($legajo?->apellido ?? '')).' '.trim((string) ($legajo?->nombre ?? '')),
            );

            return $nombre !== '' ? mb_strtoupper($nombre) : '—';
        }

        $familia = $items->first()?->legajo?->familia;
        $apellido = trim((string) ($familia?->apellido ?? ''));
        $responsable = trim((string) ($familia?->responsable ?? ''));

        $linea = trim(
            $apellido
            .($apellido !== '' && $responsable !== '' ? ' - ' : '')
            .$responsable,
        );

        return $linea !== '' ? mb_strtoupper($linea) : '—';
    }

    public static function claveOrden(CuotaGenerada $registro): string
    {
        $clave = self::claveGrupo($registro);

        if (str_starts_with($clave, 'f:')) {
            $apellido = mb_strtoupper(trim((string) ($registro->legajo?->familia?->apellido ?? '')));

            return '0:'.$apellido.':'.$clave;
        }

        $apellido = mb_strtoupper(trim((string) ($registro->legajo?->apellido ?? '')));
        $nombre = mb_strtoupper(trim((string) ($registro->legajo?->nombre ?? '')));

        return '1:'.$apellido.':'.$nombre.':'.$clave;
    }
}
