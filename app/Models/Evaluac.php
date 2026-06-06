<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluac extends Model
{
    protected $table = 'evaluac';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'idMateria',
        'idCurso',
        'fecheval',
        'temas',
        'obs',
        'fechregi',
    ];

    protected $casts = [
        'fecheval' => 'date',
        'fechregi' => 'datetime',
    ];

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'idCurso', 'Id');
    }
}
