<?php

namespace App\Models;

use App\Support\ProfesorMenuPortal;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Profesor extends Authenticatable
{
    /** Rol «Sin Rol» en `profesortipo`. No recibe `permisos_ia`. */
    public const ID_TIPO_SIN_ROL = 1;

    protected $table = 'profesores';

    public $timestamps = false;

    /**
     * Permite columnas extra por colegio (p. ej. famiCargo) sin listarlas todas en fillable.
     * Mass assignment solo bloquea identificador y campos de sistema.
     */
    protected $guarded = ['id', 'pwrd', 'permisos', 'permisos_ia', 'ult_idNivel', 'ult_idTerlec', 'nivel'];
    protected $hidden = ['pwrd'];

    protected $casts = [
        'fechnaci'   => 'date',
        'apto'       => 'date',
        'escalafonD' => 'date',
        'escalafonE' => 'date',
    ];

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return (string) $this->pwrd;
    }

    public function tipo()
    {
        return $this->belongsTo(ProfesorTipo::class, 'IdTipoProf');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->apellido . ', ' . $this->nombre);
    }

    public function scopeBuscar($query, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('apellido', 'like', "%{$term}%")
                ->orWhere('nombre', 'like', "%{$term}%");
            if (ctype_digit($term)) {
                $q->orWhere('dni', (int) $term);
            }
        });
    }

    public function scopeDelNivel($query, ?int $idNivel)
    {
        if ($idNivel) {
            $query->where('nivel', $idNivel);
        }

        return $query;
    }

    /**
     * Personal al que se asigna `permisos_ia` (Secretaría / Administración).
     * Excluye «Sin Rol» (`IdTipoProf` = 1) y rol Profesor/a (`IdTipoProf` = 6).
     */
    public function scopeElegiblesParaPermisosIa($query)
    {
        return $query->where(function ($w) {
            $w->whereNull('IdTipoProf')
                ->orWhereNotIn('IdTipoProf', [
                    self::ID_TIPO_SIN_ROL,
                    ProfesorMenuPortal::ID_TIPO_PROFESOR_AULA,
                ]);
        });
    }
}
