<?php

namespace App\Models;

use App\Support\Listados\ListadoCursoPdfFieldCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class CampoLegajo extends Model
{
    protected $table = 'campos_legajo';

    public $timestamps = false;

    protected $fillable = [
        'columna',
        'etiqueta',
        'visible_listado',
        'orden',
        'solapa_legajo_id',
        'orden_en_solapa',
    ];

    protected $casts = [
        'visible_listado' => 'boolean',
        'orden' => 'integer',
        'solapa_legajo_id' => 'integer',
        'orden_en_solapa' => 'integer',
    ];

    /** Columnas de `legajos` excluidas de la parametrización (seguridad). */
    public const COLUMNAS_EXCLUIDAS = ['pwrd', 'telecelmad', 'telecelpad'];

    /**
     * Apellido, nombre y DNI no se parametrizan: siempre en la solapa Alumno del formulario.
     *
     * @var list<string>
     */
    public const COLUMNAS_FIJAS_ALUMNO = ['apellido', 'nombre', 'dni'];

    public function solapa(): BelongsTo
    {
        return $this->belongsTo(SolapaLegajo::class, 'solapa_legajo_id');
    }

    // ─── Legajo ABM ───────────────────────────────────────────────────────────

    /**
     * Columnas activas para el formulario del legajo (aquellas con solapa asignada).
     * Devuelve null si no hay parametrización activa (tabla vacía o sin asignaciones).
     *
     * @return list<string>|null
     */
    public static function columnasActivasParaLegajo(): ?array
    {
        if (! Schema::hasTable('campos_legajo') || ! static::query()->exists()) {
            return null;
        }

        $cols = static::query()
            ->whereNotNull('solapa_legajo_id')
            ->whereNotIn('columna', self::COLUMNAS_FIJAS_ALUMNO)
            ->orderBy('solapa_legajo_id')
            ->orderBy('orden_en_solapa')
            ->orderBy('columna')
            ->pluck('columna')
            ->map(fn ($c) => (string) $c)
            ->values()
            ->all();

        return $cols !== [] ? $cols : null;
    }

    /**
     * Columnas por slug de solapa, en orden `orden_en_solapa` (sin apellido/nombre/dni).
     *
     * @return array<string, list<array{columna: string, etiqueta: ?string}>>
     */
    public static function camposPorSolapaSlugOrdenados(): array
    {
        if (! Schema::hasTable('campos_legajo') || ! Schema::hasTable('solapas_legajo')) {
            return [];
        }

        $rows = static::query()
            ->whereNotNull('solapa_legajo_id')
            ->whereNotIn('columna', self::COLUMNAS_FIJAS_ALUMNO)
            ->join('solapas_legajo', 'solapas_legajo.id', '=', 'campos_legajo.solapa_legajo_id')
            ->orderBy('solapas_legajo.orden')
            ->orderBy('campos_legajo.orden_en_solapa')
            ->orderBy('campos_legajo.columna')
            ->get(['campos_legajo.columna', 'campos_legajo.etiqueta', 'solapas_legajo.slug']);

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

    // ─── Listado PDF ──────────────────────────────────────────────────────────

    /**
     * Filtra claves `legajos.*` según columnas con solapa y listado visible.
     * Con parametrización activa descarta cualquier otra clave (p. ej. matrícula en query string antigua).
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function aplicarVisibilidadListadoPdf(array $keys): array
    {
        if (! Schema::hasTable('campos_legajo') || ! static::query()->exists()) {
            return $keys;
        }

        $permitidas = static::columnasLegajosVisiblesParaUi();
        if ($permitidas === null) {
            return $keys;
        }

        $flip = array_flip($permitidas);
        $out = [];
        foreach ($keys as $k) {
            if (str_starts_with($k, 'legajos.')) {
                $col = substr($k, strlen('legajos.'));
                if (! isset($flip[$col])) {
                    continue;
                }
                $out[] = $k;

                continue;
            }
            // Con parametrización activa solo pasan columnas de `legajos`; el PDF puede añadir condición después.
            continue;
        }

        if ($out !== []) {
            return $out;
        }

        $defecto = [];
        foreach (['legajos.apellido', 'legajos.nombre', 'legajos.dni'] as $k) {
            $col = substr($k, strlen('legajos.'));
            if (isset($flip[$col])) {
                $defecto[] = $k;
            }
        }

        return $defecto !== [] ? $defecto : [];
    }

    /**
     * Columnas de `legajos` elegibles en el selector del PDF: el trío fijo más las que tienen
     * solapa asignada (mismo criterio que el formulario del legajo por solapa). Null si no hay tabla o está vacía.
     *
     * @return list<string>|null
     */
    public static function columnasLegajosVisiblesParaUi(): ?array
    {
        if (! Schema::hasTable('campos_legajo') || ! static::query()->exists()) {
            return null;
        }

        $cols = static::query()
            ->whereNotNull('solapa_legajo_id')
            ->whereNotIn('columna', self::COLUMNAS_FIJAS_ALUMNO)
            ->orderBy('orden')
            ->orderBy('columna')
            ->pluck('columna')
            ->map(function ($c) {
                $raw = (string) $c;

                return ListadoCursoPdfFieldCatalog::canonicalLegajoColumnName($raw) ?? $raw;
            })
            ->values()
            ->all();

        return array_values(array_unique(array_merge(self::COLUMNAS_FIJAS_ALUMNO, $cols)));
    }
}
