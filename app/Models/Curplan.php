<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Curplan extends Model
{
    protected $table = 'curplan';

    public $timestamps = false;

    protected $fillable = [
        'idPlan',
        'curPlanCurso',
    ];

    /** Etiquetas conocidas de grado/año (más largas primero al matchear). */
    private const ORDEN_ETIQUETAS = [
        'PRIMER GRADO' => 1,
        'SEGUNDO GRADO' => 2,
        'TERCER GRADO' => 3,
        'CUARTO GRADO' => 4,
        'QUINTO GRADO' => 5,
        'SEXTO GRADO' => 6,
        'PRIMER AÑO' => 1,
        'SEGUNDO AÑO' => 2,
        'TERCER AÑO' => 3,
        'CUARTO AÑO' => 4,
        'QUINTO AÑO' => 5,
        'SEXTO AÑO' => 6,
        'PRIMERO' => 1,
        'SEGUNDO' => 2,
        'TERCERO' => 3,
        'CUARTO' => 4,
        'QUINTO' => 5,
        'SEXTO' => 6,
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'idPlan');
    }

    public function cursos()
    {
        return $this->hasMany(Curso::class, 'idCurPlan');
    }

    public function materias()
    {
        return $this->hasMany(Matplan::class, 'idCurPlan');
    }

    public function scopeDelNivel(Builder $query, int $idNivel): Builder
    {
        $planesIds = Plan::query()->where('idNivel', $idNivel)->pluck('id');

        return $query->whereIn('idPlan', $planesIds);
    }

    /**
     * Clave numérica para ordenar `curPlanCurso` (grado/año/sala), no alfabético.
     */
    public static function claveOrdenNombre(string $nombre): int
    {
        $texto = mb_strtoupper(trim($nombre), 'UTF-8');
        if ($texto === '') {
            return 99_999;
        }

        if (isset(self::ORDEN_ETIQUETAS[$texto])) {
            return self::ORDEN_ETIQUETAS[$texto];
        }

        $etiquetas = collect(self::ORDEN_ETIQUETAS)
            ->sortByDesc(fn ($v, $k) => mb_strlen((string) $k));

        foreach ($etiquetas as $clave => $num) {
            if (str_contains($texto, (string) $clave)) {
                return (int) $num;
            }
        }

        if (preg_match('/SALA\s*DE\s*(\d+)/u', $texto, $m) === 1) {
            return (int) $m[1];
        }

        if (preg_match('/\b([1-9]|1[0-9])\s*(?:°|º|o)?\b/u', $texto, $m) === 1) {
            return (int) $m[1];
        }

        return 90_000;
    }

    /**
     * @param  Collection<int, self>|array<int, self>  $curplanes
     * @return Collection<int, self>
     */
    public static function ordenarColeccion(Collection|array $curplanes): Collection
    {
        return collect($curplanes)->sortBy(function (self $c): array {
            return [
                (int) $c->idPlan,
                static::claveOrdenNombre((string) $c->curPlanCurso),
                (int) $c->id,
            ];
        })->values();
    }

    /**
     * Cursos modelo del nivel: por plan y orden pedagógico del nombre.
     *
     * @param  list<string>  $with
     * @return Collection<int, self>
     */
    public static function listadoOrdenadoParaNivel(int $idNivel, array $with = ['plan']): Collection
    {
        $query = static::query()->delNivel($idNivel);
        if ($with !== []) {
            $query->with($with);
        }

        return static::ordenarColeccion($query->get());
    }
}
