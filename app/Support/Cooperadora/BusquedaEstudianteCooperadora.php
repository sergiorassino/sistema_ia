<?php

namespace App\Support\Cooperadora;

use App\Models\Legajo;
use App\Models\Matricula;
use App\Models\Terlec;
use App\Support\MatriculaNivelEstilo;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda y datos de estudiantes para Cooperadora (sin dependencia de aranceles / cuotasbecas).
 */
final class BusquedaEstudianteCooperadora
{
    public static function buscarLegajos(string $termino, int $porPagina = 20): LengthAwarePaginator
    {
        $query = Legajo::query()
            ->whereHas('matriculas', function (Builder $q) {
                SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($q, 'idNivel');
            });

        $termino = trim($termino);
        if ($termino !== '') {
            $query->buscar($termino);
        }

        return $query
            ->with([
                'matriculas' => function ($q) {
                    $q->with(self::relacionesMatricula())
                        ->orderByDesc('idTerlec')
                        ->orderByDesc('id');
                    SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($q, 'idNivel');
                },
            ])
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate($porPagina, ['id', 'apellido', 'nombre', 'dni', 'legajo']);
    }

    public static function legajo(int $idLegajo): ?Legajo
    {
        return Legajo::query()
            ->whereKey($idLegajo)
            ->whereHas('matriculas', function (Builder $q) {
                SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($q, 'idNivel');
            })
            ->first(['id', 'apellido', 'nombre', 'dni', 'legajo']);
    }

    public static function matriculaActiva(int $idLegajo): ?Matricula
    {
        $idTerlec = (int) schoolCtx()->idTerlec;

        $query = Matricula::query()
            ->where('idLegajos', $idLegajo)
            ->where('idTerlec', $idTerlec)
            ->where(function ($q) {
                $q->whereNull('fechaBaja')
                    ->orWhere('fechaBaja', '0000-00-00')
                    ->orWhere('fechaBaja', '');
            });

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNivel');

        return $query
            ->with(self::relacionesMatricula())
            ->orderByDesc('id')
            ->first();
    }

    public static function esHermanoCooperadora(int $idLegajo): bool
    {
        $matricula = self::matriculaActiva($idLegajo);

        return $matricula !== null && (int) ($matricula->coop_es_hermano ?? 0) === 1;
    }

    /**
     * @return array{
     *     curso: string,
     *     nivel: string,
     *     tieneMatriculaActual: bool,
     *     anoUltimaMatricula: string,
     *     nivelEtiqueta: string,
     *     claseChipNivel: string,
     *     esHermanoCooperadora: bool
     * }
     */
    public static function datosListadoBusqueda(Legajo $legajo): array
    {
        $idTerlec = (int) schoolCtx()->idTerlec;
        $ultima = self::ultimaMatriculaDesdeLegajo($legajo);
        $matReferencia = self::matriculaReferenciaListado($legajo);
        $nivelNombre = trim((string) ($matReferencia?->nivel?->nivel ?? ''));
        $datosMat = self::datosDesdeMatricula($matReferencia);

        return array_merge($datosMat, [
            'tieneMatriculaActual' => self::tieneMatriculaCicloActivo($legajo, $idTerlec),
            'anoUltimaMatricula' => self::anoTerlecMatricula($ultima),
            'nivelEtiqueta' => $nivelNombre !== '' ? $nivelNombre : '—',
            'claseChipNivel' => MatriculaNivelEstilo::claseChipPorNombreNivel($nivelNombre),
            'esHermanoCooperadora' => (int) ($matReferencia?->coop_es_hermano ?? 0) === 1
                && (int) ($matReferencia?->idTerlec ?? 0) === $idTerlec,
        ]);
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

    private static function tieneMatriculaCicloActivo(Legajo $legajo, int $idTerlec): bool
    {
        if ($legajo->relationLoaded('matriculas')) {
            return $legajo->matriculas->contains(
                fn (Matricula $m) => (int) $m->idTerlec === $idTerlec,
            );
        }

        $query = Matricula::query()
            ->where('idLegajos', (int) $legajo->id)
            ->where('idTerlec', $idTerlec);

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNivel');

        return $query->exists();
    }

    private static function matriculaReferenciaListado(Legajo $legajo): ?Matricula
    {
        $idTerlec = (int) schoolCtx()->idTerlec;

        if ($legajo->relationLoaded('matriculas') && $legajo->matriculas->isNotEmpty()) {
            return $legajo->matriculas->firstWhere('idTerlec', $idTerlec)
                ?? $legajo->matriculas->first();
        }

        return self::matriculaActiva((int) $legajo->id) ?? self::ultimaMatricula((int) $legajo->id);
    }

    private static function ultimaMatriculaDesdeLegajo(Legajo $legajo): ?Matricula
    {
        if ($legajo->relationLoaded('matriculas') && $legajo->matriculas->isNotEmpty()) {
            return $legajo->matriculas->first();
        }

        return self::ultimaMatricula((int) $legajo->id);
    }

    private static function ultimaMatricula(int $idLegajo): ?Matricula
    {
        $query = Matricula::query()->where('idLegajos', $idLegajo);

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNivel');

        return $query
            ->with(self::relacionesMatricula())
            ->orderByDesc('idTerlec')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{curso: string, nivel: string}
     */
    private static function datosDesdeMatricula(?Matricula $mat): array
    {
        if ($mat === null) {
            return ['curso' => '—', 'nivel' => '—'];
        }

        $curso = trim((string) ($mat->curso?->nombreParaListado() ?? $mat->curso?->cursec ?? ''));
        $nivel = trim((string) ($mat->nivel?->nivel ?? ''));

        return [
            'curso' => $curso !== '' ? mb_strtoupper($curso) : '—',
            'nivel' => $nivel !== '' ? mb_strtoupper($nivel) : '—',
        ];
    }

    private static function anoTerlecMatricula(?Matricula $mat): string
    {
        if ($mat === null) {
            return '—';
        }

        if ($mat->relationLoaded('terlec') && $mat->terlec !== null) {
            $ano = trim((string) ($mat->terlec->ano ?? ''));

            return $ano !== '' ? $ano : '—';
        }

        $terlec = Terlec::query()->find((int) $mat->idTerlec, ['id', 'ano']);
        $ano = trim((string) ($terlec->ano ?? ''));

        return $ano !== '' ? $ano : '—';
    }

    /**
     * @return array<int, string>
     */
    private static function relacionesMatricula(): array
    {
        return [
            'terlec:id,ano',
            'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
            'curso.curplan:id,curPlanCurso',
            'curso.turnoClase:id,nombre',
            'nivel:id,nivel',
        ];
    }
}
