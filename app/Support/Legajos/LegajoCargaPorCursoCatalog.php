<?php

namespace App\Support\Legajos;

use App\Models\CampoLegajo;
use App\Models\SolapaLegajo;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Listados\ListadoCursoPdfFieldCatalog;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Campos editables en la carga masiva de legajo por curso.
 * Solo columnas declaradas en `campos_legajo` con solapa asignada (mismo criterio que el ABM del legajo).
 */
final class LegajoCargaPorCursoCatalog
{
    /** @var list<string> */
    private const COLUMNAS_NO_EDITABLES = [
        'apellido', 'nombre', 'dni',
        'pwrd', 'fechhora', 'fechActDatos', 'bloqmatr', 'bloqadmi',
    ];

    /** @var list<string> */
    private const COLUMNAS_FECHA = ['fechnaci', 'fechnacmad', 'fechnacpad'];

    /** @var list<string> */
    private const COLUMNAS_EMAIL = ['email', 'emailmad', 'emailpad', 'emailtut', 'reglamEmail'];

    /** @var list<string> */
    private const COLUMNAS_SI_NO = ['vivemad', 'vivepad', 'needes'];

    /** @var list<string> */
    private const COLUMNAS_TEXTAREA = ['hermanos', 'retira', 'emeravis', 'obs', 'obs_web', 'needes_detalle'];

    /**
     * Bloques para el selector: filas de `campos_legajo` agrupadas por `solapas_legajo`.
     *
     * @return list<array{titulo: string, items: list<array{key: string, label: string, column: string}>}>
     */
    public static function bloquesParaSelector(): array
    {
        if (! Schema::hasTable('campos_legajo') || ! Schema::hasTable('solapas_legajo')) {
            return [];
        }

        $permitidasFlip = array_flip(self::columnasPermitidas());
        if ($permitidasFlip === []) {
            return [];
        }

        $porSlug = CampoLegajo::camposPorSolapaSlugOrdenados();
        if ($porSlug === []) {
            return [];
        }

        $out = [];
        $solapas = SolapaLegajo::query()->orderBy('orden')->orderBy('id')->get(['id', 'nombre', 'slug']);

        foreach ($solapas as $solapa) {
            $items = [];
            foreach ($porSlug[$solapa->slug] ?? [] as $campo) {
                $col = (string) ($campo['columna'] ?? '');
                if ($col === '' || ! isset($permitidasFlip[$col])) {
                    continue;
                }
                $items[] = self::itemSelectorDesdeColumna(
                    $col,
                    isset($campo['etiqueta']) && $campo['etiqueta'] !== '' && $campo['etiqueta'] !== null
                        ? (string) $campo['etiqueta']
                        : null,
                );
            }
            if ($items !== []) {
                $out[] = [
                    'titulo' => (string) $solapa->nombre,
                    'items' => $items,
                ];
            }
        }

        return $out;
    }

    /**
     * @return array{key: string, label: string, column: string}
     */
    private static function itemSelectorDesdeColumna(string $columna, ?string $etiqueta = null): array
    {
        return [
            'key' => 'legajos.'.$columna,
            'label' => $etiqueta ?? self::etiquetaColumna($columna),
            'column' => $columna,
        ];
    }

    /**
     * @return list<string> columnas de `campos_legajo` con solapa asignada (excluye identificación y sistema).
     */
    public static function columnasPermitidas(): array
    {
        $excluidas = array_flip(array_merge(
            self::COLUMNAS_NO_EDITABLES,
            CampoLegajo::COLUMNAS_EXCLUIDAS,
        ));

        $visibles = CampoLegajo::columnasActivasParaLegajo();
        if ($visibles === null) {
            return [];
        }

        $cols = [];
        foreach ($visibles as $col) {
            if (isset($excluidas[$col])) {
                continue;
            }
            if (self::columnaExisteEnLegajos($col)) {
                $cols[] = $col;
            }
        }

        return array_values(array_unique($cols));
    }

    public static function esColumnaPermitida(string $columna): bool
    {
        $columna = trim($columna);

        return $columna !== '' && in_array($columna, self::columnasPermitidas(), true);
    }

    /**
     * @param  list<string>  $keys claves `legajos.*`
     * @return list<string> columnas permitidas, orden conservado
     */
    public static function columnasDesdeKeys(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $key = trim((string) $key);
            if (! str_starts_with($key, 'legajos.')) {
                continue;
            }
            $col = substr($key, strlen('legajos.'));
            if (self::esColumnaPermitida($col) && ! in_array($col, $out, true)) {
                $out[] = $col;
            }
        }

