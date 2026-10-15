<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persona autorizada para comunicación y retiro (legajo del estudiante).
 */
class LegajoAutorizadoEmergencia extends Model
{
    protected $table = 'legajo_autorizados_emergencia';

    public $timestamps = false;

    protected $fillable = [
        'idLegajo',
        'orden',
        'apellido_nombre',
        'dni',
        'relacion',
        'telefono',
    ];

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'idLegajo');
    }
}
