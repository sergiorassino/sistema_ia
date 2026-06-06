<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuotaTipoPago extends Model
{
    protected $table = 'cuotastipopago';

    public $timestamps = false;

    protected $fillable = [
        'tipoPago',
        'abrev',
    ];
}
