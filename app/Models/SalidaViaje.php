<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SalidaViaje extends Model
{
    protected $table = 'salidasviajes';

    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'desde',
        'hasta',
        'texto',
        'idTerlec',
        'idNivel',
    ];

    protected $casts = [
        'desde' => 'date',
        'hasta' => 'date',
    ];

    /**
     * Viajes del contexto activo (si la tabla legacy incluye idTerlec / idNivel).
     * Valor 0 en esas columnas = registro legacy sin contexto asignado (visible en cualquier ciclo/nivel).
     */
    public static function queryEnContexto(): Builder
    {
        $query = static::query();
        $ctx = schoolCtx();

        if (Schema::hasColumn('salidasviajes', 'idTerlec') && (int) $ctx->idTerlec > 0) {
            $idTerlec = (int) $ctx->idTerlec;
            $query->where(function (Builder $q) use ($idTerlec) {
                $q->where('idTerlec', $idTerlec)->orWhere('idTerlec', 0);
            });
        }

        if (Schema::hasColumn('salidasviajes', 'idNivel') && (int) $ctx->idNivel > 0) {
            $idNivel = (int) $ctx->idNivel;
            $query->where(function (Builder $q) use ($idNivel) {
                $q->where('idNivel', $idNivel)->orWhere('idNivel', 0);
            });
        }

        return $query;
    }

    public function perteneceAlContexto(): bool
    {
        $ctx = schoolCtx();

        if (Schema::hasColumn('salidasviajes', 'idTerlec')) {
            $idTerlec = (int) $this->idTerlec;
            if ($idTerlec !== 0 && $idTerlec !== (int) $ctx->idTerlec) {
                return false;
            }
        }

        if (Schema::hasColumn('salidasviajes', 'idNivel')) {
            $idNivel = (int) $this->idNivel;
            if ($idNivel !== 0 && $idNivel !== (int) $ctx->idNivel) {
                return false;
            }
        }

        return true;
    }
}
