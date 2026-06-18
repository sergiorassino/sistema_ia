<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuotaGenerada extends Model
{
    protected $table = 'cuotasgeneradas';

    public $timestamps = false;

    protected $fillable = [
        'idTerlec',
        'idLegajos',
        'idCursos',
        'idMatricula',
        'idCuotas',
        'idCuotastipo',
        'idCuotasmeses',
        'idCuotasbecas',
        'venc1',
        'venc2',
        'venc3',
        'importe',
        'bonificacion',
        'interes',
        'pagado',
        'faltapa',
        'fechaPago',
        'obs',
        'ultUpload',
        'nueVenc',
        'nroComp',
        'mensajeResultado',
        'difePlan',
        'fechaDifePlan',
        'avisoPago',
    ];

    protected $casts = [
        'venc1' => 'date',
        'venc2' => 'date',
        'venc3' => 'date',
        'nueVenc' => 'date',
        'fechaPago' => 'datetime',
        'fechaDifePlan' => 'date',
        'importe' => 'float',
        'bonificacion' => 'float',
        'interes' => 'float',
        'pagado' => 'float',
        'faltapa' => 'float',
        'avisoPago' => 'boolean',
    ];

    public function legajo()
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'idCursos', 'Id');
    }

    public function cuota()
    {
        return $this->belongsTo(Cuota::class, 'idCuotas');
    }

    public function terlec()
    {
        return $this->belongsTo(Terlec::class, 'idTerlec');
    }

    public function beca()
    {
        return $this->belongsTo(CuotasBeca::class, 'idCuotasbecas');
    }

    public function pagos()
    {
        return $this->hasMany(CuotaPago::class, 'idCuotasGeneradas');
    }
}
