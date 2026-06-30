<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailEscrito extends Model
{
    protected $table = 'emails_escritos';

    public $timestamps = false;

    protected $fillable = [
        'subject',
        'text',
        'attached',
    ];
}