        return $out;
    }

    public static function etiquetaColumna(string $columna): string
    {
        return ListadoCursoPdfFieldCatalog::legajoColumnLabel($columna);
    }

    public static function tipoInput(string $columna): string
    {
        if (in_array($columna, self::COLUMNAS_FECHA, true)) {
            return 'date';
        }
        if (in_array($columna, self::COLUMNAS_EMAIL, true)) {
            return 'email';
        }
        if ($columna === 'sexo') {
            return 'sexo';
        }
        if ($columna === 'idFamilias') {
            return 'familia';
        }
        if (in_array($columna, self::COLUMNAS_SI_NO, true)) {
            return 'si_no';
        }
        if (in_array($columna, self::COLUMNAS_TEXTAREA, true)) {
            return 'textarea';
        }
        if ($columna === 'tipoalumno') {
            return 'number';
        }

        return 'text';
    }

    /**
     * @return array<string, mixed>
     */
    public static function reglasValidacion(string $columna): array
    {
        if (! self::esColumnaPermitida($columna)) {
            return ['prohibited'];
        }

        if (in_array($columna, self::COLUMNAS_FECHA, true)) {
            return ['nullable', 'date'];
        }
        if (in_array($columna, self::COLUMNAS_EMAIL, true)) {
            return ['nullable', 'email', 'max:100'];
        }
        if ($columna === 'cuil') {
            return ['nullable', 'string', 'max:13'];
        }
        if ($columna === 'sexo') {
            return ['nullable', 'integer', 'min:0'];
        }
        if ($columna === 'idFamilias') {
            return ['nullable', 'integer', 'min:1'];
        }
        if ($columna === 'tipoalumno') {
            return ['nullable', 'integer'];
        }
        if (in_array($columna, self::COLUMNAS_SI_NO, true)) {
            return ['nullable', Rule::in(['', 'si', 'no'])];
        }
        if (in_array($columna, ['dnimad', 'dnipad'], true)) {
            return ['nullable', 'string', 'max:10'];
        }
        if (in_array($columna, ['dnitut', 'respAdmiDni'], true)) {
            return ['nullable', 'string', 'max:20'];
        }

        return ['nullable', 'string', 'max:4000'];
    }

    public static function normalizarValor(string $columna, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (in_array($columna, self::COLUMNAS_FECHA, true)) {
            $s = trim((string) $value);

            return $s !== '' ? $s : null;
        }

        if (in_array($columna, self::COLUMNAS_EMAIL, true)) {
            $s = trim((string) $value);

            return $s !== '' ? $s : null;
        }

        if ($columna === 'sexo') {
            return $value !== '' && $value !== null ? (int) $value : 0;
        }

        if ($columna === 'idFamilias') {
            $n = (int) $value;

            return $n > 0 ? $n : 1;
        }

        if ($columna === 'tipoalumno') {
            return $value !== '' && $value !== null ? (int) $value : 0;
        }

        if (in_array($columna, ['dnitut', 'respAdmiDni'], true)) {
            $s = trim((string) $value);

            // INT NOT NULL legacy: vacío → 0. VARCHAR: vacío → string vacío.
            if ($s === '') {
                return PersistenciaColumnas::columnaEsEntera('legajos', $columna) ? 0 : '';
            }

            return PersistenciaColumnas::columnaEsEntera('legajos', $columna) ? (int) $s : $s;
        }

        if (is_string($value)) {
            return trim($value) === '' ? null : trim($value);
        }

        return $value;
    }

    /**
     * Valor listo para inputs de formulario (fechas en Y-m-d).
     */
    public static function valorParaInput(string $columna, mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }

        if (in_array($columna, self::COLUMNAS_FECHA, true)) {
            if ($raw instanceof \DateTimeInterface) {
                return $raw->format('Y-m-d');
            }
            $s = trim((string) $raw);
            if ($s === '' || $s === '0000-00-00') {
                return '';
            }

            return $s;
        }

        if ($columna === 'sexo') {
            return (string) (int) $raw;
        }

        if (in_array($columna, ['dnitut', 'respAdmiDni'], true)) {
            $s = trim((string) $raw);

            return ($s === '' || $s === '0') ? '' : $s;
        }

        return (string) $raw;
    }

    private static function columnaExisteEnLegajos(string $columna): bool
    {
        if (! Schema::hasTable('legajos')) {
            return false;
        }

        foreach (Schema::getColumnListing('legajos') as $phys) {
            if (strcasecmp((string) $phys, $columna) === 0) {
                return true;
            }
        }

        return false;
    }
}
