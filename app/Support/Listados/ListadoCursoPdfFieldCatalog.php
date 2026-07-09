<?php

namespace App\Support\Listados;

use App\Models\CampoLegajo;
use App\Models\SolapaLegajo;
use Illuminate\Support\Facades\Schema;

/**
 * Campos permitidos para el PDF de listado por curso (principalmente columnas de `legajos`;
 * el controlador puede añadir columnas de matrícula/condición según el filtro de condición).
 * Solo se aceptan claves de este catálogo en la query string; nunca input libre hacia SQL.
 */
final class ListadoCursoPdfFieldCatalog
{
    /** Columna virtual: apellido y nombre del alumno en una sola celda. */
    public const KEY_APELLIDO_NOMBRE = 'legajos.apellido_nombre';

    private const KEYS_APELLIDO_NOMBRE = ['legajos.apellido', 'legajos.nombre'];

    /** Claves antiguas (bloqueos en legajos) → matrícula por ciclo. */
    private const LEGACY_KEY_MAP = [
        'legajos.bloqmatr' => 'matricula.bloqmatr',
        'legajos.bloqadmi' => 'matricula.bloqadmi',
    ];

    public const DEFAULT_KEYS = [
        'legajos.apellido',
        'legajos.nombre',
        'legajos.dni',
    ];

    /** @var array<string, array{label: string, group: string, table: string, column: string, needs_condiciones?: bool}>|null */
    private static ?array $mergedDefinitions = null;

