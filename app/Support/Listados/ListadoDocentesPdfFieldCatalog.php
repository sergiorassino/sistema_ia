<?php

namespace App\Support\Listados;

use App\Models\CampoProfesor;
use App\Models\SolapaLegajoProfesor;
use Illuminate\Support\Facades\Schema;

/**
 * Campos permitidos para PDF/Excel de listado de docentes (columnas de `profesores`).
 */
final class ListadoDocentesPdfFieldCatalog
{
    public const KEY_APELLIDO_NOMBRE = 'profesores.apellido_nombre';

    /** @var list<string> */
    private const KEYS_APELLIDO_NOMBRE = ['profesores.apellido', 'profesores.nombre'];

    /** @var list<string> */
    public const DEFAULT_KEYS = [
        'profesores.apellido',
        'profesores.nombre',
        'profesores.dni',
    ];

    /**
     * Sin permiso de datos personales, solo estas columnas (apellido, nombre, DNI).
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function restringirPorPermisoDatosPersonales(array $keys): array
    {
        if (puedeVerDatosPersonalesDocentes()) {
            return $keys;
        }

        $allowed = array_flip(self::DEFAULT_KEYS);
        $out = [];
        foreach ($keys as $k) {
            if (isset($allowed[$k]) && ! in_array($k, $out, true)) {
                $out[] = $k;
            }
        }

        return $out !== [] ? $out : self::DEFAULT_KEYS;
    }

    /**
     * Bloques de columnas para la UI del listado, respetando el permiso de datos personales.
     *
     * @return list<array{titulo: string, items: list<array{key: string, label: string}>}>
     */
    public static function groupedForUiPorSolapasSegunPermiso(): array
    {
        $blocks = self::groupedForUiPorSolapas();
        if (puedeVerDatosPersonalesDocentes()) {
            return $blocks;
        }

        $allowed = array_flip(self::DEFAULT_KEYS);
        $out = [];
        foreach ($blocks as $bloque) {
            $items = [];
            foreach ($bloque['items'] as $item) {
                if (isset($allowed[$item['key']])) {
                    $items[] = $item;
                }
            }
            if ($items !== []) {
                $out[] = ['titulo' => $bloque['titulo'], 'items' => $items];
            }
        }

        return $out !== []
            ? $out
            : [['titulo' => 'Identificación', 'items' => [
                ['key' => 'profesores.apellido', 'label' => 'Apellido'],
                ['key' => 'profesores.nombre', 'label' => 'Nombre'],
                ['key' => 'profesores.dni', 'label' => 'DNI'],
            ]]];
    }

    /** @var array<string, array{label: string, group: string, table: string, column: string, needs_profesortipo?: bool}>|null */
    private static ?array $mergedDefinitions = null;

