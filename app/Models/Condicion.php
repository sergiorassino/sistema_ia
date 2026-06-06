<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Condicion extends Model
{
    protected $table = 'condiciones';

    public $timestamps = false;

    protected $fillable = [
        'orden',
        'condicion',
        'proteg',
    ];

    public function matriculas()
    {
        return $this->hasMany(Matricula::class, 'idCondiciones');
    }
}
