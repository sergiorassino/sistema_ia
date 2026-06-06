<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Terlec extends Model
{
    protected $table = 'terlec';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;
    protected $fillable = [
        'ano',
        'orden',
    ];

    protected $casts = [
        'ano'   => 'integer',
        'orden' => 'integer',
    ];

    public function matriculas()
    {
        return $this->hasMany(Matricula::class, 'idTerlec');
    }

    public function cursos()
    {
        return $this->hasMany(Curso::class, 'idTerlec');
    }

    /** Listados y selects: año más reciente primero (arriba en el desplegable). */
    public function scopeOrdenado($query)
    {
        return $query->orderByDesc('ano')->orderByDesc('orden')->orderByDesc('id');
    }

    /**
     * Opciones para desplegables de año lectivo (mismo criterio en todo el sistema).
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function paraSelector()
    {
        return static::ordenado()->get(['id', 'ano']);
    }
}
