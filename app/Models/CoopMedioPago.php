<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoopMedioPago extends Model
{
    protected $table = 'coop_medios_pago';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'orden',
        'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function ingresos(): HasMany
    {
        return $this->hasMany(CoopIngreso::class, 'id_medio_pago');
    }

    public function egresos(): HasMany
    {
        return $this->hasMany(CoopEgreso::class, 'id_medio_pago');
    }
}
