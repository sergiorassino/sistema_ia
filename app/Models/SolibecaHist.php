<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolibecaHist extends Model
{
    protected $table = 'solibecahist';

    public $timestamps = false;

    protected $fillable = [
        'idLegajos',
        'fecha',
        'nro',
    ];

    protected $casts = [
        'idLegajos' => 'integer',
        'nro' => 'integer',
    ];

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }
}
