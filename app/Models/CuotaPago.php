<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuotaPago extends Model
{
    protected $table = 'cuotaspagos';

    public $timestamps = false;

    protected $fillable = [
        'idCuotasGeneradas',
        'idCuotastipopago',
        'fechhora',
        'importe',
        'bonificacion',
        'interes',
        'nombreArchivo',
        'cadenaPago',
    ];

    protected $casts = [
        'fechhora' => 'datetime',
        'importe' => 'float',
        'bonificacion' => 'float',
        'interes' => 'float',
    ];

    public function cuotaGenerada()
    {
        return $this->belongsTo(CuotaGenerada::class, 'idCuotasGeneradas');
    }

    public function tipoPago()
    {
        return $this->belongsTo(CuotaTipoPago::class, 'idCuotastipopago');
    }
}
