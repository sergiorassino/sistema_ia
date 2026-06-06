<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Sexo extends Model
{
    protected $table = 'sexos';

    public $timestamps = false;

    protected $fillable = ['sexo'];

    /** @var array<int, string>|null */
    private static ?array $etiquetasPorIdCache = null;

    public static function tablaDisponible(): bool
    {
        return Schema::hasTable('sexos');
    }

    /**
     * @return array<int, string> id → nombre (`sexos.sexo`)
     */
    public static function etiquetasPorId(): array
    {
        if (self::$etiquetasPorIdCache !== null) {
            return self::$etiquetasPorIdCache;
        }

        if (! self::tablaDisponible()) {
            self::$etiquetasPorIdCache = [];

            return self::$etiquetasPorIdCache;
        }

        self::$etiquetasPorIdCache = self::query()
            ->orderBy('id')
            ->pluck('sexo', 'id')
            ->mapWithKeys(fn ($nombre, $id) => [(int) $id => (string) $nombre])
            ->all();

        return self::$etiquetasPorIdCache;
    }

    /** Opciones para &lt;select&gt; del legajo (valor = id). */
    public static function opcionesParaSelect(): Collection
    {
        if (! self::tablaDisponible()) {
            return collect();
        }

        return self::query()->orderBy('id')->get(['id', 'sexo']);
    }

    /**
     * Resuelve el valor guardado en `legajos.sexo` (id numérico o texto legacy) al nombre en `sexos`.
     */
    public static function etiquetaParaValorAlmacenado(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        $map = self::etiquetasPorId();

        if (is_numeric($valor)) {
            $id = (int) $valor;
            if ($id === 0) {
                return '';
            }

            return $map[$id] ?? (string) $valor;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }

        foreach ($map as $nombre) {
            if (strcasecmp($nombre, $texto) === 0) {
                return $nombre;
            }
        }

        return $texto;
    }
}
