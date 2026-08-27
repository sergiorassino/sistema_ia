<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtActividadAlumno extends Model
{
    protected $table = 'ext_actividad_alumnos';

    public $timestamps = false;

    protected $fillable = [
        'id_actividad',
        'id_legajo',
    ];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(ExtActividad::class, 'id_actividad');
    }

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'id_legajo');
    }
}
