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
        'fechaRegistro',
        'cantidad',
        'motivo',
        'acta',
        'solipor',
        'publicada',
        'comunicadaPadres',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fechaRegistro' => 'datetime',
        'publicada' => 'boolean',
        'comunicadaPadres' => 'boolean',
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

    /** Texto del impreso: "Fecha de Registro: dd/mm/aaaa hh:mm" o vacío si no hay dato. */
    public function lineaFechaRegistroImpreso(): string
    {
        if ($this->fechaRegistro === null) {
            return '';
        }

        return 'Fecha de Registro: '.$this->fechaRegistro->format('d/m/Y H:i');
    }
}

