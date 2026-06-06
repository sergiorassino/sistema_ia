<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EstadoCivil extends Model
{
    protected $table = 'estadocivil';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    public static function tablaDisponible(): bool
    {
        return Schema::hasTable('estadocivil');
    }

    /** Opciones para &lt;select&gt; del legajo docente (valor = id). */
    public static function opcionesParaSelect(): Collection
    {
        if (! self::tablaDisponible()) {
            return collect();
        }

        return self::query()->orderBy('id')->get(['id', 'nombre']);
    }

    public static function etiquetaParaValorAlmacenado(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if (! self::tablaDisponible()) {
            return (string) $valor;
        }

        if (is_numeric($valor)) {
            $id = (int) $valor;
            if ($id === 0) {
                return '';
            }

            return (string) (self::query()->whereKey($id)->value('nombre') ?? $valor);
        }

        return trim((string) $valor);
    }
}
