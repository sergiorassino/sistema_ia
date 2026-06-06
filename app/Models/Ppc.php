<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ppc extends Model
{
    protected $table = 'ppc';

    public $timestamps = false;

    protected $fillable = [
        'idMateria',
        'idProfesor',
        'idSituRevis',
    ];

    public function profesor()
    {
        return $this->belongsTo(Profesor::class, 'idProfesor');
    }

    public function situacionRevista()
    {
        return $this->belongsTo(SituacionRevista::class, 'idSituRevis');
    }
}
