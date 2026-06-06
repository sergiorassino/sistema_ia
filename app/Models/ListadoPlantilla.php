<?php

namespace App\Models;

use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\Listados\ListadoCursoPdfFieldCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Plantilla de listado por curso: combinación de columnas y condición guardada por el
 * operador para reutilizar (parametrización por colegio; el alcance es por `idNivel`).
 */
class ListadoPlantilla extends Model
{
    protected $table = 'listados_plantillas';

    public $timestamps = false;

    protected $fillable = [
        'idNivel',
        'nombre',
        'campos',
        'condicion',
        'orden',
    ];

    protected $casts = [
        'idNivel' => 'integer',
        'campos' => 'array',
        'orden' => 'integer',
    ];

    /**
     * Plantillas de un nivel, en orden de presentación.
     *
     * @return Collection<int, static>
     */
    public static function paraNivel(int $idNivel): Collection
    {
        if ($idNivel < 1) {
            return new Collection();
        }

        return static::query()
            ->where('idNivel', $idNivel)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
    }

    /** @return list<string> */
    public function etiquetasCampos(): array
    {
        $keys = ListadoCursoPdfFieldCatalog::normalizeSelection(
            is_array($this->campos) ? $this->campos : [],
        );

        return array_map(
            fn (array $col) => (string) $col['label'],
            ListadoCursoPdfFieldCatalog::columnsForPdf($keys),
        );
    }

    public function etiquetaCondicionUi(): string
    {
        return ListadoCursoCondicionFiltro::etiquetaUi((string) $this->condicion);
    }
}
