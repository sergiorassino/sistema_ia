<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuotasTipo extends Model
{
    protected $table = 'cuotastipo';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];
}
