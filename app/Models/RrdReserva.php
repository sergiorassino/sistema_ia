<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RrdReserva extends Model
{
    protected $table = 'rrd_reservas';

    public $timestamps = false;

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_ENTREGADO = 'entregado';
    public const ESTADO_DEVUELTO  = 'devuelto';
    public const ESTADO_CANCELADO = 'cancelado';

    protected $fillable = [
        'id_pedido',
        'id_recurso',
        'id_nivel',
        'id_terlec',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
        'entregado_a',
        'entregado_por',
        'entregado_at',
        'devuelto_por',
        'devuelto_a',
        'devuelto_at',
        'created_at',
    ];

    protected $casts = [
        'id_pedido'    => 'integer',
        'id_recurso'   => 'integer',
        'id_nivel'     => 'integer',
        'id_terlec'    => 'integer',
        'fecha'        => 'date',
        'entregado_at' => 'datetime',
        'devuelto_a'   => 'integer',
        'devuelto_at'  => 'datetime',
        'created_at'   => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relaciones
    // ---------------------------------------------------------------

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(RrdPedido::class, 'id_pedido');
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(RrdRecurso::class, 'id_recurso');
    }

    public function entregadoPorProfesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'entregado_por');
    }

    public function operadorDevolucion(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'devuelto_a');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    /** Reservas visibles para todos los niveles del mismo ciclo lectivo. */
    public function scopeEnContexto(Builder $query): Builder
    {
        $idTerlec = (int) (schoolCtx()->idTerlec ?? 0);

        return $query->where('id_terlec', $idTerlec);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->whereNotIn('estado', [self::ESTADO_CANCELADO, self::ESTADO_DEVUELTO]);
    }

    public static function queryEnContexto(): Builder
    {
        return static::query()->enContexto();
    }

    // ---------------------------------------------------------------
    // Helpers de estado
    // ---------------------------------------------------------------

    public function esPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function esEntregado(): bool
    {
        return $this->estado === self::ESTADO_ENTREGADO;
    }

    public function esDevuelto(): bool
    {
        return $this->estado === self::ESTADO_DEVUELTO;
    }

    public function esCancelado(): bool
    {
        return $this->estado === self::ESTADO_CANCELADO;
    }

    /** Una reserva entregada no puede borrarse ni cancelarse hasta la devolución. */
    public function puedeModificarse(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    /** Nombre de la persona que devolvió el material (columna devuelto_por). */
    public function nombreQuienDevuelve(): string
    {
        $valor = $this->getAttributes()['devuelto_por'] ?? null;

        if ($valor === null) {
            return '';
        }

        $texto = trim((string) $valor);

        return ($texto === '' || $texto === '0') ? '' : $texto;
    }
}
