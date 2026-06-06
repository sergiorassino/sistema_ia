<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertAsistProf extends Model
{
    protected $table = 'certasistprof';

    public $timestamps = false;

    protected $fillable = [
        'idProfesores',
        'fecha',
        'texto',
        'parapre',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'idProfesores');
    }
}