    /** @var array<string, array{label: string, group: string, table: string, column: string, needs_condiciones?: bool}> */
    private const DEFINITIONS = [
        // — Alumno —
        'legajos.apellido' => ['label' => 'Apellido', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'apellido'],
        'legajos.nombre' => ['label' => 'Nombre', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'nombre'],
        'legajos.dni' => ['label' => 'DNI', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'dni'],
        'legajos.cuil' => ['label' => 'CUIL', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'cuil'],
        'legajos.fechnaci' => ['label' => 'Fecha de nacimiento', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'fechnaci'],
        'legajos.sexo' => ['label' => 'Sexo', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'sexo'],
        'legajos.nacion' => ['label' => 'Nacionalidad', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'nacion'],
        'legajos.tipoalumno' => ['label' => 'Tipo de alumno', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'tipoalumno'],
        'legajos.legajo' => ['label' => 'Legajo', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'legajo'],
        'legajos.libro' => ['label' => 'Libro', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'libro'],
        'legajos.folio' => ['label' => 'Folio', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'folio'],
        'legajos.pwrd' => ['label' => 'Contraseña (autogestión)', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'pwrd'],
        'legajos.codigo' => ['label' => 'Código', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'codigo'],
        'legajos.identif' => ['label' => 'Identificación', 'group' => 'Alumno', 'table' => 'legajos', 'column' => 'identif'],
        // — Domicilio y contacto —
        'legajos.callenum' => ['label' => 'Calle y número', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'callenum'],
        'legajos.barrio' => ['label' => 'Barrio', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'barrio'],
        'legajos.localidad' => ['label' => 'Localidad', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'localidad'],
        'legajos.codpos' => ['label' => 'Código postal', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'codpos'],
        'legajos.ln_ciudad' => ['label' => 'Lugar nac. — ciudad', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'ln_ciudad'],
        'legajos.ln_depto' => ['label' => 'Lugar nac. — departamento', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'ln_depto'],
        'legajos.ln_provincia' => ['label' => 'Lugar nac. — provincia', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'ln_provincia'],
        'legajos.ln_pais' => ['label' => 'Lugar nac. — país', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'ln_pais'],
        'legajos.telefono' => ['label' => 'Teléfono', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'telefono'],
        'legajos.email' => ['label' => 'Email', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'email'],
        'legajos.contacto1' => ['label' => 'Contacto 1', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'contacto1'],
        'legajos.contacto2' => ['label' => 'Contacto 2', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'contacto2'],
        'legajos.contacto3' => ['label' => 'Contacto 3', 'group' => 'Domicilio y contacto', 'table' => 'legajos', 'column' => 'contacto3'],
        // — Madre —
        'legajos.nombremad' => ['label' => 'Madre — nombre', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'nombremad'],
        'legajos.dnimad' => ['label' => 'Madre — DNI', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'dnimad'],
        'legajos.vivemad' => ['label' => 'Madre — vive', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'vivemad'],
        'legajos.fechnacmad' => ['label' => 'Madre — fecha nac.', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'fechnacmad'],
        'legajos.nacionmad' => ['label' => 'Madre — nacionalidad', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'nacionmad'],
        'legajos.estacivimad' => ['label' => 'Madre — estado civil', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'estacivimad'],
        'legajos.domimad' => ['label' => 'Madre — domicilio', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'domimad'],
        'legajos.cpmad' => ['label' => 'Madre — CP', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'cpmad'],
        'legajos.ocupacmad' => ['label' => 'Madre — ocupación', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'ocupacmad'],
        'legajos.sitlabmad' => ['label' => 'Madre — situación laboral', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'sitlabmad'],
        'legajos.lugtramad' => ['label' => 'Madre — lugar de trabajo', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'lugtramad'],
        'legajos.telemad' => ['label' => 'Madre — teléfono', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'telemad'],
        'legajos.telecelmad' => ['label' => 'Madre — celular', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'telecelmad'],
        'legajos.telltm' => ['label' => 'Madre — tel. laboral', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'telltm'],
        'legajos.emailmad' => ['label' => 'Madre — email', 'group' => 'Madre', 'table' => 'legajos', 'column' => 'emailmad'],
        // — Padre —
        'legajos.nombrepad' => ['label' => 'Padre — nombre', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'nombrepad'],
        'legajos.dnipad' => ['label' => 'Padre — DNI', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'dnipad'],
        'legajos.vivepad' => ['label' => 'Padre — vive', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'vivepad'],
        'legajos.fechnacpad' => ['label' => 'Padre — fecha nac.', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'fechnacpad'],
        'legajos.nacionpad' => ['label' => 'Padre — nacionalidad', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'nacionpad'],
        'legajos.estacivipad' => ['label' => 'Padre — estado civil', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'estacivipad'],
        'legajos.domipad' => ['label' => 'Padre — domicilio', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'domipad'],
        'legajos.cppad' => ['label' => 'Padre — CP', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'cppad'],
        'legajos.ocupacpad' => ['label' => 'Padre — ocupación', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'ocupacpad'],
        'legajos.sitlabpad' => ['label' => 'Padre — situación laboral', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'sitlabpad'],
        'legajos.lugtrapad' => ['label' => 'Padre — lugar de trabajo', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'lugtrapad'],
        'legajos.telepad' => ['label' => 'Padre — teléfono', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'telepad'],
        'legajos.telecelpad' => ['label' => 'Padre — celular', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'telecelpad'],
        'legajos.telltp' => ['label' => 'Padre — tel. laboral', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'telltp'],
        'legajos.emailpad' => ['label' => 'Padre — email', 'group' => 'Padre', 'table' => 'legajos', 'column' => 'emailpad'],
        // — Tutor / responsable —
        'legajos.nombretut' => ['label' => 'Tutor — nombre', 'group' => 'Tutor / responsable', 'table' => 'legajos', 'column' => 'nombretut'],
        'legajos.dnitut' => ['label' => 'Tutor — DNI', 'group' => 'Tutor / responsable', 'table' => 'legajos', 'column' => 'dnitut'],
        'legajos.teletut' => ['label' => 'Tutor — teléfono', 'group' => 'Tutor / responsable', 'table' => 'legajos', 'column' => 'teletut'],
        'legajos.emailtut' => ['label' => 'Tutor — email', 'group' => 'Tutor / responsable', 'table' => 'legajos', 'column' => 'emailtut'],
        'legajos.ocupactut' => ['label' => 'Tutor — ocupación', 'group' => 'Tutor / responsable', 'table' => 'legajos', 'column' => 'ocupactut'],
        'legajos.lugtratut' => ['label' => 'Tutor — lugar de trabajo', 'group' => 'Tutor / responsable', 'table' => 'legajos', 'column' => 'lugtratut'],
        'legajos.telltt' => ['label' => 'Tutor — tel. laboral', 'group' => 'Tutor / responsable', 'table' => 'legajos', 'column' => 'telltt'],
        'legajos.respAdmiNom' => ['label' => 'Resp. administrativo — nombre', 'group' => 'Tutor / responsable', 'table' => 'legajos', 'column' => 'respAdmiNom'],
        'legajos.respAdmiDni' => ['label' => 'Resp. administrativo — DNI', 'group' => 'Tutor / responsable', 'table' => 'legajos', 'column' => 'respAdmiDni'],
        // — Escolaridad y otros —
        'legajos.escori' => ['label' => 'Escolaridad origen', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'escori'],
        'legajos.destino' => ['label' => 'Destino', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'destino'],
        'legajos.emeravis' => ['label' => 'Emergencia / avisar a', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'emeravis'],
        'legajos.retira' => ['label' => 'Retira', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'retira'],
        'legajos.retira1' => ['label' => 'Retira (1)', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'retira1'],
        'legajos.retira2' => ['label' => 'Retira (2)', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'retira2'],
        'legajos.obs' => ['label' => 'Observaciones', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'obs'],
        'legajos.obs_web' => ['label' => 'Observaciones web', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'obs_web'],
        'legajos.vivecon' => ['label' => 'Vive con', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'vivecon'],
        'legajos.hermanos' => ['label' => 'Hermanos', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'hermanos'],
        'legajos.ec_padres' => ['label' => 'Estado civil padres', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'ec_padres'],
        'legajos.parroquia' => ['label' => 'Parroquia', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'parroquia'],
        'legajos.needes' => ['label' => 'N.E.E.', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'needes'],
        'legajos.needes_detalle' => ['label' => 'N.E.E. — detalle', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'needes_detalle'],
        'legajos.certDisc' => ['label' => 'Certificado discapacidad', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'certDisc'],
        'legajos.motivo_detalle' => ['label' => 'Motivo — detalle', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'motivo_detalle'],
        'legajos.acopro' => ['label' => 'Acompañante', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'acopro'],
        'legajos.acopro_detalle' => ['label' => 'Acompañante — detalle', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'acopro_detalle'],
        'legajos.idnivel' => ['label' => 'ID nivel (legajo)', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'idnivel'],
        'legajos.idFamilias' => ['label' => 'ID familia', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'idFamilias'],
        'legajos.fechhora' => ['label' => 'Fecha/hora registro', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'fechhora'],
        'legajos.fechActDatos' => ['label' => 'Última actualización datos', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'fechActDatos'],
        'legajos.reglamApenom' => ['label' => 'Reglamento — apellido y nombre', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'reglamApenom'],
        'legajos.reglamDni' => ['label' => 'Reglamento — DNI', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'reglamDni'],
        'legajos.reglamEmail' => ['label' => 'Reglamento — email', 'group' => 'Escolaridad y otros', 'table' => 'legajos', 'column' => 'reglamEmail'],
        // — Matrícula (tabla matricula) —
        'matricula.nroMatricula' => ['label' => 'N° matrícula', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'nroMatricula'],
        'matricula.fechaMatricula' => ['label' => 'Fecha de matrícula', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'fechaMatricula'],
        'matricula.bloqmatr' => ['label' => 'Bloqueo pedagógico', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'bloqmatr'],
        'matricula.bloqadmi' => ['label' => 'Bloqueo administrativo', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'bloqadmi'],
        'matricula.obsMatr' => ['label' => 'Obs. matrícula', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'obsMatr'],
        'matricula.obsAnual' => ['label' => 'Obs. anual', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'obsAnual'],
        'matricula.conducta1' => ['label' => 'Conducta 1°', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'conducta1'],
        'matricula.conducta2' => ['label' => 'Conducta 2°', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'conducta2'],
        'matricula.acept1' => ['label' => 'Aceptación 1', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'acept1'],
        'matricula.acept2' => ['label' => 'Aceptación 2', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'acept2'],
        'matricula.acept3' => ['label' => 'Aceptación 3', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'acept3'],
        'matricula.acept4' => ['label' => 'Aceptación 4', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'acept4'],
        'matricula.inscripto' => ['label' => 'Inscripto', 'group' => 'Matrícula', 'table' => 'matricula', 'column' => 'inscripto'],
        'condiciones.condicion' => ['label' => 'Condición de matrícula', 'group' => 'Matrícula', 'table' => 'condiciones', 'column' => 'condicion', 'needs_condiciones' => true],
    ];

