<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoopEgreso extends Model
{
    protected $table = 'coop_egresos';

    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'fecha',
        'concepto',
        'importe',
        'importe_letras',
        'orden_numero',
        'firmante',
        'id_medio_pago',
        'medio_pago',
        'id_profesor',
        'anulado',
        'created_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
        'orden_numero' => 'integer',
        'anulado' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(CoopProveedor::class, 'id_proveedor');
    }

    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(CoopMedioPago::class, 'id_medio_pago');
    }
}
