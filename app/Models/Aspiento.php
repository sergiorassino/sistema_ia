<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instancia de registro de aspirantes (una por nivel + ciclo lectivo).
 *
 * Tabla legacy `aspiento`. Solo agregamos columnas nuevas; no tocamos las existentes.
 */
class Aspiento extends Model
{
    protected $table = 'aspiento';

    /**
     * Tabla legacy: la PK real es `Id` (I mayúscula).
     */
    protected $primaryKey = 'Id';

    public $timestamps = true;

    protected $fillable = [
        'idNivel',
        'idTerlec',
        'insti',
        'titulo',
        'titulo3',
        'fechdesde',
        'fechhasta',
        'token',
        'activo',
        'mensaje_publico',
    ];

    protected $casts = [
        'idNivel'   => 'integer',
        'idTerlec'  => 'integer',
        'activo'    => 'boolean',
        'fechdesde' => 'date',
        'fechhasta' => 'date',
    ];

    public function terlec(): BelongsTo
    {
        return $this->belongsTo(Terlec::class, 'idTerlec');
    }

    /** Año del ciclo lectivo configurado en la instancia (`terlec.ano`). */
    public function anoLectivo(): ?int
    {
        $ano = $this->terlec?->ano;

        return $ano !== null ? (int) $ano : null;
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Aspicurso::class, 'idAspiento');
    }

    public function aspirantes(): HasMany
    {
        return $this->hasMany(Aspirante::class, 'idAspiento');
    }

    /**
     * ¿La instancia acepta registros ahora mismo?
     */
    public function aceptaRegistros(?\DateTimeInterface $ahora = null): bool
    {
        if (! $this->activo) {
            return false;
        }
        $ahora ??= now();
        $hoy = $ahora->format('Y-m-d');

        $desde = $this->fechdesde?->format('Y-m-d');
        $hasta = $this->fechhasta?->format('Y-m-d');

        if ($desde !== null && $hoy < $desde) {
            return false;
        }
        if ($hasta !== null && $hoy > $hasta) {
            return false;
        }

        return true;
    }
}
