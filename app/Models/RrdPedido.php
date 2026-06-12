<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RrdPedido extends Model
{
    protected $table = 'rrd_pedidos';

    public $timestamps = false;

    protected $fillable = [
        'id_nivel',
        'id_terlec',
        'id_profesor',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'sala_curso_grado',
        'auxiliar',
        'observaciones',
        'created_at',
    ];

    protected $casts = [
        'id_nivel'    => 'integer',
        'id_terlec'   => 'integer',
        'id_profesor' => 'integer',
        'fecha'       => 'date',
        'created_at'  => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relaciones
    // ---------------------------------------------------------------

    public function reservas(): HasMany
    {
        return $this->hasMany(RrdReserva::class, 'id_pedido');
    }

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'id_profesor');
    }

    // ---------------------------------------------------------------
    // Scopes y helpers de consulta
    // ---------------------------------------------------------------

    public function scopeEnContexto(Builder $query): Builder
    {
        $ctx = schoolCtx();
        $idNivel  = (int) ($ctx->idNivel  ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        return $query
            ->where('id_nivel', $idNivel)
            ->where('id_terlec', $idTerlec);
    }

    /** Solo los pedidos del profesor autenticado (para "Solo Lectura" y "Profesor"). */
    public function scopeDelProfesor(Builder $query): Builder
    {
        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);

        return $query->where('id_profesor', $idProfesor);
    }

    public static function queryEnContexto(): Builder
    {
        return static::query()->enContexto();
    }
}
