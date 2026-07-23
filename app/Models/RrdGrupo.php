<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RrdGrupo extends Model
{
    protected $table = 'rrd_grupos';

    public $timestamps = false;

    protected $fillable = [
        'id_nivel',
        'nombre',
        'orden',
        'activo',
    ];

    protected $casts = [
        'id_nivel' => 'integer',
        'orden'    => 'integer',
        'activo'   => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relaciones
    // ---------------------------------------------------------------

    public function recursos(): HasMany
    {
        return $this->hasMany(RrdRecurso::class, 'id_grupo');
    }

    // ---------------------------------------------------------------
    // Scopes y helpers de consulta
    // ---------------------------------------------------------------

    /**
     * Catálogo compartido entre todos los niveles del colegio.
     * id_nivel se conserva al crear (auditoría del contexto), pero no filtra visibilidad.
     */
    public function scopeEnContexto(Builder $query): Builder
    {
        return $query;
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** Grupos activos del contexto, ordenados. */
    public static function paraSelector(): \Illuminate\Support\Collection
    {
        return static::query()
            ->enContexto()
            ->activos()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }
}
