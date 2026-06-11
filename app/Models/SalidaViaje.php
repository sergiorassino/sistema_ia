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
     */
    public static function queryEnContexto(): Builder
    {
        $query = static::query();
        $ctx = schoolCtx();

        if (Schema::hasColumn('salidasviajes', 'idTerlec') && (int) $ctx->idTerlec > 0) {
            $query->where('idTerlec', (int) $ctx->idTerlec);
        }

        if (Schema::hasColumn('salidasviajes', 'idNivel') && (int) $ctx->idNivel > 0) {
            $query->where('idNivel', (int) $ctx->idNivel);
        }

        return $query;
    }

    public function perteneceAlContexto(): bool
    {
        $ctx = schoolCtx();

        if (Schema::hasColumn('salidasviajes', 'idTerlec')) {
            if ((int) $this->idTerlec !== (int) $ctx->idTerlec) {
                return false;
            }
        }

        if (Schema::hasColumn('salidasviajes', 'idNivel')) {
            if ((int) $this->idNivel !== (int) $ctx->idNivel) {
                return false;
            }
        }

        return true;
    }
}
