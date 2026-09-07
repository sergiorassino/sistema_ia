<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gabinete extends Model
{
    protected $table = 'gabinete';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'idMatricula',
        'idTipoSancion',
        'fecha',
        'cantidad',
        'motivo',
        'solipor',
        'asistentes',
        'conclusion',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function tipo()
    {
        return $this->belongsTo(GabineteTipo::class, 'idTipoSancion');
    }

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'idMatricula');
    }
}