    /** Etiqueta para una columna de la tabla `legajos` (ABM legajo / campos_legajo). */
    public static function legajoColumnLabel(string $column): string
    {
        $key = 'legajos.'.$column;
        $def = self::definition($key);

        return $def['label'] ?? str_replace('_', ' ', ucfirst($column));
    }

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
     * @return list<string> orden conservado, sin duplicados, solo permitidos
     */
    public static function normalizeSelection(array $requested): array
    {
        $allowed = array_flip(self::allowedKeys());
        $out = [];
        foreach ($requested as $k) {
            $k = trim((string) $k);
            if ($k === '') {
                continue;
            }
            $k = self::LEGACY_KEY_MAP[$k] ?? $k;
            if (isset($allowed[$k]) && ! in_array($k, $out, true)) {
                $out[] = $k;
            }
        }

        return $out !== [] ? $out : self::DEFAULT_KEYS;
    }

    /**
     * Si el listado incluye apellido y/o nombre, los reemplaza por una sola columna virtual.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function fusionarApellidoNombre(array $keys): array
    {
        $tieneApellido = in_array('legajos.apellido', $keys, true);
        $tieneNombre = in_array('legajos.nombre', $keys, true);
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
        return in_array('legajos.apellido', $keys, true) || in_array('legajos.nombre', $keys, true);
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
        $apellido = trim((string) ($fila->{self::alias('legajos.apellido')} ?? ''));
        $nombre = trim((string) ($fila->{self::alias('legajos.nombre')} ?? ''));
        $texto = EstudiantesDatosConsulta::formatearApellidoNombre($apellido, $nombre);

        if ($texto === '') {
            return $vacioComoGuion ? '—' : '';
        }

        return $texto;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string> expresiones para select()
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

        return $expr;
    }

    public static function needsCondicionesJoin(array $keys): bool
    {
        foreach ($keys as $key) {
            $def = self::definition($key);
            if ($def === null) {
                continue;
            }
            if (($def['needs_condiciones'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bloque fijo Apellido / Nombre / DNI (no están en `campos_legajo`) cuando no hay solapa «alumno» en BD.
     *
     * @return list<array{key: string, label: string}>
     */
    private static function itemsPdfColumnasFijasAlumno(): array
    {
        $items = [];
        foreach (CampoLegajo::COLUMNAS_FIJAS_ALUMNO as $col) {
            $key = 'legajos.'.$col;
            $def = self::definition($key);
            if ($def !== null) {
                $items[] = ['key' => $key, 'label' => $def['label']];
            }
        }

        return $items;
    }

