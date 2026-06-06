<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuotasImporte extends Model
{
    protected $table = 'cuotasimportes';

    public $timestamps = false;

    protected $fillable = [
        'idCuotas',
        'idCursos',
        'importe',
        'signo1v',
        'valor1v',
        'porcan1v',
        'signo2v',
        'valor2v',
        'porcan2v',
        'signo3v',
        'valor3v',
        'porcan3v',
        'signo4v',
        'valor4v',
        'porcan4v',
    ];

    protected $casts = [
        'importe' => 'float',
        'valor1v' => 'float',
        'valor2v' => 'float',
        'valor3v' => 'float',
        'valor4v' => 'float',
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
