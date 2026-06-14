<?php

namespace App\Support\Cooperadora;

use App\Models\Legajo;
use App\Models\Matricula;
use App\Models\Terlec;
use App\Support\Cuotas\GestionAranceles;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class BusquedaEstudianteCooperadora
{
    public static function buscarLegajos(string $termino): LengthAwarePaginator
    {
        return GestionAranceles::buscarLegajos($termino);
    }

    public static function legajo(int $idLegajo): ?Legajo
    {
        return GestionAranceles::legajoParaGestion($idLegajo);
    }

    public static function matriculaActiva(int $idLegajo): ?Matricula
    {
        $idTerlec = (int) schoolCtx()->idTerlec;

        return Matricula::query()
            ->with(['curso:id,cursec,c,s', 'nivel:id,nivel'])
            ->where('idLegajos', $idLegajo)
            ->where('idTerlec', $idTerlec)
            ->where(function ($q) {
                $q->whereNull('fechaBaja')
                    ->orWhere('fechaBaja', '0000-00-00')
                    ->orWhere('fechaBaja', '');
            })
            ->orderByDesc('id')
            ->first();
    }

    public static function etiquetaCurso(?Matricula $matricula): string
    {
        if ($matricula === null || $matricula->curso === null) {
            return '';
        }

        $curso = $matricula->curso;
        $partes = array_filter([
            trim((string) ($curso->cursec ?? '')),
            trim((string) ($curso->c ?? '')),
            trim((string) ($curso->s ?? '')),
        ]);

        return implode(' ', $partes);
    }

    public static function nombrePagadorDesdeLegajo(Legajo $legajo): string
    {
        return mb_strtoupper(trim($legajo->apellido.', '.$legajo->nombre), 'UTF-8');
    }

    public static function anioCicloActivo(): int
    {
        return CooperadoraConfig::anioVigente();
    }

    public static function etiquetaAnioCiclo(): string
    {
        $idTerlec = (int) schoolCtx()->idTerlec;
        $ano = Terlec::query()->whereKey($idTerlec)->value('ano');

        return $ano ? (string) $ano : (string) now()->year;
    }
}