    /**
     * Fallback cuando no existen `solapas_legajo` / `campos_legajo` o no se pudo armar ningún bloque:
     * solo el trío fijo (apellido, nombre, DNI).
     *
     * @return list<array{titulo: string, items: list<array{key: string, label: string}>}>
     */
    private static function bloquesPdfLegajoSinParametrizacion(): array
    {
        $fijos = self::itemsPdfColumnasFijasAlumno();
        $blocks = [];
        if ($fijos !== []) {
            $blocks[] = ['titulo' => 'Identificación', 'items' => $fijos];
        }

        return $blocks;
    }

    /**
     * Nombre de columna física en `legajos` tal como figura en el catálogo PDF, o null si no hay entrada.
     */
    public static function canonicalLegajoColumnName(string $columnaDb): ?string
    {
        $key = self::catalogKeyForLegajoColumna($columnaDb);
        if ($key === null) {
            return null;
        }
        $def = self::definition($key);

        return $def['column'] ?? null;
    }

    /**
     * Clave de catálogo `legajos.*` para un nombre de columna física (insensible a mayúsculas).
     */
    private static function catalogKeyForLegajoColumna(string $columnaDb): ?string
    {
        $columnaDb = trim($columnaDb);
        if ($columnaDb === '') {
            return null;
        }

        $key = 'legajos.'.$columnaDb;
        if (isset(self::DEFINITIONS[$key])) {
            return $key;
        }
        foreach (self::DEFINITIONS as $k => $def) {
            if ($def['table'] === 'legajos' && strcasecmp($def['column'], $columnaDb) === 0) {
                return $k;
            }
        }

        $phys = self::physicalLegajoColumnName($columnaDb);
        if ($phys === null) {
            return null;
        }
        $dynKey = 'legajos.'.$phys;

        return isset(self::mergedDefinitions()[$dynKey]) ? $dynKey : null;
    }

    /**
     * Nombre de columna en `legajos` tal como figura en el esquema, o null si no existe (comparación insensible).
     */
    private static function physicalLegajoColumnName(string $columnaDb): ?string
    {
        if (! Schema::hasTable('legajos')) {
            return null;
        }
        $needle = strtolower(trim($columnaDb));
        foreach (Schema::getColumnListing('legajos') as $phys) {
            $phys = (string) $phys;
            if (strtolower($phys) === $needle) {
                return $phys;
            }
        }

        return null;
    }

