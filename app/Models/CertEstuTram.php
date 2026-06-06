<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertEstuTram extends Model
{
    protected $table = 'certestutram';

    public $timestamps = false;

    protected $fillable = [
        'idLegajos',
        'mateAdeud',
        'idiomaCursado',
        'preAnte',
        'fechaEmision',
    ];

    protected $casts = [
        'fechaEmision' => 'date',
    ];

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }
}
