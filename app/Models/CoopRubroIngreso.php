<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoopRubroIngreso extends Model
{
    protected $table = 'coop_rubros_ingreso';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'tipo',
        'es_anual',
        'orden',
        'activo',
    ];

    protected $casts = [
        'es_anual' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CoopItemIngreso::class, 'id_rubro');
    }

    /**
     * @return array<string, string>
     */
    public static function etiquetasTipo(): array
    {
        return [
            'por_alumno' => 'Cuotas Sociales',
            'uniforme' => 'Uniformes',
            'eventual' => 'Eventual',
        ];
    }

    public function etiquetaTipo(): string
    {
        return self::etiquetasTipo()[$this->tipo] ?? (string) $this->tipo;
    }
}
