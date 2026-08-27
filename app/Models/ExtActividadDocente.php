<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtActividadDocente extends Model
{
    protected $table = 'ext_actividad_docentes';

    public $timestamps = false;

    protected $fillable = [
        'id_actividad',
        'id_profesor',
        'rol',
    ];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(ExtActividad::class, 'id_actividad');
    }

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'id_profesor');
    }
}
