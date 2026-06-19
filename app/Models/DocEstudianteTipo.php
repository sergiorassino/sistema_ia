<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de documentación que la familia puede subir en actualización de datos.
 */
class DocEstudianteTipo extends Model
{
    public const EXTENSIONES_SOPORTADAS = ['jpg', 'jpeg', 'pdf'];

    public const MAX_ARCHIVOS_LIMITE = 20;

    public const MAX_MB_DEFAULT = 2;

    public const MAX_MB_LIMITE = 50;

    public const MAX_EXPLICACION_LENGTH = 500;

    protected $table = 'doc_estudiante_tipos';

    public $timestamps = false;

    protected $fillable = [
        'clave',
        'etiqueta',
        'explicacion',
        'extensiones',
        'max_archivos',
        'max_mb',
        'obligatorio',
        'activo',
        'orden',
    ];

    protected $casts = [
        'extensiones' => 'array',
        'max_archivos' => 'integer',
        'max_mb' => 'integer',
        'obligatorio' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenados(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('etiqueta');
    }

    public static function tablaDisponible(): bool
    {
        return Schema::hasTable('doc_estudiante_tipos');
    }

    /**
     * @return list<string>
     */
    public function extensionesNormalizadas(): array
    {
        $raw = is_array($this->extensiones) ? $this->extensiones : [];
        $out = [];
        foreach ($raw as $ext) {
            $ext = strtolower(trim((string) $ext));
            if (in_array($ext, self::EXTENSIONES_SOPORTADAS, true)) {
                $out[] = $ext;
            }
        }

        return array_values(array_unique($out));
    }

    public function maxMbEfectivo(): int
    {
        $mb = (int) ($this->max_mb ?? 0);
        if ($mb < 1) {
            $mb = self::MAX_MB_DEFAULT;
        }

        return min(self::MAX_MB_LIMITE, $mb);
    }

    /**
     * @return array{
     *     id: int,
     *     clave: string,
     *     label: string,
     *     extensiones: list<string>,
     *     max_archivos: int,
     *     max_mb: int,
     *     obligatorio: bool,
     *     explicacion: ?string
     * }
     */
    public function toDefinicionAutogestion(): array
    {
        return [
            'id' => (int) $this->id,
            'clave' => (string) $this->clave,
            'label' => (string) $this->etiqueta,
            'explicacion' => self::explicacionNormalizada($this->explicacion ?? null),
            'extensiones' => $this->extensionesNormalizadas(),
            'max_archivos' => max(1, min(self::MAX_ARCHIVOS_LIMITE, (int) $this->max_archivos)),
            'max_mb' => $this->maxMbEfectivo(),
            'obligatorio' => (bool) $this->obligatorio,
        ];
    }

    public static function normalizarClave(string $clave): string
    {
        $clave = strtolower(trim($clave));
        $clave = preg_replace('/[^a-z0-9_\-]/', '', $clave) ?? '';

        return substr($clave, 0, 40);
    }

    public static function explicacionNormalizada(mixed $valor): ?string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        return mb_substr($texto, 0, self::MAX_EXPLICACION_LENGTH);
    }
}
