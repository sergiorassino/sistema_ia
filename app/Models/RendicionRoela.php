<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RendicionRoela extends Model
{
    protected $table = 'rendicionesroela';

    public $timestamps = false;

    protected $fillable = [
        'fechaPago',
        'fechaAcreditacion',
        'idCuotastipopago',
        'idLegajos',
        'nroPlanilla',
        'idCuotas',
        'fechVenc1',
        'importe',
        'pagado',
        'interes',
        'bonificacion',
        'nombreArchivo',
        'cadenaPago',
        'idCuotasbecas',
        'idCuotasgeneradas',
        'impactado',
        'idCursos',
        'obs',
    ];

    protected $casts = [
        'fechaPago' => 'date',
        'fechaAcreditacion' => 'date',
        'fechVenc1' => 'date',
        'importe' => 'float',
        'pagado' => 'float',
        'interes' => 'float',
        'bonificacion' => 'float',
        'impactado' => 'boolean',
    ];

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class, 'idCuotas');
    }

    public function cuotaGenerada(): BelongsTo
    {
        return $this->belongsTo(CuotaGenerada::class, 'idCuotasgeneradas');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'idCursos', 'Id');
    }

    public function tipoPago(): BelongsTo
    {
        return $this->belongsTo(CuotaTipoPago::class, 'idCuotastipopago');
    }

    public function beca(): BelongsTo
    {
        return $this->belongsTo(CuotasBeca::class, 'idCuotasbecas');
    }
}
