<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RrdRecurso extends Model
{
    protected $table = 'rrd_recursos';

    public $timestamps = false;

    protected $fillable = [
        'id_grupo',
        'id_nivel',
        'nombre',
        'antelacion_min_horas',
        'orden',
        'activo',
        'siempre_disponible',
    ];

    protected $casts = [
        'id_grupo'             => 'integer',
        'id_nivel'             => 'integer',
        'antelacion_min_horas' => 'integer',
        'orden'                => 'integer',
        'activo'               => 'boolean',
        'siempre_disponible'   => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relaciones
    // ---------------------------------------------------------------

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(RrdGrupo::class, 'id_grupo');
    }

    public function disponibilidades(): HasMany
    {
        return $this->hasMany(RrdRecursoDisponibilidad::class, 'id_recurso')
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(RrdReserva::class, 'id_recurso');
    }

    // ---------------------------------------------------------------
    // Scopes y helpers de consulta
    // ---------------------------------------------------------------

    public function scopeEnContexto(Builder $query): Builder
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);

        return $query->where('id_nivel', $idNivel);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** Recursos activos de un grupo, ordenados. */
    public static function paraGrupo(int $idGrupo): \Illuminate\Support\Collection
    {
        return static::query()
            ->enContexto()
            ->activos()
            ->where('id_grupo', $idGrupo)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'antelacion_min_horas']);
    }
}
