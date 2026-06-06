<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Calificacion extends Model
{
    protected $table = 'calificaciones';
    public $timestamps = false;
    protected $fillable = [
        'idLegajos', 'idMatricula', 'ord', 'idTerlec', 'idCursos', 'idMaterias', 'idMatPlan',
        'ic01', 'ic02', 'ic03', 'ic04', 'ic05', 'ic06', 'ic07', 'ic08', 'ic09', 'ic10',
        'ic11', 'ic12', 'ic13', 'ic14', 'ic15', 'ic16', 'ic17', 'ic18', 'ic19', 'ic20',
        'ic21', 'ic22', 'ic23', 'ic24', 'ic25', 'ic26', 'ic27', 'ic28', 'ic29', 'ic30',
        'ic31', 'ic32', 'ic33', 'ic34', 'ic35', 'ic36', 'ic37', 'ic38', 'ic39', 'ic40',
        'obs01', 'obs02', 'tm1', 'tm2', 'tm3', 'tm4', 'tm5', 'tm6', 'tmNota',
        'dic', 'feb', 'tea', 'inscri', 'condAdeuda', 'apro', 'calif', 'mes', 'ano', 'cond', 'escuapro',
        'libro', 'folio', 'fechApro', 'libroDic', 'folioDic', 'fechAproDic', 'libroFeb', 'folioFeb', 'fechAproFeb'
    ];

    public function legajo()
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }

    public function terlec()
    {
        return $this->belongsTo(Terlec::class, 'idTerlec');
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'idCursos', 'Id');
    }
}
