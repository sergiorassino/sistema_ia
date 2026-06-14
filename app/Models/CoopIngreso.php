<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoopIngreso extends Model
{
    protected $table = 'coop_ingresos';

    public $timestamps = false;

    protected $fillable = [
        'tipo',
        'id_rubro',
        'id_item',
        'id_legajo',
        'id_matricula',
        'pagador_nombre',
        'pagador_vinculo',
        'pagador_email',
        'recibo_email_estado',
        'recibo_email_enviado_at',
        'recibo_email_error',
        'fecha',
        'concepto',
        'importe_bruto',
        'descuento_pct',
        'importe',
        'importe_letras',
        'recibo_numero',
        'recibo_grupo_id',
        'medio_pago',
        'id_medio_pago',
        'id_profesor',
        'anulado',
        'created_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe_bruto' => 'decimal:2',
        'descuento_pct' => 'decimal:2',
        'importe' => 'decimal:2',
        'recibo_numero' => 'integer',
        'recibo_grupo_id' => 'integer',
        'anulado' => 'boolean',
        'recibo_email_enviado_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(CoopRubroIngreso::class, 'id_rubro');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CoopItemIngreso::class, 'id_item');
    }

    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(CoopMedioPago::class, 'id_medio_pago');
    }

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'id_legajo');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'id_matricula');
    }
}
