<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CierreAnualLote extends Model
{
    protected $table = 'cierre_anual_lotes';

    public $timestamps = false;

    protected $fillable = [
        'operacion',
        'id_nivel',
        'id_terlec',
        'ano_lectivo',
        'nivel_nombre',
        'id_profesor',
        'nombre_profesor',
        'procesados',
        'aprobados',
        'previas',
        'omitidos',
        'actualizados',
        'estado',
        'created_at',
        'revertido_at',
        'id_profesor_reverso',
        'nombre_profesor_reverso',
        'revertidos_ok',
        'revertidos_omitidos',
    ];

    public function filas(): HasMany
    {
        return $this->hasMany(CierreAnualLoteFila::class, 'id_lote');
    }
}
