<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo legacy de situación de revista del docente (titular, suplente, interino, etc.).
 * Referenciada por `ppc.idSituRevis`.
 */
class SituacionRevista extends Model
{
    protected $table = 'situacionrevista';

    public $timestamps = false;

    protected $fillable = [
        'sitRev',
    ];
}
