<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RrdRecursoDisponibilidad extends Model
{
    protected $table = 'rrd_recurso_disponibilidad';

    public $timestamps = false;

    protected $fillable = [
        'id_recurso',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    protected $casts = [
        'id_recurso' => 'integer',
        'dia_semana' => 'integer',
    ];

    /** Nombres de días (ISO 8601: 1=Lunes, 7=Domingo). */
    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(RrdRecurso::class, 'id_recurso');
    }

    public function nombreDia(): string
    {
        return self::DIAS[$this->dia_semana] ?? "Día {$this->dia_semana}";
    }
}
