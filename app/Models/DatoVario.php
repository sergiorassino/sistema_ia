<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatoVario extends Model
{
    protected $table = 'datosvarios';

    public $timestamps = false;

    protected $fillable = [
        'ultimoComprobante',
        'ultimaSoliBeca',
        'textoInicNotDeuda',
        'textoFinalNotDeuda',
        'textoFinalNotDeudaBec',
    ];

    protected $casts = [
        'ultimoComprobante' => 'integer',
        'ultimaSoliBeca' => 'integer',
    ];

    /**
     * Registro único de configuración (`datosvarios.id = 1`).
     */
    public static function singleton(): self
    {
        $registro = static::query()->whereKey(1)->first();
        abort_if(
            $registro === null,
            404,
            'No se encontró el registro de datos varios (id = 1).',
        );

        return $registro;
    }
}
