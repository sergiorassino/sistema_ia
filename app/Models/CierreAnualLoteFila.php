<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CierreAnualLoteFila extends Model
{
    protected $table = 'cierre_anual_lote_filas';

    public $timestamps = false;

    protected $fillable = [
        'id_lote',
        'id_calificacion',
        'id_legajos',
        'id_matricula',
        'id_materias',
        'apellido',
        'nombre',
        'dni',
        'materia',
        'curso',
        'tipo',
        'apro_antes',
        'calif_antes',
        'mes_antes',
        'ano_antes',
        'cond_antes',
        'escuapro_antes',
        'cond_adeuda_antes',
        'inscri_antes',
        'apro_despues',
        'calif_despues',
        'mes_despues',
        'ano_despues',
        'cond_despues',
        'escuapro_despues',
        'cond_adeuda_despues',
        'inscri_despues',
        'revertida_at',
    ];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(CierreAnualLote::class, 'id_lote');
    }
}
