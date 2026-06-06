<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertAluReg extends Model
{
    protected $table = 'certalureg';

    public $timestamps = false;

    protected $fillable = [
        'idLegajos',
        'iniFin',
        'fechIniFin',
        'prePor',
        'prePorDni',
        'preAnte',
        'fechaEmision',
    ];

    protected $casts = [
        'iniFin' => 'integer',
        'fechIniFin' => 'date',
        'fechaEmision' => 'date',
    ];

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }
}
