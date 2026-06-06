<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuota extends Model
{
    protected $table = 'cuotas';

    public $timestamps = false;

    protected $fillable = [
        'idCuotasmeses',
        'idCuotastipo',
        'idTerlec',
        'orden',
        'nombre',
        'venc1',
        'venc2',
        'venc3',
        'sinConBeca',
    ];

    protected $casts = [
        'venc1' => 'date',
        'venc2' => 'date',
        'venc3' => 'date',
        'sinConBeca' => 'integer',
        'orden' => 'integer',
    ];

    public function cuotasGeneradas()
    {
        return $this->hasMany(CuotaGenerada::class, 'idCuotas');
    }

    public function terlec()
    {
        return $this->belongsTo(Terlec::class, 'idTerlec');
    }

    public function cuotasMes()
    {
        return $this->belongsTo(CuotasMes::class, 'idCuotasmeses');
    }

    public function cuotasTipo()
    {
        return $this->belongsTo(CuotasTipo::class, 'idCuotastipo');
    }

    public function cuotasImportes()
    {
        return $this->hasMany(CuotasImporte::class, 'idCuotas');
    }
}
