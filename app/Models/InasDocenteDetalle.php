<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InasDocenteDetalle extends Model
{
    protected $table = 'inasdocentes_detalle';

    public $timestamps = false;

    protected $fillable = [
        'idInasDocentes',
        'idMaterias',
        'idCursos',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'float',
    ];

    public function inasistencia(): BelongsTo
    {
        return $this->belongsTo(InasDocente::class, 'idInasDocentes');
    }
}