    /** @var array<string, array{label: string, group: string, table: string, column: string, needs_profesortipo?: bool}> */
    private const DEFINITIONS = [
        'profesores.apellido' => ['label' => 'Apellido', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'apellido'],
        'profesores.nombre' => ['label' => 'Nombre', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'nombre'],
        'profesores.dni' => ['label' => 'DNI', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'dni'],
        'profesores.IdTipoProf' => ['label' => 'Rol', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'IdTipoProf', 'needs_profesortipo' => true],
        'profesores.cuil' => ['label' => 'CUIL', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'cuil'],
        'profesores.sexo' => ['label' => 'Sexo', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'sexo'],
        'profesores.email' => ['label' => 'Email', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'email'],
        'profesores.emailInsti' => ['label' => 'Email institucional', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'emailInsti'],
        'profesores.callenum' => ['label' => 'Calle y número', 'group' => 'Domicilio', 'table' => 'profesores', 'column' => 'callenum'],
        'profesores.barrio' => ['label' => 'Barrio', 'group' => 'Domicilio', 'table' => 'profesores', 'column' => 'barrio'],
        'profesores.telefono' => ['label' => 'Teléfono', 'group' => 'Domicilio', 'table' => 'profesores', 'column' => 'telefono'],
        'profesores.celular' => ['label' => 'Celular', 'group' => 'Domicilio', 'table' => 'profesores', 'column' => 'celular'],
        'profesores.nacion' => ['label' => 'Nacionalidad', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'nacion'],
        'profesores.estacivi' => ['label' => 'Estado civil', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'estacivi'],
        'profesores.legJunta' => ['label' => 'Legajo Junta', 'group' => 'Legajos', 'table' => 'profesores', 'column' => 'legJunta'],
        'profesores.legEscuela' => ['label' => 'Legajo escuela', 'group' => 'Legajos', 'table' => 'profesores', 'column' => 'legEscuela'],
        'profesores.fechnaci' => ['label' => 'Fecha de nacimiento', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'fechnaci'],
        'profesores.titulo' => ['label' => 'Título', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'titulo'],
        'profesores.numreg' => ['label' => 'N° registro', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'numreg'],
        'profesores.apto' => ['label' => 'Apto', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'apto'],
        'profesores.incapac' => ['label' => 'Incapacidad', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'incapac'],
        'profesores.escalafonD' => ['label' => 'Escalafón D', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'escalafonD'],
        'profesores.escalafonE' => ['label' => 'Escalafón E', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'escalafonE'],
        'profesores.cargo' => ['label' => 'Cargo', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'cargo'],
        'profesores.obs' => ['label' => 'Observaciones', 'group' => 'Docente', 'table' => 'profesores', 'column' => 'obs'],
    ];

