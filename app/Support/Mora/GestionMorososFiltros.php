<?php

namespace App\Support\Mora;

use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Models\Cuota;
use App\Models\CuotasBeca;
use App\Models\Curso;
use App\Models\Familia;
use App\Models\Legajo;
use App\Models\Terlec;
use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
use App\Support\Cuotas\ListadoEstudiantesPorCuotaDatos;
use App\Support\SchoolAlcancePedagogico;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Filtros del listado de morosos (corregidos respecto del form legacy Scriptcase).
 *
 * Correcciones principales:
 * - Rango «1º venc.» filtra por {@see CuotaGenerada::venc1}, no por venc2.
 * - «Solo / excepto fuera de colegio» usa matrícula del ciclo activo (`idLegajos` + `idTerlec`).
 * - «Año» es el id de {@see Terlec}, no el número de año suelto.
 * - Conteo «más de / hasta X cuotas» solo considera cuotas con saldo (`faltapa` > 0).
 */
final class GestionMorososFiltros
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizarDesdeLivewire(array $input): array
    {
        $fechaCalculo = self::parseFecha($input['fechaCalculo'] ?? null) ?? Carbon::today();

        $chkFamilia = (bool) ($input['chkFamilia'] ?? false);
        $idFamilia = (int) ($input['idFamilia'] ?? 0);
        if ($chkFamilia) {
            if ($idFamilia <= 0 || $idFamilia === LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR) {
                throw ValidationException::withMessages(['idFamilia' => 'Seleccione una familia.']);
            }
            if (Familia::query()->whereKey($idFamilia)->doesntExist()) {
                throw ValidationException::withMessages(['idFamilia' => 'Familia no válida.']);
            }
        }

        $chkAlumno = (bool) ($input['chkAlumno'] ?? false);
        $idAlumno = (int) ($input['idAlumno'] ?? 0);
        if ($chkAlumno) {
            if ($idAlumno <= 0) {
                throw ValidationException::withMessages(['idAlumno' => 'Seleccione un estudiante.']);
            }
            if (Legajo::query()->whereKey($idAlumno)->doesntExist()) {
                throw ValidationException::withMessages(['idAlumno' => 'Estudiante no válido.']);
            }
        }

        $chkVencDesde = (bool) ($input['chkVencDesde'] ?? false);
        $vencDesde = self::parseFecha($input['vencDesde'] ?? null);
        if ($chkVencDesde && $vencDesde === null) {
            throw ValidationException::withMessages(['vencDesde' => 'Indique la fecha desde (1º vencimiento).']);
        }

        $chkVencHasta = (bool) ($input['chkVencHasta'] ?? false);
        $vencHasta = self::parseFecha($input['vencHasta'] ?? null);
        if ($chkVencHasta && $vencHasta === null) {
            throw ValidationException::withMessages(['vencHasta' => 'Indique la fecha hasta (1º vencimiento).']);
        }
        if ($chkVencDesde && $chkVencHasta && $vencDesde !== null && $vencHasta !== null && $vencHasta->lt($vencDesde)) {
            throw ValidationException::withMessages(['vencHasta' => 'La fecha hasta debe ser igual o posterior a la fecha desde.']);
        }

        $chkExcluir = (bool) ($input['chkExcluir'] ?? false);
        $idsExcluir = self::normalizarIds($input['idsExcluirCuotas'] ?? []);
        if ($chkExcluir && $idsExcluir === []) {
            throw ValidationException::withMessages(['idsExcluirCuotas' => 'Seleccione al menos una cuota a excluir.']);
        }
        self::validarIdsCuotas($idsExcluir);

        $chkCurso = (bool) ($input['chkCurso'] ?? false);
        $idsCursos = self::normalizarIds($input['idsCursos'] ?? []);
        if ($chkCurso && $idsCursos === []) {
            throw ValidationException::withMessages(['idsCursos' => 'Seleccione al menos un curso.']);
        }
        self::validarIdsCursos($idsCursos);

        $chkMasDe = (bool) ($input['chkMasDe'] ?? false);
        $masDe = (int) ($input['masDe'] ?? 0);
        if ($chkMasDe && $masDe < 0) {
            throw ValidationException::withMessages(['masDe' => 'Indique un número válido de cuotas.']);
        }

        $chkHasta = (bool) ($input['chkHasta'] ?? false);
        $hasta = (int) ($input['hasta'] ?? 0);
        if ($chkHasta && $hasta < 0) {
            throw ValidationException::withMessages(['hasta' => 'Indique un número válido de cuotas.']);
        }

        $soloFuera = (bool) ($input['chkSoloFuera'] ?? false);
        $exceptoFuera = (bool) ($input['chkExceptoFuera'] ?? false);
        if ($soloFuera && $exceptoFuera) {
            $exceptoFuera = false;
        }

        $chkAno = (bool) ($input['chkAno'] ?? false);
        $idTerlec = (int) ($input['idTerlec'] ?? 0);
        $terlecsPermitidos = Terlec::paraSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($chkAno) {
            if ($idTerlec < 1 || ! in_array($idTerlec, $terlecsPermitidos, true)) {
                throw ValidationException::withMessages(['idTerlec' => 'Año lectivo no válido.']);
            }
        }

        $chkSoloBecados = (bool) ($input['chkSoloBecados'] ?? false);
        $idsBecas = self::normalizarIds($input['idsBecas'] ?? []);
        if ($chkSoloBecados && $idsBecas === []) {
            throw ValidationException::withMessages(['idsBecas' => 'Seleccione al menos un tipo de beca.']);
        }
        self::validarIdsBecas($idsBecas);

        return [
            'fechaCalculo' => $fechaCalculo->format('Y-m-d'),
            'chkFamilia' => $chkFamilia,
            'idFamilia' => $idFamilia,
            'chkAlumno' => $chkAlumno,
            'idAlumno' => $idAlumno,
            'chkVencDesde' => $chkVencDesde,
            'vencDesde' => $vencDesde?->format('Y-m-d'),
            'chkVencHasta' => $chkVencHasta,
            'vencHasta' => $vencHasta?->format('Y-m-d'),
            'chkExcluir' => $chkExcluir,
            'idsExcluirCuotas' => $idsExcluir,
            'chkCurso' => $chkCurso,
            'idsCursos' => $idsCursos,
            'chkMasDe' => $chkMasDe,
            'masDe' => $masDe,
            'chkHasta' => $chkHasta,
            'hasta' => $hasta,
            'chkSoloFuera' => $soloFuera,
            'chkExceptoFuera' => $exceptoFuera,
            'chkAno' => $chkAno,
            'idTerlec' => $idTerlec,
            'chkSoloBecados' => $chkSoloBecados,
            'idsBecas' => $idsBecas,
        ];
    }

    /**
     * Indica si el usuario activó algún filtro opcional (además de la fecha de cálculo).
     *
     * @param  array<string, mixed>  $input  Crudos de Livewire o normalizados
     */
    public static function algunFiltroOpcionalActivo(array $input): bool
    {
        return (bool) ($input['chkFamilia'] ?? false)
            || (bool) ($input['chkAlumno'] ?? false)
            || (bool) ($input['chkVencDesde'] ?? false)
            || (bool) ($input['chkVencHasta'] ?? false)
            || (bool) ($input['chkExcluir'] ?? false)
            || (bool) ($input['chkCurso'] ?? false)
            || (bool) ($input['chkMasDe'] ?? false)
            || (bool) ($input['chkHasta'] ?? false)
            || (bool) ($input['chkSoloFuera'] ?? false)
            || (bool) ($input['chkExceptoFuera'] ?? false)
            || (bool) ($input['chkAno'] ?? false)
            || (bool) ($input['chkSoloBecados'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function puedeGenerarPdf(array $input): bool
    {
        try {
            self::normalizarDesdeLivewire($input);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarAConsulta(Builder $query, array $filtros): void
    {
        $query->where('cuotasgeneradas.faltapa', '>', 0)
            ->where('cuotasgeneradas.importe', '>', 0);

        if ($filtros['chkAno'] ?? false) {
            $query->where('cuotasgeneradas.idTerlec', (int) ($filtros['idTerlec'] ?? 0));
        }

        if ($filtros['chkVencDesde'] ?? false) {
            $query->whereDate('cuotasgeneradas.venc1', '>=', (string) ($filtros['vencDesde'] ?? ''));
        }

        if ($filtros['chkVencHasta'] ?? false) {
            $query->whereDate('cuotasgeneradas.venc1', '<=', (string) ($filtros['vencHasta'] ?? ''));
        }

        if ($filtros['chkExcluir'] ?? false) {
            $ids = (array) ($filtros['idsExcluirCuotas'] ?? []);
            if ($ids !== []) {
                $query->whereNotIn('cuotasgeneradas.idCuotas', $ids);
            }
        }

        if ($filtros['chkCurso'] ?? false) {
            $ids = (array) ($filtros['idsCursos'] ?? []);
            if ($ids !== []) {
                $query->whereIn('cuotasgeneradas.idCursos', $ids);
            }
        }

        if ($filtros['chkSoloBecados'] ?? false) {
            $ids = (array) ($filtros['idsBecas'] ?? []);
            if ($ids !== []) {
                $query->whereIn('cuotasgeneradas.idCuotasbecas', $ids);
            }
        }

        $query->whereHas('legajo', function (Builder $leg) use ($filtros) {
            if ($filtros['chkFamilia'] ?? false) {
                $leg->where('idFamilias', (int) ($filtros['idFamilia'] ?? 0));
            }

            if ($filtros['chkAlumno'] ?? false) {
                $leg->whereKey((int) ($filtros['idAlumno'] ?? 0));
            }

            $idTerlecActivo = (int) schoolCtx()->idTerlec;

            if ($filtros['chkSoloFuera'] ?? false) {
                $leg->whereDoesntHave('matriculas', function (Builder $m) use ($idTerlecActivo) {
                    $m->where('idTerlec', $idTerlecActivo);
                    SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($m, 'idNivel');
                });
            } elseif ($filtros['chkExceptoFuera'] ?? false) {
                $leg->whereHas('matriculas', function (Builder $m) use ($idTerlecActivo) {
                    $m->where('idTerlec', $idTerlecActivo);
                    SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($m, 'idNivel');
                });
            }

            if ($filtros['chkMasDe'] ?? false) {
                $leg->whereRaw(
                    '(SELECT COUNT(*) FROM cuotasgeneradas cg WHERE cg.idLegajos = legajos.id AND cg.faltapa > 0) > ?',
                    [(int) ($filtros['masDe'] ?? 0)],
                );
            }

            if ($filtros['chkHasta'] ?? false) {
                $leg->whereRaw(
                    '(SELECT COUNT(*) FROM cuotasgeneradas cg WHERE cg.idLegajos = legajos.id AND cg.faltapa > 0) <= ?',
                    [(int) ($filtros['hasta'] ?? 0)],
                );
            }
        });
    }

    /**
     * @return Collection<int, Familia>
     */
    public static function familiasParaSelector(): Collection
    {
        return Familia::query()
            ->whereKeyNot(LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR)
            ->whereHas('legajos', fn (Builder $q) => $q->whereHas('cuotasGeneradas', fn (Builder $cg) => $cg->where('faltapa', '>', 0)))
            ->orderBy('apellido')
            ->orderBy('id')
            ->get(['id', 'apellido', 'responsable']);
    }

    /**
     * @return Collection<int, Legajo>
     */
    public static function alumnosParaSelector(): Collection
    {
        return Legajo::query()
            ->whereHas('cuotasGeneradas', fn (Builder $cg) => $cg->where('faltapa', '>', 0))
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->orderBy('id')
            ->get(['id', 'apellido', 'nombre', 'dni', 'idFamilias']);
    }

    /**
     * @return Collection<int, Cuota>
     */
    public static function cuotasParaExcluir(): Collection
    {
        return ListadoEstudiantesPorCuotaDatos::cuotasParaSelector();
    }

    /**
     * @return Collection<int, Curso>
     */
    public static function cursosParaSelector(): Collection
    {
        return GeneracionMasivaCuotasConsulta::cursosEnContexto();
    }

    /**
     * @return Collection<int, CuotasBeca>
     */
    public static function becasParaSelector(): Collection
    {
        return CuotasBeca::query()
            ->orderBy('nombreBeca')
            ->orderBy('id')
            ->get(['id', 'nombreBeca']);
    }

    /**
     * @return Collection<int, Terlec>
     */
    public static function terlecsParaSelector(): Collection
    {
        return Terlec::paraSelector();
    }

    private static function parseFecha(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int|string>|string  $ids
     * @return list<int>
     */
    private static function normalizarIds(array|string $ids): array
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

        $permitidos = ListadoEstudiantesPorCuotaDatos::cuotasParaSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($ids as $id) {
            if (! in_array($id, $permitidos, true)) {
                throw ValidationException::withMessages(['idsExcluirCuotas' => 'Cuota no válida para excluir.']);
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
    private static function validarIdsBecas(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $permitidos = self::becasParaSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($ids as $id) {
            if (! in_array($id, $permitidos, true)) {
                throw ValidationException::withMessages(['idsBecas' => 'Tipo de beca no válido.']);
            }
        }
    }
}
