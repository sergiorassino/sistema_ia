<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailEnviado extends Model
{
    protected $table = 'emails_enviados';

    public $timestamps = false;

    protected $fillable = [
        'mailDestino',
        'fechhora',
        'idProfesores',
        'idLegajos',
        'idCursos',
        'idNiveles',
        'idTerlec',
        'subject',
        'texto',
        'attached',
    ];

    protected $casts = [
        'fechhora' => 'datetime',
    ];

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'idProfesores');
    }

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }
}
