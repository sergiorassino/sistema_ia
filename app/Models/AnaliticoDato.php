<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnaliticoDato extends Model
{
    protected $table = 'analiticodatos';

    public $timestamps = false;

    protected $fillable = [
        'idLegajos',
        'analCohorte',
        'analObservaciones',
        'analParaCompletar',
        'analValidez',
        'serie',
        'numero',
        'analLibroFolio',
        'analFechaEmision',
        'analParaPre',
    ];

    protected $casts = [
        'analFechaEmision' => 'date',
    ];

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }
}
