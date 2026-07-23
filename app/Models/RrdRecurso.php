<?php

namespace App\Models;

use App\Support\MaterialDidactico\RrdReservaService;
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

    /** El recurso solo puede reservarse dentro de ventanas horarias configuradas. */
    public function restringidoPorHorario(): bool
    {
        return ! $this->siempre_disponible;
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
            ->get(['id', 'nombre', 'antelacion_min_horas', 'siempre_disponible']);
    }

    /**
     * Recursos activos de un grupo reservables en el horario indicado
     * (antelación mínima, ventanas de disponibilidad y sin reservas solapadas).
     */
    public static function paraGrupoReservablesEnHorario(
        int $idGrupo,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        bool $omitirAntelacion = false,
        ?int $excluirPedidoId = null
    ): \Illuminate\Support\Collection {
        $recursosOcupados = RrdReservaService::idsRecursosConSolapamientoEnHorario(
            $fecha,
            $horaInicio,
            $horaFin,
            $excluirPedidoId
        );

        return static::query()
            ->enContexto()
            ->activos()
            ->where('id_grupo', $idGrupo)
            ->with('disponibilidades')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->filter(
                fn (self $recurso): bool => RrdReservaService::esReservableEnHorario(
                    $recurso,
                    $fecha,
                    $horaInicio,
                    $horaFin,
                    $omitirAntelacion,
                    $excluirPedidoId,
                    $recursosOcupados
                )
            )
            ->values();
    }
}
