<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtActividadCurso extends Model
{
    protected $table = 'ext_actividad_cursos';

    public $timestamps = false;

    protected $fillable = [
        'id_actividad',
        'id_curso',
    ];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(ExtActividad::class, 'id_actividad');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'id_curso', 'Id');
    }
}
