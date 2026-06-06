<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InasDocente extends Model
{
    protected $table = 'inasdocentes';

    public $timestamps = false;

    protected $fillable = [
        'idProfesores',
        'dniProfesor',
        'idNivel',
        'inaLic',
        'idTipoInaDoc',
        'idCargosXProfesor',
        'fecha',
        'hasta',
        'cantOblig',
        'cantObligIna',
        'justif',
        'obs',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hasta' => 'date',
        'inaLic' => 'integer',
        'justif' => 'integer',
        'cantOblig' => 'integer',
        'cantObligIna' => 'float',
    ];

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'idProfesores');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoInaDoc::class, 'idTipoInaDoc');
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class, 'idNivel');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(InasDocenteDetalle::class, 'idInasDocentes');
    }
}
