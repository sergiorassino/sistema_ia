<?php

namespace App\Support\Cuotas\Siro;

use App\Models\Cuota;
use App\Models\Curso;
use App\Models\Legajo;
use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
use App\Support\Cuotas\ListadoEstudiantesPorCuotaDatos;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Filtros del módulo «Subida base de deuda SIRO» (legacy Scriptcase).
 */
final class SiroSubidaBaseDeudaFiltros
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizarDesdeLivewire(array $input): array
    {
        $chkCuotas = (bool) ($input['chkCuotas'] ?? false);
        $idsCuotas = self::normalizarIds($input['idsCuotas'] ?? []);
        if ($chkCuotas && $idsCuotas === []) {
            throw ValidationException::withMessages(['idsCuotas' => 'Seleccione al menos una cuota.']);
        }
        self::validarIdsCuotas($idsCuotas);

        $chkCursos = (bool) ($input['chkCursos'] ?? false);
        $idsCursos = self::normalizarIds($input['idsCursos'] ?? []);
        if ($chkCursos && $idsCursos === []) {
            throw ValidationException::withMessages(['idsCursos' => 'Seleccione al menos un curso.']);
        }
        self::validarIdsCursos($idsCursos);

        $chkExcluirAlumnos = (bool) ($input['chkExcluirAlumnos'] ?? false);
        $idsExcluirAlumnos = self::normalizarIds($input['idsExcluirAlumnos'] ?? []);
        if ($chkExcluirAlumnos && $idsExcluirAlumnos === []) {
            throw ValidationException::withMessages(['idsExcluirAlumnos' => 'Seleccione al menos un alumno a excluir.']);
        }
        self::validarIdsLegajos($idsExcluirAlumnos);

        $chkIncluirAlumnos = (bool) ($input['chkIncluirAlumnos'] ?? false);
        $idsIncluirAlumnos = self::normalizarIds($input['idsIncluirAlumnos'] ?? []);
        if ($chkIncluirAlumnos) {
            if (! $chkCuotas || $idsCuotas === []) {
                throw ValidationException::withMessages([
                    'chkIncluirAlumnos' => 'Para incluir alumnos debe activar el filtro por cuota y elegir al menos una.',
                ]);
            }
            if ($idsIncluirAlumnos === []) {
                throw ValidationException::withMessages(['idsIncluirAlumnos' => 'Seleccione al menos un alumno a incluir.']);
            }
            self::validarIdsLegajos($idsIncluirAlumnos);
        }

        if ($chkIncluirAlumnos && $chkExcluirAlumnos) {
            throw ValidationException::withMessages([
                'chkExcluirAlumnos' => 'No puede combinar «Incluir alumnos» con «Excluir alumnos».',
            ]);
        }

        return [
            'chkCuotas' => $chkCuotas,
            'idsCuotas' => $idsCuotas,
            'chkCursos' => $chkCursos,
            'idsCursos' => $idsCursos,
            'chkExcluirAlumnos' => $chkExcluirAlumnos,
            'idsExcluirAlumnos' => $idsExcluirAlumnos,
            'chkIncluirAlumnos' => $chkIncluirAlumnos,
            'idsIncluirAlumnos' => $idsIncluirAlumnos,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarAConsulta(Builder $query, array $filtros): void
    {
        $query->where('cuotasgeneradas.faltapa', '>', 0)
            ->where('cuotasgeneradas.importe', '>', 0);

        self::aplicarVenc3Vigente($query);

        if ($filtros['chkIncluirAlumnos'] ?? false) {
            $idsAlumnos = (array) ($filtros['idsIncluirAlumnos'] ?? []);
            $idsCuotas = (array) ($filtros['idsCuotas'] ?? []);
            if ($idsAlumnos !== []) {
                $query->whereIn('cuotasgeneradas.idLegajos', $idsAlumnos);
            }
            if ($idsCuotas !== []) {
                $query->whereIn('cuotasgeneradas.idCuotas', $idsCuotas);
            }

            return;
        }

        if ($filtros['chkCuotas'] ?? false) {
            $ids = (array) ($filtros['idsCuotas'] ?? []);
            if ($ids !== []) {
                $query->whereIn('cuotasgeneradas.idCuotas', $ids);
            }
        }

        if ($filtros['chkCursos'] ?? false) {
            $ids = (array) ($filtros['idsCursos'] ?? []);
            if ($ids !== []) {
                $query->whereIn('cuotasgeneradas.idCursos', $ids);
            }
        }

        if ($filtros['chkExcluirAlumnos'] ?? false) {
            $ids = (array) ($filtros['idsExcluirAlumnos'] ?? []);
            if ($ids !== []) {
                $query->whereNotIn('cuotasgeneradas.idLegajos', $ids);
            }
        }
    }

    /**
     * @return Collection<int, Cuota>
     */
    public static function cuotasParaSelector(): Collection
    {
        return Cuota::query()
            ->join('terlec', 'terlec.id', '=', 'cuotas.idTerlec')
            ->whereHas('cuotasGeneradas', function (Builder $q): void {
                $q->where('faltapa', '>', 0);
                self::aplicarVenc3Vigente($q, 'venc3');
            })
            ->selectRaw('cuotas.id, cuotas.nombre, cuotas.orden, terlec.ano as terlec_ano')
            ->orderBy('terlec.ano')
            ->orderBy('cuotas.orden')
            ->orderBy('cuotas.id')
            ->get();
    }

    /**
     * Excluye cuotas generadas cuyo 3.er vencimiento ya pasó (o no tiene fecha válida).
     */
    public static function aplicarVenc3Vigente(Builder $query, string $column = 'cuotasgeneradas.venc3'): void
    {
        $hoy = Carbon::today()->format('Y-m-d');

        $query->where($column, '>', '0000-00-00')
            ->whereDate($column, '>=', $hoy);
    }

    /**
     * @return Collection<int, Curso>
     */
    public static function cursosParaSelector(): Collection
    {
        return GeneracionMasivaCuotasConsulta::cursosEnContexto();
    }

    /**
     * @return Collection<int, Legajo>
     */
    public static function alumnosParaSelector(): Collection
    {
        return Legajo::query()
            ->whereHas('cuotasGeneradas', fn (Builder $q) => $q->where('faltapa', '>', 0))
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->orderBy('id')
            ->get(['id', 'apellido', 'nombre', 'dni']);
    }

    /**
     * @param  array<int|string>|string  $ids
     * @return list<int>
     */
    public static function normalizarIds(array|string $ids): array
    {
        if (is_string($ids)) {
            $ids = array_filter(explode(';', $ids), fn ($v) => trim($v) !== '');
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($v) => (int) $v,
            (array) $ids,
        ), fn ($id) => $id > 0)));
    }

    /**
     * @param  list<int>  $ids
     */
    private static function validarIdsCuotas(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $permitidos = self::cuotasParaSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($ids as $id) {
            if (! in_array($id, $permitidos, true)) {
                throw ValidationException::withMessages(['idsCuotas' => 'Cuota no válida.']);
            }
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private static function validarIdsCursos(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $permitidos = self::cursosParaSelector()->pluck('Id')->map(fn ($id) => (int) $id)->all();
        foreach ($ids as $id) {
            if (! in_array($id, $permitidos, true)) {
                throw ValidationException::withMessages(['idsCursos' => 'Curso no válido.']);
            }
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private static function validarIdsLegajos(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $permitidos = self::alumnosParaSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($ids as $id) {
            if (! in_array($id, $permitidos, true)) {
                throw ValidationException::withMessages(['idsLegajos' => 'Estudiante no válido.']);
            }
        }
    }
}
