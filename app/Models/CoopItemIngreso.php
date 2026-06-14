<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoopItemIngreso extends Model
{
    protected $table = 'coop_items_ingreso';

    public $timestamps = false;

    protected $fillable = [
        'id_rubro',
        'nombre',
        'anio',
        'precio',
        'orden',
        'activo',
    ];

    protected $casts = [
        'anio' => 'integer',
        'precio' => 'decimal:2',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(CoopRubroIngreso::class, 'id_rubro');
    }
}
