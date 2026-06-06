<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotasExamen extends Model
{
    protected $table = 'notasexamen';

    public $timestamps = false;

    protected $fillable = [
        'idCalificaciones',
        'idLegajos',
        'nota',
        'fecha',
        'condExamen',
        'libro',
        'folio',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function calificacion()
    {
        return $this->belongsTo(Calificacion::class, 'idCalificaciones');
    }

    public function legajo()
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }
}
