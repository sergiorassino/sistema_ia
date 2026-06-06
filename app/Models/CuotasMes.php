<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuotasMes extends Model
{
    protected $table = 'cuotasmeses';

    public $timestamps = false;

    protected $fillable = [
        'mes',
    ];
}
