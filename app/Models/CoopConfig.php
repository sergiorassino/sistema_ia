<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoopConfig extends Model
{
    protected $table = 'coop_config';

    public $timestamps = false;

    protected $fillable = [
        'nombre_institucion',
        'direccion',
        'localidad',
        'telefono',
        'cuit',
        'repace',
        'descuento_hermano_pct',
        'recibo_proximo_num',
        'orden_pago_proximo_num',
    ];

    protected $casts = [
        'descuento_hermano_pct' => 'decimal:2',
        'recibo_proximo_num' => 'integer',
        'orden_pago_proximo_num' => 'integer',
    ];
}
