<?php

namespace App\Support\Seguimiento;

use App\Models\Curso;
use App\Models\Gabinete;
use App\Models\GabineteMarca;
use App\Models\GabineteTipo;
use App\Models\Matricula;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\OrdenAlfabeticoEstudiante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Consultas del seguimiento de gabinete de orientación (tablas legacy gabinete / gabinetetipo).
 */
final class GabineteOrientacion
{
    public const TABLA = 'gabinete';

    public const TABLA_TIPO = 'gabinetetipo';

    public const TABLA_MARCA = 'gabinetemarca';

    public static function tablasDisponibles(): bool
    {
        return Schema::hasTable(self::TABLA) && Schema::hasTable(self::TABLA_TIPO);
    }

    public static function tablaMarcaDisponible(): bool
    {
        return Schema::hasTable(self::TABLA_MARCA)
            && Schema::hasColumn(self::TABLA_MARCA, 'idLegajos')
            && Schema::hasColumn(self::TABLA_MARCA, 'color');
    }

    /** @return list<int> */
    public static function idsCondicionesSelector(): array
    {
        return ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::TODOS);
    }

    /** @return Collection<int, Curso> */
    public static function cursosDelContexto(): Collection
    {
        return Curso::query()
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'idTurnoClase', 'c', 's']);
    }

    public static function matriculaEnContexto(int $idMatricula): Matricula
    {
        /** @var Matricula $m */
        $m = Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->whereIn('idCondiciones', self::idsCondicionesSelector())
            ->findOrFail($idMatricula);

        return $m;
    }

    /**
     * Matrícula del ciclo activo para un legajo (permite imprimir historial / actas de años anteriores).
     */
    public static function matriculaActualDelLegajo(int $idLegajos): ?Matricula
    {
        if ($idLegajos < 1) {
            return null;
        }

        return Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->where('idLegajos', $idLegajos)
            ->whereIn('idCondiciones', self::idsCondicionesSelector())
            ->first();
    }

    public static function registroEnAlcance(int $id): Gabinete
    {
        abort_unless(self::tablasDisponibles(), 404);

        /** @var Gabinete $reg */
        $reg = Gabinete::query()
            ->with(['tipo', 'matricula.legajo', 'matricula.curso', 'matricula.terlec'])
            ->findOrFail($id);

        $idLegajos = (int) ($reg->matricula?->idLegajos ?? 0);
        abort_if($idLegajos < 1, 404);
        abort_if(self::matriculaActualDelLegajo($idLegajos) === null, 404);

        return $reg;
    }

    /**
     * @return Collection<int, GabineteTipo>
     */
    public static function tipos(): Collection
    {
        if (! self::tablasDisponibles()) {
            return collect();
        }

        return GabineteTipo::query()
            ->orderBy('tipo')
            ->get(['id', 'tipo']);
    }

    public static function tipoExiste(int $id): bool
    {
        if ($id < 1 || ! self::tablasDisponibles()) {
            return false;
        }

        return GabineteTipo::query()->where('id', $id)->exists();
    }

    /**
     * Todos los registros del alumno (todas las matrículas del legajo), más recientes primero.
     *
     * @return Collection<int, Gabinete>
     */
    public static function registrosDelLegajo(int $idLegajos): Collection
    {
        if ($idLegajos < 1 || ! self::tablasDisponibles()) {
            return collect();
        }

        return Gabinete::query()
            ->with(['tipo', 'matricula.curso', 'matricula.terlec'])
            ->join('matricula', 'matricula.id', '=', 'gabinete.idMatricula')
            ->where('matricula.idLegajos', $idLegajos)
            ->orderByDesc('gabinete.fecha')
            ->orderByDesc('gabinete.id')
            ->select('gabinete.*')
            ->get();
    }

    public static function colorDelLegajo(int $idLegajos): int
    {
        if ($idLegajos < 1 || ! self::tablaMarcaDisponible()) {
            return GabineteSemaforo::NINGUNO;
        }

        $color = GabineteMarca::query()
            ->where('idLegajos', $idLegajos)
            ->value('color');

        return GabineteSemaforo::normalizar($color);
    }

    /**
     * @return array{ok: bool, color: int, mensaje: string}
     */
    public static function guardarColor(int $idLegajos, int $color): array
    {
        if (! self::tablaMarcaDisponible()) {
            return [
                'ok' => false,
                'color' => GabineteSemaforo::NINGUNO,
                'mensaje' => 'Falta la tabla gabinetemarca. Ejecute el SQL de esquema del módulo antes de marcar colores.',
            ];
        }

        $color = GabineteSemaforo::normalizar($color);

        if ($color === GabineteSemaforo::NINGUNO) {
            GabineteMarca::query()->where('idLegajos', $idLegajos)->delete();

            return ['ok' => true, 'color' => GabineteSemaforo::NINGUNO, 'mensaje' => 'Marca quitada.'];
        }

        $payload = [
            'idLegajos' => $idLegajos,
            'color' => $color,
        ];
        $preparado = PersistenciaColumnas::prepararPayload(self::TABLA_MARCA, $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            return [
                'ok' => false,
                'color' => self::colorDelLegajo($idLegajos),
                'mensaje' => PersistenciaColumnas::mensajeColumnasInexistentes(
                    self::TABLA_MARCA,
                    $preparado['columnas_con_valor_sin_columna']
                ),
            ];
        }

        $existente = GabineteMarca::query()->where('idLegajos', $idLegajos)->first();
        if ($existente) {
            $existente->forceFill($preparado['payload'])->save();
        } else {
            $insert = PersistenciaColumnas::completarNotNullSinDefault(self::TABLA_MARCA, $preparado['payload']);
            $marca = new GabineteMarca();
            $marca->forceFill($insert)->save();
        }

        return ['ok' => true, 'color' => $color, 'mensaje' => 'Marca actualizada.'];
    }

    /**
     * @return Builder<Matricula>
     */
    public static function queryAlumnos(int $idCurso, string $vista, string $filtroColor, string $busqueda): Builder
    {
        $q = Matricula::query()
            ->where('matricula.idNivel', schoolCtx()->idNivel)
            ->where('matricula.idTerlec', schoolCtx()->idTerlec)
            ->whereIn('matricula.idCondiciones', self::idsCondicionesSelector())
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->join('cursos', 'cursos.Id', '=', 'matricula.idCursos');

        if ($idCurso > 0) {
            $q->where('matricula.idCursos', $idCurso);
        }

        if (self::tablaMarcaDisponible()) {
            $q->leftJoin('gabinetemarca', 'gabinetemarca.idLegajos', '=', 'legajos.id');
            $filtro = GabineteSemaforo::normalizarFiltro($filtroColor);
            if ($filtro === 'sin') {
                $q->where(function (Builder $inner) {
                    $inner->whereNull('gabinetemarca.color')
                        ->orWhere('gabinetemarca.color', GabineteSemaforo::NINGUNO);
                });
            } elseif ($filtro !== '') {
                $q->where('gabinetemarca.color', GabineteSemaforo::colorDesdeFiltro($filtro));
            }
        }

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $busqueda).'%';
            $q->where(function (Builder $inner) use ($like) {
                $inner->where('legajos.apellido', 'like', $like)
                    ->orWhere('legajos.nombre', 'like', $like)
                    ->orWhere('legajos.dni', 'like', $like);
            });
        }

        $q->select([
            'matricula.id',
            'matricula.idLegajos',
            'matricula.idCursos',
            'legajos.apellido',
            'legajos.nombre',
            'legajos.dni',
            'cursos.cursec',
            'cursos.orden',
        ]);

        if (self::tablaMarcaDisponible()) {
            $q->addSelect('gabinetemarca.color as colorGabinete');
        }

        if ($vista === 'alfa' && $idCurso <= 0) {
            $q->orderByRaw(OrdenAlfabeticoEstudiante::sql('legajos.apellido'))
                ->orderByRaw(OrdenAlfabeticoEstudiante::sql('legajos.nombre'))
                ->orderBy('matricula.id');
        } else {
            $q->orderBy('cursos.orden')
                ->orderBy('cursos.cursec')
                ->orderByRaw(OrdenAlfabeticoEstudiante::sql('legajos.apellido'))
                ->orderByRaw(OrdenAlfabeticoEstudiante::sql('legajos.nombre'))
                ->orderBy('matricula.id');
        }

        return $q;
    }

    /**
     * @return LengthAwarePaginator<int, Matricula>
     */
    public static function paginarAlumnos(
        int $idCurso,
        string $vista,
        string $filtroColor,
        string $busqueda,
        int $porPagina
    ): LengthAwarePaginator {
        return self::queryAlumnos($idCurso, $vista, $filtroColor, $busqueda)
            ->paginate($porPagina);
    }

    public static function nroLegajo(?object $legajo): string
    {
        if ($legajo === null) {
            return '';
        }

        foreach (['nrolegajo', 'nroLegajo', 'nro', 'legajo'] as $campo) {
            if (isset($legajo->{$campo}) && trim((string) $legajo->{$campo}) !== '') {
                return trim((string) $legajo->{$campo});
            }
        }

        $id = (int) ($legajo->id ?? 0);

        return $id > 0 ? (string) $id : '';
    }
}
