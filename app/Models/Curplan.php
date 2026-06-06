<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curplan extends Model
{
    protected $table = 'curplan';

    public $timestamps = false;

    protected $fillable = [
        'idPlan',
        'curPlanCurso',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'idPlan');
    }

    public function cursos()
    {
        return $this->hasMany(Curso::class, 'idCurPlan');
    }

    public function materias()
    {
        return $this->hasMany(Matplan::class, 'idCurPlan');
    }
}
