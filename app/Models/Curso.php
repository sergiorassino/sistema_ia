<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Curso extends Model
{
    protected $table = 'cursos';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'orden', 'idCurPlan', 'idTerlec', 'idNivel', 'cursec', 'c', 's', 'idTurnoClase',
    ];

    public function turnoClase()
    {
        return $this->belongsTo(TurnoClase::class, 'idTurnoClase', 'id');
    }

    public function nivel()
    {
        return $this->belongsTo(Nivel::class, 'idNivel');
    }

    public function terlec()
    {
        return $this->belongsTo(Terlec::class, 'idTerlec');
    }

    public function curplan()
    {
        return $this->belongsTo(Curplan::class, 'idCurPlan');
    }

    /**
     * Listados y selectores: nivel, año/sala, `cursos.orden` (sección) e Id.
     *
     * `first()` / `value()` / `pluck()` de Query Builder no pasan por acá: el curso
     * por defecto de un módulo no cambia.
     *
     * @param  array<int, mixed>  $models
     */
    public function newCollection(array $models = []): EloquentCollection
    {
        if (count($models) <= 1) {
            return parent::newCollection($models);
        }

        return parent::newCollection(static::ordenarColeccion($models)->all());
    }

    /**
     * Año/sala para selectores: `cursos.c` si es numérico; si no, el nombre (`cursec` / curplan).
     */
    public static function claveOrdenPedagogico(self $curso): int
    {
        $nombre = trim((string) ($curso->cursec ?? ''));
        if ($nombre === '' && $curso->relationLoaded('curplan') && $curso->curplan) {
            $nombre = trim((string) $curso->curplan->curPlanCurso);
        }

        return static::claveOrdenPedagogicoDesdeAtributos((string) ($curso->c ?? ''), $nombre);
    }

    public static function claveOrdenPedagogicoDesdeAtributos(string $ciclo, string $nombre): int
    {
        $ciclo = trim($ciclo);
        if ($ciclo !== '' && ctype_digit($ciclo)) {
            return (int) $ciclo;
        }

        return Curplan::claveOrdenNombre($nombre);
    }

    /**
     * Claves de orden para un curso o una fila (join / query builder) con los mismos campos.
     *
     * @param  object|array<string, mixed>|self  $fila
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    public static function clavesOrdenSelector(object|array $fila): array
    {
        if ($fila instanceof self) {
            return [
                (int) ($fila->idNivel ?? 0),
                static::claveOrdenPedagogico($fila),
                (int) ($fila->orden ?? 9999),
                (int) $fila->Id,
            ];
        }

        $ciclo = (string) (data_get($fila, 'c') ?? '');
        $nombre = (string) (data_get($fila, 'cursec') ?? '');

        return [
            (int) (data_get($fila, 'idNivel') ?? 0),
            static::claveOrdenPedagogicoDesdeAtributos($ciclo, $nombre),
            (int) (data_get($fila, 'orden') ?? 9999),
            (int) (data_get($fila, 'Id') ?? data_get($fila, 'id') ?? data_get($fila, 'idCursos') ?? 0),
        ];
    }

    /**
     * Selectores de curso: agrupa por nivel, año/sala y, dentro, por `cursos.orden` (sección).
     *
     * `orden` suele repetirse entre salas (A=1, B=2…); ordenar solo por ese campo intercalaría 4A / 3A / 4B.
     *
     * @param  Collection<int, mixed>|array<int, mixed>  $cursos
     * @return Collection<int, mixed>
     */
    public static function ordenarColeccion(Collection|array $cursos): Collection
    {
        return collect($cursos)->sortBy(
            fn ($c): array => static::clavesOrdenSelector($c)
        )->values();
    }

    /**
     * Texto para listados / PDF: prioriza sección (`cursec`), si no hay datos del plan y turno.
     */
    public function nombreParaListado(): string
    {
        $sec = trim((string) $this->cursec);
        if ($sec !== '') {
            return $sec;
        }

        $nombrePlan = trim((string) ($this->curplan?->curPlanCurso ?? ''));

        $turnoLabel = null;
        if ((int) ($this->idTurnoClase ?? 0) > 0) {
            if (! $this->relationLoaded('turnoClase')) {
                $this->load('turnoClase');
            }
            $turnoLabel = trim((string) ($this->turnoClase?->nombre ?? ''));
        }
        if ($turnoLabel === '') {
            $turnoLabel = null;
        }

        $extras = collect([$turnoLabel, $this->c, $this->s])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();

        if ($nombrePlan !== '') {
            return $extras->isNotEmpty()
                ? $nombrePlan.' · '.$extras->implode(' · ')
                : $nombrePlan;
        }

        if ($extras->isNotEmpty()) {
            return $extras->implode(' · ');
        }

        return 'Curso';
    }

    public function matriculas()
    {
        return $this->hasMany(Matricula::class, 'idCursos', 'Id');
    }

    public function calificaciones()
    {
        return $this->hasMany(Calificacion::class, 'idCursos', 'Id');
    }
}