    public static function alias(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    /** @return list<string> */
    public static function allowedKeys(): array
    {
        return array_keys(self::mergedDefinitions());
    }

    /**
     * @param  list<string>  $requested
     * @return list<string>
     */
    public static function normalizeSelection(array $requested): array
    {
        $allowed = array_flip(self::allowedKeys());
        $out = [];
        foreach ($requested as $k) {
            $k = trim((string) $k);
            if ($k !== '' && isset($allowed[$k]) && ! in_array($k, $out, true)) {
                $out[] = $k;
            }
        }

        return $out !== [] ? $out : self::DEFAULT_KEYS;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function fusionarApellidoNombre(array $keys): array
    {
        $tieneApellido = in_array('profesores.apellido', $keys, true);
        $tieneNombre = in_array('profesores.nombre', $keys, true);
        if (! $tieneApellido && ! $tieneNombre) {
            return $keys;
        }

        $out = [];
        $fusionInsertada = false;
        foreach ($keys as $key) {
            if (in_array($key, self::KEYS_APELLIDO_NOMBRE, true)) {
                if (! $fusionInsertada) {
                    $out[] = self::KEY_APELLIDO_NOMBRE;
                    $fusionInsertada = true;
                }

                continue;
            }
            $out[] = $key;
        }

        return $out;
    }

    public static function incluyeApellidoONombre(array $keys): bool
    {
        return in_array('profesores.apellido', $keys, true) || in_array('profesores.nombre', $keys, true);
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{key: string, label: string, alias: string}>
     */
    public static function columnsForPdf(array $keys): array
    {
        $cols = [];
        foreach (self::fusionarApellidoNombre($keys) as $key) {
            if ($key === self::KEY_APELLIDO_NOMBRE) {
                $cols[] = [
                    'key' => $key,
                    'label' => 'Apellido y nombre',
                    'alias' => self::alias($key),
                ];

                continue;
            }

            $def = self::definition($key);
            if ($def === null) {
                continue;
            }
            $cols[] = [
                'key' => $key,
                'label' => $def['label'],
                'alias' => self::alias($key),
            ];
        }

        return $cols;
    }

    public static function valorApellidoNombre(object $fila, bool $vacioComoGuion = true): string
    {
        $apellido = trim((string) ($fila->{self::alias('profesores.apellido')} ?? ''));
        $nombre = trim((string) ($fila->{self::alias('profesores.nombre')} ?? ''));
        $texto = EstudiantesDatosConsulta::formatearApellidoNombre($apellido, $nombre);

        if ($texto === '') {
            return $vacioComoGuion ? '—' : '';
        }

        return $texto;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function selectExpressions(array $keys): array
    {
        $keysConsulta = $keys;
        if (self::incluyeApellidoONombre($keys)) {
            foreach (self::KEYS_APELLIDO_NOMBRE as $fijo) {
                if (! in_array($fijo, $keysConsulta, true)) {
                    $keysConsulta[] = $fijo;
                }
            }
        }

        $expr = [];
        foreach ($keysConsulta as $key) {
            $def = self::definition($key);
            if ($def === null) {
                continue;
            }
            $alias = self::alias($key);
            $expr[] = $def['table'].'.'.$def['column'].' as '.$alias;
        }

        if (self::needsProfesorTipoJoin($keysConsulta)) {
            $expr[] = 'profesortipo.tipo as profesortipo_tipo';
        }

        return $expr;
    }

    /** @param  list<string>  $keys */
    public static function needsProfesorTipoJoin(array $keys): bool
    {
        foreach ($keys as $key) {
            $def = self::definition($key);
            if ($def !== null && ($def['needs_profesortipo'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private static function itemsPdfColumnasFijasDocente(): array
    {
        $items = [];
        foreach (CampoProfesor::COLUMNAS_FIJAS_DOCENTE as $col) {
            $key = 'profesores.'.$col;
            $def = self::definition($key);
            if ($def !== null) {
                $items[] = ['key' => $key, 'label' => $def['label']];
            }
        }

        return $items;
    }

    /**
     * @return list<array{titulo: string, items: list<array{key: string, label: string}>}>
     */
    private static function bloquesPdfLegajoSinParametrizacion(): array
    {
        $fijos = self::itemsPdfColumnasFijasDocente();
        $blocks = [];
        if ($fijos !== []) {
            $blocks[] = ['titulo' => 'Identificación', 'items' => $fijos];
        }

        return $blocks;
    }

    public static function canonicalProfesorColumnName(string $columnaDb): ?string
    {
        $key = self::catalogKeyForProfesorColumna($columnaDb);
        if ($key === null) {
            return null;
        }
        $def = self::definition($key);

        return $def['column'] ?? null;
    }

    private static function catalogKeyForProfesorColumna(string $columnaDb): ?string
    {
        $columnaDb = trim($columnaDb);
        if ($columnaDb === '') {
            return null;
        }

        $key = 'profesores.'.$columnaDb;
        if (isset(self::DEFINITIONS[$key])) {
            return $key;
        }
        foreach (self::DEFINITIONS as $k => $def) {
            if ($def['table'] === 'profesores' && strcasecmp($def['column'], $columnaDb) === 0) {
                return $k;
            }
        }

        $phys = self::physicalProfesorColumnName($columnaDb);
        if ($phys === null) {
            return null;
        }
        $dynKey = 'profesores.'.$phys;

        return isset(self::mergedDefinitions()[$dynKey]) ? $dynKey : null;
    }

    private static function physicalProfesorColumnName(string $columnaDb): ?string
    {
        if (! Schema::hasTable('profesores')) {
            return null;
        }
        $needle = strtolower(trim($columnaDb));
        foreach (Schema::getColumnListing('profesores') as $phys) {
            $phys = (string) $phys;
            if (strtolower($phys) === $needle) {
                return $phys;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{label: string, group: string, table: string, column: string, needs_profesortipo?: bool}>
     */
    private static function mergedDefinitions(): array
    {
        if (self::$mergedDefinitions !== null) {
            return self::$mergedDefinitions;
        }

        $merged = self::DEFINITIONS;
        if (! Schema::hasTable('profesores') || ! Schema::hasTable('campos_profesores')) {
            self::$mergedDefinitions = $merged;

            return self::$mergedDefinitions;
        }

        $staticPhysLower = [];
        foreach (self::DEFINITIONS as $def) {
            if (($def['table'] ?? '') === 'profesores') {
                $staticPhysLower[strtolower((string) $def['column'])] = true;
            }
        }

        $columnasAsignadas = CampoProfesor::query()
            ->whereNotNull('solapa_legajo_profesor_id')
            ->pluck('columna')
            ->map(fn ($c) => trim((string) $c))
            ->unique()
            ->all();

        foreach ($columnasAsignadas as $raw) {
            if ($raw === '' || in_array($raw, CampoProfesor::COLUMNAS_EXCLUIDAS, true)) {
                continue;
            }
            $phys = self::physicalProfesorColumnName($raw);
            if ($phys === null) {
                continue;
            }
            if (isset($staticPhysLower[strtolower($phys)])) {
                continue;
            }
            $dynKey = 'profesores.'.$phys;
            if (isset($merged[$dynKey])) {
                continue;
            }
            $merged[$dynKey] = [
                'label' => str_replace('_', ' ', ucfirst($phys)),
                'group' => 'Legajo',
                'table' => 'profesores',
                'column' => $phys,
            ];
        }

        self::$mergedDefinitions = $merged;

        return self::$mergedDefinitions;
    }

    /**
     * @return array{label: string, group: string, table: string, column: string, needs_profesortipo?: bool}|null
     */
    private static function definition(string $key): ?array
    {
        $m = self::mergedDefinitions();

        return $m[$key] ?? null;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private static function itemsProfesorCamposParaSolapaPdf(SolapaLegajoProfesor $solapa): array
    {
        $items = [];
        $campos = CampoProfesor::query()
            ->where('solapa_legajo_profesor_id', $solapa->id)
            ->whereNotNull('solapa_legajo_profesor_id')
            ->whereNotIn('columna', CampoProfesor::COLUMNAS_FIJAS_DOCENTE)
            ->orderBy('orden_en_solapa')
            ->orderBy('columna')
            ->get(['columna', 'etiqueta']);

        foreach ($campos as $c) {
            $col = trim((string) $c->columna);
            $catalogKey = self::catalogKeyForProfesorColumna($col);
            if ($catalogKey === null) {
                continue;
            }
            $def = self::definition($catalogKey);
            if ($def === null) {
                continue;
            }
            $etiqueta = $c->etiqueta;
            $label = ($etiqueta !== null && $etiqueta !== '') ? (string) $etiqueta : $def['label'];
            $items[] = ['key' => $catalogKey, 'label' => $label];
        }

        return $items;
    }

    /**
     * @return list<array{titulo: string, items: list<array{key: string, label: string}>}>
     */
    private static function armarBloquesPdfPorSolapas(): array
    {
        $solapas = SolapaLegajoProfesor::query()->orderBy('orden')->get(['id', 'nombre', 'slug']);
        if ($solapas->isEmpty()) {
            return [];
        }

        $blocks = [];
        foreach ($solapas as $solapa) {
            $items = [];

            if (strcasecmp((string) $solapa->slug, 'docente') === 0) {
                foreach (self::itemsPdfColumnasFijasDocente() as $row) {
                    $items[] = $row;
                }
            }

            foreach (self::itemsProfesorCamposParaSolapaPdf($solapa) as $row) {
                $items[] = $row;
            }

            $blocks[] = ['titulo' => (string) $solapa->nombre, 'items' => $items];
        }

        return $blocks;
    }

    /**
     * @return list<array{titulo: string, items: list<array{key: string, label: string}>}>
     */
    public static function groupedForUiPorSolapas(): array
    {
        if (! Schema::hasTable('solapas_legajo_profesor') || ! Schema::hasTable('campos_profesores')) {
            return self::bloquesPdfLegajoSinParametrizacion();
        }

        $blocks = self::armarBloquesPdfPorSolapas();

        return $blocks !== [] ? $blocks : self::bloquesPdfLegajoSinParametrizacion();
    }

    /** @return list<string> */
    public static function keysOrdenadosExportPorSolapas(): array
    {
        $keys = [];
        foreach (self::groupedForUiPorSolapas() as $bloque) {
            foreach ($bloque['items'] as $item) {
                $k = $item['key'];
                if (! in_array($k, $keys, true)) {
                    $keys[] = $k;
                }
            }
        }

        return $keys;
    }
}
