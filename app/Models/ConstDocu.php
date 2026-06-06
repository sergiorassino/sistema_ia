<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstDocu extends Model
{
    protected $table = 'constdocu';

    public $timestamps = false;

    protected $fillable = [
        'idLegajos',
        'certifde',
        'otorpor',
        'fechotor',
        'parnacop',
        'parapre',
        'fechemis',
    ];

    protected $casts = [
        'fechotor' => 'date',
        'fechemis' => 'date',
    ];

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }
}
