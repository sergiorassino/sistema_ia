<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemBoletin extends Model
{
    protected $table = 'itemsboletin';

    public $timestamps = false;

    protected $fillable = [
        'orden',
        'etiqueta',
        'fuente',
        'condicion_where',
        'idTerlec',
        'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'idTerlec' => 'integer',
        'activo' => 'boolean',
    ];
}
