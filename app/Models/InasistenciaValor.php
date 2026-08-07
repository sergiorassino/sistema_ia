<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class InasistenciaValor extends Model
{
    protected $table = 'inasistencias_valores';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'concepto',
        'texto_cidi',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
    ];

    /**
     * IDs de tipo (`inasistencias.tipo`) que corresponden a educación física.
     *
     * @return Collection<int, string> claves normalizadas como string del id numérico
     */
    public static function idsEducacionFisica(): Collection
    {
        return Cache::remember('inasistencias_valores:ids_educacion_fisica', 3600, function () {
            return static::query()
                ->get(['id', 'concepto'])
                ->filter(fn (self $v) => static::conceptoEsEducacionFisica((string) ($v->concepto ?? '')))
                ->map(fn (self $v) => (string) (int) $v->id)
                ->values();
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('inasistencias_valores:ids_educacion_fisica'));
        static::deleted(fn () => Cache::forget('inasistencias_valores:ids_educacion_fisica'));
    }

    /** Normaliza texto de concepto o de columna «Tipo» del CSV CIDI (comparación sin distinción de mayúsculas/acentos). */
    public static function normalizarTexto(string $texto): string
    {
        return static::normalizarConcepto($texto);
    }

    public static function conceptoEsLlegadaTarde(string $concepto): bool
    {
        $n = static::normalizarConcepto($concepto);

        return str_contains($n, 'llegada') || str_contains($n, 'tarde') || str_contains($n, 'tardanza');
    }

    public static function conceptoEsRetiro(string $concepto): bool
    {
        $n = static::normalizarConcepto($concepto);

        return str_contains($n, 'retiro');
    }

    /**
     * Contraturno: no es ausencia a clase (puede haber asistido al turno y faltar al contraturno).
     */
    public static function conceptoEsContraturno(string $concepto): bool
    {
        $n = static::normalizarConcepto($concepto);

        return str_contains($n, 'contraturno');
    }

    public static function conceptoEsEducacionFisica(string $concepto): bool
    {
        $n = static::normalizarConcepto($concepto);
        if ($n === '') {
            return false;
        }

        if (str_contains($n, 'edfis') || str_contains($n, 'ed fis') || str_contains($n, 'ed. fis')) {
            return true;
        }

        return str_contains($n, 'educ') && str_contains($n, 'fis');
    }

    private static function normalizarConcepto(string $concepto): string
    {
        $s = mb_strtolower(trim($concepto));
        $s = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü'], ['a', 'e', 'i', 'o', 'u', 'u'], $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return $s;
    }
}
