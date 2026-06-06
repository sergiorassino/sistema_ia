<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SancionTipo extends Model
{
    protected $table = 'sanciontipo';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'tipo',
    ];
}

