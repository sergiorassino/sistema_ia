<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Matplan extends Model
{
    protected $table = 'matplan';

    public $timestamps = false;

    protected $fillable = [
        'idCurPlan',
        'matPlanMateria',
        'ord',
        'abrev',
        'codGE',
        'codGE2',
        'codGE3',
    ];

    public function curplan()
    {
        return $this->belongsTo(Curplan::class, 'idCurPlan');
    }
}