    /**
     * Catálogo efectivo: definiciones estáticas más columnas de `legajos` asignadas en `campos_legajo`
     * que aún no están en el catálogo (el ABM del legajo las muestra igual; el PDF debe poder listarlas).
     *
     * @return array<string, array{label: string, group: string, table: string, column: string, needs_condiciones?: bool}>
     */
    private static function mergedDefinitions(): array
    {
        if (self::$mergedDefinitions !== null) {
            return self::$mergedDefinitions;
        }

        $merged = self::DEFINITIONS;
        if (! Schema::hasTable('legajos') || ! Schema::hasTable('campos_legajo')) {
            self::$mergedDefinitions = $merged;

            return self::$mergedDefinitions;
        }

        $staticPhysLower = [];
        foreach (self::DEFINITIONS as $def) {
            if (($def['table'] ?? '') === 'legajos') {
                $staticPhysLower[strtolower((string) $def['column'])] = true;
            }
        }

        $columnasAsignadas = CampoLegajo::query()
            ->whereNotNull('solapa_legajo_id')
            ->pluck('columna')
            ->map(fn ($c) => trim((string) $c))
            ->unique()
            ->all();

        foreach ($columnasAsignadas as $raw) {
            if ($raw === '' || in_array($raw, CampoLegajo::COLUMNAS_EXCLUIDAS, true)) {
                continue;
            }
            $phys = self::physicalLegajoColumnName($raw);
            if ($phys === null) {
                continue;
            }
            if (isset($staticPhysLower[strtolower($phys)])) {
                continue;
            }
            $dynKey = 'legajos.'.$phys;
            if (isset($merged[$dynKey])) {
                continue;
            }
            $merged[$dynKey] = [
                'label' => str_replace('_', ' ', ucfirst($phys)),
                'group' => 'Legajo',
                'table' => 'legajos',
                'column' => $phys,
            ];
        }

        self::$mergedDefinitions = $merged;

        return self::$mergedDefinitions;
    }

    /**
     * @return array{label: string, group: string, table: string, column: string, needs_condiciones?: bool}|null
     */
    private static function definition(string $key): ?array
    {
        $m = self::mergedDefinitions();

        return $m[$key] ?? null;
    }

    /**
     * Campos de legajo en una solapa para el selector PDF (misma regla que el listado por solapa).
     *
     * @return list<array{key: string, label: string}>
     */
    private static function itemsLegajoCamposParaSolapaPdf(SolapaLegajo $solapa): array
    {
        $items = [];
        $campos = CampoLegajo::query()
            ->where('solapa_legajo_id', $solapa->id)
            ->whereNotNull('solapa_legajo_id')
            ->whereNotIn('columna', CampoLegajo::COLUMNAS_FIJAS_ALUMNO)
            ->orderBy('orden_en_solapa')
            ->orderBy('columna')
            ->get(['columna', 'etiqueta']);

        foreach ($campos as $c) {
            $col = trim((string) $c->columna);
            $catalogKey = self::catalogKeyForLegajoColumna($col);
            if ($catalogKey === null) {
                continue;
            }
            $def = self::definition($catalogKey);
            if ($def === null) {
                continue;
            }
            $defLabel = $def['label'];
            $etiqueta = $c->etiqueta;
            $label = ($etiqueta !== null && $etiqueta !== '') ? (string) $etiqueta : $defLabel;
            $items[] = ['key' => $catalogKey, 'label' => $label];
        }

        return $items;
    }

    /**
     * Un fieldset por fila de `solapas_legajo` en orden `orden`. Contenido: `campos_legajo` de esa solapa
     * (más apellido/nombre/DNI si slug = alumno). Las solapas sin campos asignados generan bloque con
     * `items` vacío (p. ej. solapa «Otros» hasta que se muevan columnas en parametrización).
     *
     * @return list<array{titulo: string, items: list<array{key: string, label: string}>}>
     */
    private static function armarBloquesPdfPorSolapas(): array
    {
        $solapas = SolapaLegajo::query()->orderBy('orden')->get(['id', 'nombre', 'slug']);
        if ($solapas->isEmpty()) {
            return [];
        }

        $blocks = [];
        foreach ($solapas as $solapa) {
            $items = [];

            if (strcasecmp((string) $solapa->slug, 'alumno') === 0) {
                foreach (self::itemsPdfColumnasFijasAlumno() as $row) {
                    $items[] = $row;
                }
            }

            foreach (self::itemsLegajoCamposParaSolapaPdf($solapa) as $row) {
                $items[] = $row;
            }

            // Siempre un bloque por solapa (p. ej. «Otros» recién creada sin campos en `campos_legajo`):
            // antes se omitía y la solapa no aparecía en el selector del PDF.
            $blocks[] = ['titulo' => (string) $solapa->nombre, 'items' => $items];
        }

        return $blocks;
    }

