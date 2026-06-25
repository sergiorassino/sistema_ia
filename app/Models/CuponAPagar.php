<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuponAPagar extends Model
{
    public const ORIGEN_SUBIDA_SIRO = 'subida_siro';

    public const ORIGEN_IMPRESION_ADMIN = 'impresion_admin';

    public const ORIGEN_IMPRESION_AUTOGESTION = 'impresion_autogestion';

    protected $table = 'cupones_a_pagar';

    public $timestamps = false;

    protected $fillable = [
        'id_cuotas_generadas',
        'id_cursos',
        'id_cuotasbecas',
        'saldo_pagar',
        'cpe',
        'id_factura',
        'ult_upload',
        'origen',
        'signo1v',
        'valor1v',
        'porcan1v',
        'fecha1venc',
        'importe1venc',
        'signo2v',
        'valor2v',
        'porcan2v',
        'fecha2venc',
        'importe2venc',
        'signo3v',
        'valor3v',
        'porcan3v',
        'fecha3venc',
        'importe3venc',
        'fecha_emision',
        'nombre_archivo_siro',
    ];

    protected $casts = [
        'saldo_pagar' => 'float',
        'valor1v' => 'float',
        'valor2v' => 'float',
        'valor3v' => 'float',
        'importe1venc' => 'float',
        'importe2venc' => 'float',
        'importe3venc' => 'float',
        'fecha1venc' => 'date',
        'fecha2venc' => 'date',
        'fecha3venc' => 'date',
        'fecha_emision' => 'datetime',
    ];

    public function cuotaGenerada(): BelongsTo
    {
        return $this->belongsTo(CuotaGenerada::class, 'id_cuotas_generadas');
    }
}
