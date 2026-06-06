<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sancion extends Model
{
    protected $table = 'sanciones';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idMatricula',
        'idTipoSancion',
        'idProfesores',
        'fecha',
        'cantidad',
        'motivo',
        'solipor',
        'publicada',
    ];

    protected $casts = [
        'fecha' => 'date',
        'publicada' => 'boolean',
    ];

    public function tipo()
    {
        return $this->belongsTo(SancionTipo::class, 'idTipoSancion');
    }

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'idMatricula');
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class, 'idProfesores');
    }
}

