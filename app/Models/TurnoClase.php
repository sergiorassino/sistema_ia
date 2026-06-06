<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurnoClase extends Model
{
    protected $table = 'turnos_clase';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];
}
