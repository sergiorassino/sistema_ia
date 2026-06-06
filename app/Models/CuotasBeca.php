<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuotasBeca extends Model
{
    protected $table = 'cuotasbecas';

    public $timestamps = false;

    protected $fillable = [
        'nombreBeca',
        'porcentaje',
    ];

    protected $casts = [
        'porcentaje' => 'float',
    ];
}