    /**
     * Grupos de campos para la UI del listado PDF.
     *
     * @param  list<string>|null  $soloColumnasLegajosVisibles  nombres de columna física en `legajos`;
     *                                                          null = no filtrar por visibilidad (tabla de parametrización vacía o inexistente).
     * @return array<string, list<array{key: string, label: string}>>
     */
    public static function groupedForUi(?array $soloColumnasLegajosVisibles = null): array
    {
        $visibles = null;
        if ($soloColumnasLegajosVisibles !== null) {
            $visibles = array_flip($soloColumnasLegajosVisibles);
        }

        $groups = [];
        foreach (self::mergedDefinitions() as $key => $def) {
            if ($def['table'] === 'legajos' && $visibles !== null) {
                $col = $def['column'];
                if (! isset($visibles[$col])) {
                    continue;
                }
            }
            $g = $def['group'];
            if (! isset($groups[$g])) {
                $groups[$g] = [];
            }
            $groups[$g][] = ['key' => $key, 'label' => $def['label']];
        }

        return $groups;
    }

    /**
     * UI del listado PDF por curso: un bloque por fila de `solapas_legajo` (título = `nombre`, orden = `orden`).
     * En cada bloque: filas de `campos_legajo` asignadas a esa solapa. Slug reservado `alumno`: agrega apellido,
     * nombre y DNI al inicio. Las columnas de `legajos` asignadas a una solapa y ausentes del catálogo estático
     * se incorporan al catálogo en tiempo de ejecución (misma fuente que el ABM de legajo).
     * Sin tablas o sin bloques armables: {@see bloquesPdfLegajoSinParametrizacion()}.
     * (Matrícula y condición de cursada se gestionan fuera del legajo; no se ofrecen en este selector.)
     *
     * @return list<array{titulo: string, items: list<array{key: string, label: string}>}>
     */
    public static function groupedForUiPorSolapas(): array
    {
        if (! Schema::hasTable('solapas_legajo') || ! Schema::hasTable('campos_legajo')) {
            return self::bloquesPdfLegajoSinParametrizacion();
        }

        $blocks = self::armarBloquesPdfPorSolapas();

        return $blocks !== [] ? $blocks : self::bloquesPdfLegajoSinParametrizacion();
    }

    /**
     * Claves de columnas de `legajos` para exportación Excel: orden de solapas y campos
     * parametrizados (misma fuente que el listado PDF por solapa).
     *
     * @return list<string>
     */
    public static function keysOrdenadosExportLegajoPorSolapas(): array
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

    /**
     * Grupos de campos de `legajos` para el formulario del ABM de legajo.
     * Solo incluye entradas cuya tabla es `legajos`; aplica el mismo filtro de
     * visibilidad que `groupedForUi()` pero descarta matrícula/condiciones.
     *
     * @param  list<string>|null  $soloColumnasLegajosVisibles  null = sin filtro (modo "mostrar todo").
     * @return array<string, list<array{key: string, label: string, column: string}>>
     */
    public static function groupedLegajosFieldsForUi(?array $soloColumnasLegajosVisibles = null): array
    {
        $visibles = $soloColumnasLegajosVisibles !== null ? array_flip($soloColumnasLegajosVisibles) : null;

        $groups = [];
        foreach (self::mergedDefinitions() as $key => $def) {
            if ($def['table'] !== 'legajos') {
                continue;
            }
            if ($visibles !== null && ! isset($visibles[$def['column']])) {
                continue;
            }
            $g = $def['group'];
            if (! isset($groups[$g])) {
                $groups[$g] = [];
            }
            $groups[$g][] = ['key' => $key, 'label' => $def['label'], 'column' => $def['column']];
        }

        return $groups;
    }
}
