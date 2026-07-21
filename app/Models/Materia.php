<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    protected $table = 'materias';

    protected $primaryKey = 'id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'ord',
        'idNivel',
        'idTerlec',
        'idCursos',
        'idCurPlan',
        'idMatPlan',
        'materia',
        'abrev',
        'cierre1e',
        'cierre2e',
        'esInstitucional',
        'infoCalif',
        'escala',
        'pp_plan',
        'pp_prog',
        'pp_aprobPlan',
        'pp_aprobProg',
        'pp_obsPlan',
        'pp_obsProg',
        'pp_nombrePlan',
        'pp_nombreProg',
    ];

    protected $casts = [
        'ord' => 'integer',
        'idNivel' => 'integer',
        'idTerlec' => 'integer',
        'idCursos' => 'integer',
        'idCurPlan' => 'integer',
        'idMatPlan' => 'integer',
        'cierre1e' => 'integer',
        'cierre2e' => 'integer',
        'esInstitucional' => 'integer',
        'infoCalif' => 'integer',
        'escala' => 'integer',
        'pp_plan' => 'integer',
        'pp_prog' => 'integer',
        'pp_aprobPlan' => 'integer',
        'pp_aprobProg' => 'integer',
    ];

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'idCursos', 'Id');
    }

    public function terlec()
    {
        return $this->belongsTo(Terlec::class, 'idTerlec', 'id');
    }
}
