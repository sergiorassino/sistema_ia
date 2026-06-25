<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Matricula extends Model
{
    protected $table = 'matricula';

    public $timestamps = false;

    protected $fillable = [
        'idLegajos',
        'idCursos',
        'idCondiciones',
        'idTerlec',
        'idNivel',
        'nroMatricula',
        'fechaMatricula',
        'fechaBaja',
        'bloqmatr',
        'bloqadmi',
        'idCuotasbecas',
        'coop_es_hermano',
        'acept1',
        'acept2',
        'acept3',
        'acept4',
    ];

    protected $casts = [
        'fechaMatricula' => 'date',
        'fechaBaja' => 'date',
        'bloqmatr' => 'boolean',
        'bloqadmi' => 'boolean',
        'coop_es_hermano' => 'boolean',
        'inscripto' => 'boolean',
        'acept1' => 'boolean',
        'acept2' => 'boolean',
        'acept3' => 'boolean',
        'acept4' => 'boolean',
    ];

    public function legajo()
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }

    public function nivel()
    {
        return $this->belongsTo(Nivel::class, 'idNivel');
    }

    public function terlec()
    {
        return $this->belongsTo(Terlec::class, 'idTerlec');
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'idCursos', 'Id');
    }

    public function condicion()
    {
        return $this->belongsTo(Condicion::class, 'idCondiciones');
    }

    public function cuotasBeca()
    {
        return $this->belongsTo(CuotasBeca::class, 'idCuotasbecas');
    }

    public function sanciones()
    {
        return $this->hasMany(Sancion::class, 'idMatricula');
    }

    public function inasistencias()
    {
        return $this->hasMany(Inasistencia::class, 'idMatricula');
    }
}
