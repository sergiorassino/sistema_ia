<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtFecha extends Model
{
    protected $table = 'ext_fechas';

    public $timestamps = false;

    protected $fillable = [
        'id_actividad',
        'fecha',
        'hora_inicio',
        'hora_fin',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(ExtActividad::class, 'id_actividad');
    }
}
