<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Legajo extends Authenticatable
{
    protected $table = 'legajos';

    public $timestamps = false;

    /**
     * Permite columnas extra por colegio (p. ej. telealte1_nom) sin listarlas todas en fillable.
     * Mass assignment solo bloquea identificador y contraseña.
     */
    protected $guarded = ['id', 'pwrd'];

    protected $hidden = ['pwrd'];

    protected $casts = [
        'fechnaci' => 'date',
        'fechnacmad' => 'date',
        'fechnacpad' => 'date',
        'fechhora' => 'datetime',
        'fechActDatos' => 'datetime',
        'bloqmatr' => 'boolean',
        'bloqadmi' => 'boolean',
    ];

    public function familia()
    {
        return $this->belongsTo(Familia::class, 'idFamilias');
    }

    public function sexoCatalogo()
    {
        return $this->belongsTo(Sexo::class, 'sexo');
    }

    public function nivel()
    {
        return $this->belongsTo(Nivel::class, 'idnivel');
    }

    public function matriculas()
    {
        return $this->hasMany(Matricula::class, 'idLegajos');
    }

    public function calificaciones()
    {
        return $this->hasMany(Calificacion::class, 'idLegajos');
    }

    public function cuotasGeneradas()
    {
        return $this->hasMany(CuotaGenerada::class, 'idLegajos');
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return (string) ($this->pwrd ?? '');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->apellido.', '.$this->nombre);
    }

    /**
     * Base de datos MySQL seleccionada en la conexión activa (colegio / tenant actual).
     */
    public static function nombreBaseDatosConectada(): string
    {
        $row = DB::selectOne('SELECT DATABASE() AS db');

        if ($row && $row->db !== null && (string) $row->db !== '') {
            return (string) $row->db;
        }

        return (string) DB::getDatabaseName();
    }

    /**
     * Columnas reales de `legajos` en la BD conectada (orden del esquema MySQL).
     *
     * @return list<string>
     */
    public static function columnasTabla(): array
    {
        if (! Schema::hasTable('legajos')) {
            return [];
        }

        $schema = static::nombreBaseDatosConectada();
        if ($schema === '') {
            return [];
        }

        $rows = DB::select(
            'SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$schema, 'legajos']
        );

        return array_map(static fn ($row) => (string) $row->COLUMN_NAME, $rows);
    }

    public function scopeBuscar($query, string $termino)
    {
        $termino = trim($termino);
        if ($termino === '') {
            return $query;
        }

        $palabras = preg_split('/\s+/u', $termino, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $query->where(function ($q) use ($termino, $palabras) {
            $q->where('apellido', 'like', "%{$termino}%")
                ->orWhere('nombre', 'like', "%{$termino}%")
                ->orWhere('dni', 'like', "%{$termino}%")
                ->orWhereRaw("CONCAT(apellido, ' ', nombre) LIKE ?", ["%{$termino}%"])
                ->orWhereRaw("CONCAT(apellido, ', ', nombre) LIKE ?", ["%{$termino}%"]);

            if (count($palabras) >= 2) {
                $apellido = $palabras[0];
                $nombre = implode(' ', array_slice($palabras, 1));

                $q->orWhere(function ($sub) use ($apellido, $nombre) {
                    $sub->where('apellido', 'like', "%{$apellido}%")
                        ->where('nombre', 'like', "%{$nombre}%");
                });

                $q->orWhere(function ($sub) use ($apellido, $nombre) {
                    $sub->where('nombre', 'like', "%{$apellido}%")
                        ->where('apellido', 'like', "%{$nombre}%");
                });
            }
        });
    }
}
