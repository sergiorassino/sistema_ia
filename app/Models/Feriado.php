<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feriado extends Model
{
    protected $table = 'feriados';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'fechaFeriado',
        'nombre',
        'idNivel',
    ];

    protected $casts = [
        'fechaFeriado' => 'date',
        'idNivel' => 'integer',
    ];

    public function nivel()
    {
        return $this->belongsTo(Nivel::class, 'idNivel');
    }
}
