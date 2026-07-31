<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override de nombre de asignatura para analítico (tabla legacy `nombresmaterias`).
 */
class NombreMateria extends Model
{
    protected $table = 'nombresmaterias';

    public $timestamps = false;

    protected $fillable = [
        'idLegajos',
        'idMaterias',
        'nombreMateria',
    ];

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }
}
