<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaseProvisorio extends Model
{
    protected $table = 'paseprovisorio';

    public $timestamps = false;

    protected $fillable = [
        'idLegajos',
        'fechaEmision',
        'mateAdeud',
        'cursosCompletos',
        'cursar',
        'preAnte',
    ];

    protected $casts = [
        'fechaEmision' => 'date',
    ];

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }
}
