<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermisoUsuario extends Model
{
    protected $table = 'permisosusuarios';
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

