<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuotasDetalle extends Model
{
    protected $table = 'cuotasdetalle';

    public $timestamps = false;

    protected $fillable = [
        'idCuotas',
        'idCursos',
        'orden',
        'nombre',
        'importe',
    ];

    protected $casts = [
        'idCuotas' => 'integer',
        'idCursos' => 'integer',
        'orden' => 'integer',
        'importe' => 'float',
    ];

    public function cuota()
    {
        return $this->belongsTo(Cuota::class, 'idCuotas');
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'idCursos', 'Id');
    }
}
