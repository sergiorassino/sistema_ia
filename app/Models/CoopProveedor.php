<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoopProveedor extends Model
{
    protected $table = 'coop_proveedores';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'cuit',
        'telefono',
        'email',
        'direccion',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function egresos(): HasMany
    {
        return $this->hasMany(CoopEgreso::class, 'id_proveedor');
    }
}
