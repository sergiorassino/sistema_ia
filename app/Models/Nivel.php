<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nivel extends Model
{
    protected $table = 'niveles';
    public $timestamps = false;
    protected $fillable = [
        'nivel',
        'abrev',
    ];

    public function legajos()
    {
        return $this->hasMany(Legajo::class, 'idnivel');
    }

    public function matriculas()
    {
        return $this->hasMany(Matricula::class, 'idNivel');
    }

    public function cursos()
    {
        return $this->hasMany(Curso::class, 'idNivel');
    }
}
