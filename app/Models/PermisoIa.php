<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermisoIa extends Model
{
    protected $table = 'permisos_ia';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'orden',
        'tema',
        'descripcion',
    ];

    protected $casts = [
        'id' => 'int',
        'orden' => 'int',
    ];
}
