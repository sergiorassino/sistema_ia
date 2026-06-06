<?php

namespace App\Models;

use App\Support\Comunicaciones\ComCanalRolCatalog;
use Illuminate\Database\Eloquent\Model;

class ProfesorTipo extends Model
{
    protected $table = 'profesortipo';
    public $timestamps = false;
    protected $fillable = [
        'tipo', 'accesoMenu',
    ];

    protected static function booted(): void
    {
        $invalidarCatalogoCanales = static function (): void {
            ComCanalRolCatalog::invalidarCache();
        };

        static::saved($invalidarCatalogoCanales);
        static::deleted($invalidarCatalogoCanales);
    }

    public function profesores()
    {
        return $this->hasMany(Profesor::class, 'IdTipoProf');
    }
}
