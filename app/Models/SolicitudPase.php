<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudPase extends Model
{
    protected $table = 'solicitudpase';

    public $timestamps = false;

    protected $fillable = [
        'idLegajos',
        'fecha',
        'destino',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }
}
