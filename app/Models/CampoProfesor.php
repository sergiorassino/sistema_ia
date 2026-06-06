<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class CampoProfesor extends Model
{
    protected $table = 'campos_profesores';

    public $timestamps = false;

    protected $fillable = [
        'columna',
        'etiqueta',
        'visible_listado',
        'orden',
        'solapa_legajo_profesor_id',
        'orden_en_solapa',
    ];

    protected $casts = [
        'visible_listado' => 'boolean',
        'orden' => 'integer',
        'solapa_legajo_profesor_id' => 'integer',
        'orden_en_solapa' => 'integer',
    ];

    /** Columnas de `profesores` excluidas de la parametrización (seguridad / sistema). */
    public const COLUMNAS_EXCLUIDAS = [
        'pwrd',
        'permisos',
        'ult_idNivel',
        'ult_idTerlec',
        'nivel',
    ];

    /**
     * Apellido, nombre, DNI y rol no se parametrizan: siempre en la solapa DOCENTE.
     *
     * @var list<string>
     */
    public const COLUMNAS_FIJAS_DOCENTE = ['apellido', 'nombre', 'dni', 'IdTipoProf'];

    public function solapa(): BelongsTo
    {
        return $this->belongsTo(SolapaLegajoProfesor::class, 'solapa_legajo_profesor_id');
    }

    /**
     * @return list<string>|null
     */
    public static function columnasActivasParaLegajo(): ?array
    {
        if (! Schema::hasTable('campos_profesores') || ! static::query()->exists()) {
            return null;
        }

        $cols = static::query()
            ->whereNotNull('solapa_legajo_profesor_id')
            ->whereNotIn('columna', self::COLUMNAS_FIJAS_DOCENTE)
            ->orderBy('solapa_legajo_profesor_id')
            ->orderBy('orden_en_solapa')
            ->orderBy('columna')
            ->pluck('columna')
            ->map(fn ($c) => (string) $c)
            ->values()
            ->all();

        return $cols !== [] ? $cols : null;
    }

    /**
     * @return array<string, list<array{columna: string, etiqueta: ?string}>>
     */
    public static function camposPorSolapaSlugOrdenados(): array
    {
        if (! Schema::hasTable('campos_profesores') || ! Schema::hasTable('solapas_legajo_profesor')) {
            return [];
        }

        $rows = static::query()
            ->whereNotNull('solapa_legajo_profesor_id')
            ->whereNotIn('columna', self::COLUMNAS_FIJAS_DOCENTE)
            ->join('solapas_legajo_profesor', 'solapas_legajo_profesor.id', '=', 'campos_profesores.solapa_legajo_profesor_id')
            ->orderBy('solapas_legajo_profesor.orden')
            ->orderBy('campos_profesores.orden_en_solapa')
            ->orderBy('campos_profesores.columna')
            ->get(['campos_profesores.columna', 'campos_profesores.etiqueta', 'solapas_legajo_profesor.slug']);

        $map = [];
        foreach ($rows as $r) {
            $slug = (string) $r->slug;
            if (! isset($map[$slug])) {
                $map[$slug] = [];
            }
            $map[$slug][] = [
                'columna' => (string) $r->columna,
                'etiqueta' => $r->etiqueta !== null && $r->etiqueta !== '' ? (string) $r->etiqueta : null,
            ];
        }

        return $map;
    }
}
