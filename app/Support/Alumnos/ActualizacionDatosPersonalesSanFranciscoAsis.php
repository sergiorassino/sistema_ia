<?php

namespace App\Support\Alumnos;

use App\Models\Legajo;
use App\Models\Matricula;
use App\Support\MatriculaWeb\MatriculaWebDocumentos;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

/**
 * Actualización de datos personales — variante San Francisco de Asís (formulario completo + documentos).
 */
final class ActualizacionDatosPersonalesSanFranciscoAsis
{
    /** Texto legal bajo compromiso educativo aceptado. */
    public const TEXTO_COMPROMISO_PARENTAL =
        'Quien ejecuta esta opción, lo hace en representación de la responsabilidad parental y asume el compromiso de informar al otro progenitor y/o tutor legal.';

    /**
     * @return array{legajo: Legajo, matricula: Matricula}|null
     */
    public static function contexto(): ?array
    {
        return ActualizacionDatosPersonalesComun::contexto();
    }

    public static function estaBloqueado(Matricula $matricula): bool
    {
        return ActualizacionDatosPersonalesComun::estaBloqueado($matricula);
    }

    public static function mensajeBloqueo(Matricula $matricula): string
    {
        return ActualizacionDatosPersonalesComun::mensajeBloqueo($matricula);
    }

    /**
     * @return array{bloqueado: bool, mensaje: string}
     */
    public static function estadoBloqueo(Matricula $matricula): array
    {
        return ActualizacionDatosPersonalesComun::estadoBloqueo($matricula);
    }

    /**
     * @return array<string, bool>
     */
    public static function aceptacionesDesdeMatricula(Matricula $matricula): array
    {
        $out = [];
        foreach (MatriculaWebDocumentos::definiciones() as $clave => $def) {
            $col = $def['acept_matricula'];
            $out[$clave] = (bool) ($matricula->{$col} ?? false);
        }

        return $out;
    }

    public static function todasAceptadas(Matricula $matricula): bool
    {
        $aceptaciones = self::aceptacionesDesdeMatricula($matricula);

        foreach (MatriculaWebDocumentos::claves() as $clave) {
            if (! self::documentoDisponible($clave)) {
                continue;
            }

            if (! ($aceptaciones[$clave] ?? false)) {
                return false;
            }
        }

        return true;
    }

    public static function marcarAceptacion(Matricula $matricula, string $clave, bool $valor): void
    {
        $def = MatriculaWebDocumentos::definicion($clave);
        if ($def === null) {
            return;
        }

        $col = $def['acept_matricula'];
        $matricula->{$col} = $valor ? 1 : 0;
        $matricula->save();
    }

    public static function documentoDisponible(string $clave): bool
    {
        return MatriculaWebDocumentos::pathAlmacenado($clave) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function atributosDesdeLegajo(Legajo $legajo): array
    {
        return array_merge([
            'reglamApenom' => (string) ($legajo->reglamApenom ?? ''),
            'reglamDni' => (string) ($legajo->reglamDni ?? ''),
            'reglamEmail' => ActualizacionDatosPersonalesComun::normalizarEmailInput($legajo->reglamEmail ?? ''),
            'fechnaci' => self::fechaInput($legajo->fechnaci),
            'ln_depto' => (string) ($legajo->ln_depto ?? ''),
            'ln_provincia' => (string) ($legajo->ln_provincia ?? ''),
            'ln_pais' => (string) ($legajo->ln_pais ?? ''),
            'callenum' => (string) ($legajo->callenum ?? ''),
            'barrio' => (string) ($legajo->barrio ?? ''),
            'localidad' => (string) ($legajo->localidad ?? ''),
            'telefono' => (string) ($legajo->telefono ?? ''),
            'email' => ActualizacionDatosPersonalesComun::normalizarEmailInput($legajo->email ?? ''),
            'escori' => (string) ($legajo->escori ?? ''),
            'needes' => self::needesParaFormulario($legajo),
            'needes_detalle' => self::needesDetalleParaFormulario($legajo),
            'nombrepad' => (string) ($legajo->nombrepad ?? ''),
            'dnipad' => ActualizacionDatosPersonalesComun::textoDniDesdeLegajo($legajo->dnipad ?? ''),
            'telepad' => (string) ($legajo->telepad ?? ''),
            'emailpad' => ActualizacionDatosPersonalesComun::normalizarEmailInput($legajo->emailpad ?? ''),
            'ocupacpad' => (string) ($legajo->ocupacpad ?? ''),
            'lugtrapad' => (string) ($legajo->lugtrapad ?? ''),
            'telltp' => (string) ($legajo->telltp ?? ''),
            'nombremad' => (string) ($legajo->nombremad ?? ''),
            'dnimad' => ActualizacionDatosPersonalesComun::textoDniDesdeLegajo($legajo->dnimad ?? ''),
            'telemad' => (string) ($legajo->telemad ?? ''),
            'emailmad' => ActualizacionDatosPersonalesComun::normalizarEmailInput($legajo->emailmad ?? ''),
            'ocupacmad' => (string) ($legajo->ocupacmad ?? ''),
            'lugtramad' => (string) ($legajo->lugtramad ?? ''),
            'telltm' => (string) ($legajo->telltm ?? ''),
            'nombretut' => (string) ($legajo->nombretut ?? ''),
            'dnitut' => self::dnitutParaFormulario($legajo->dnitut),
            'teletut' => (string) ($legajo->teletut ?? ''),
            'emailtut' => ActualizacionDatosPersonalesComun::normalizarEmailInput($legajo->emailtut ?? ''),
            'lugtratut' => (string) ($legajo->lugtratut ?? ''),
            'telltt' => (string) ($legajo->telltt ?? ''),
            'ec_padres' => (string) ($legajo->ec_padres ?? ''),
            'vivecon' => (string) ($legajo->vivecon ?? ''),
            'contacto1' => (string) ($legajo->contacto1 ?? ''),
            'contacto2' => (string) ($legajo->contacto2 ?? ''),
            'contacto3' => (string) ($legajo->contacto3 ?? ''),
            'retira1' => (string) ($legajo->retira1 ?? ''),
            'obs_web' => (string) ($legajo->obs_web ?? ''),
        ], ActualizacionDatosPersonalesComun::atributosDestinatarioFacturacionAfipDesdeLegajo($legajo));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function datosParaGuardar(array $state): array
    {
        $tutorActivo = self::tutorActivo(
            (string) ($state['nombretut'] ?? ''),
            (string) ($state['dnitut'] ?? ''),
        );

        $data = [
            'reglamApenom' => self::trimCampo($state['reglamApenom'] ?? ''),
            'reglamDni' => self::trimCampo($state['reglamDni'] ?? ''),
            'reglamEmail' => ActualizacionDatosPersonalesComun::normalizarEmailInput($state['reglamEmail'] ?? ''),
            'fechnaci' => self::parseFecha($state['fechnaci'] ?? '') ?: null,
            'ln_depto' => self::trimCampo($state['ln_depto'] ?? ''),
            'ln_provincia' => self::trimCampo($state['ln_provincia'] ?? ''),
            'ln_pais' => self::trimCampo($state['ln_pais'] ?? ''),
            'callenum' => self::trimCampo($state['callenum'] ?? ''),
            'barrio' => self::trimCampo($state['barrio'] ?? ''),
            'localidad' => self::trimCampo($state['localidad'] ?? ''),
            'telefono' => self::trimCampo($state['telefono'] ?? ''),
            'email' => ActualizacionDatosPersonalesComun::normalizarEmailInput($state['email'] ?? ''),
            'escori' => self::trimCampo($state['escori'] ?? ''),
            'needes' => ($state['needes'] ?? '') === 'si' ? 'si' : '',
            'needes_detalle' => ($state['needes'] ?? '') === 'si'
                ? self::needesDetalleParaGuardar($state['needes_detalle'] ?? '')
                : '',
            'nombrepad' => self::trimCampo($state['nombrepad'] ?? ''),
            'dnipad' => self::trimCampo($state['dnipad'] ?? ''),
            'telepad' => self::trimCampo($state['telepad'] ?? ''),
            'emailpad' => ActualizacionDatosPersonalesComun::normalizarEmailInput($state['emailpad'] ?? ''),
            'ocupacpad' => self::trimCampo($state['ocupacpad'] ?? ''),
            'lugtrapad' => self::trimCampo($state['lugtrapad'] ?? ''),
            'telltp' => self::trimCampo($state['telltp'] ?? ''),
            'nombremad' => self::trimCampo($state['nombremad'] ?? ''),
            'dnimad' => self::trimCampo($state['dnimad'] ?? ''),
            'telemad' => self::trimCampo($state['telemad'] ?? ''),
            'emailmad' => ActualizacionDatosPersonalesComun::normalizarEmailInput($state['emailmad'] ?? ''),
            'ocupacmad' => self::trimCampo($state['ocupacmad'] ?? ''),
            'lugtramad' => self::trimCampo($state['lugtramad'] ?? ''),
            'telltm' => self::trimCampo($state['telltm'] ?? ''),
            'ec_padres' => self::trimCampo($state['ec_padres'] ?? ''),
            'vivecon' => self::trimCampo($state['vivecon'] ?? ''),
            'contacto1' => self::trimCampo($state['contacto1'] ?? ''),
            'contacto2' => self::trimCampo($state['contacto2'] ?? ''),
            'contacto3' => self::trimCampo($state['contacto3'] ?? ''),
            'retira1' => self::trimCampo($state['retira1'] ?? ''),
            'obs_web' => self::trimCampo($state['obs_web'] ?? ''),
            'fechActDatos' => now()->format('Y-m-d H:i:s'),
        ];

        if ($tutorActivo) {
            $data['nombretut'] = self::trimCampo($state['nombretut'] ?? '');
            $data['dnitut'] = self::soloDigitosDni($state['dnitut'] ?? '');
            $data['teletut'] = self::trimCampo($state['teletut'] ?? '');
            $data['emailtut'] = ActualizacionDatosPersonalesComun::normalizarEmailInput($state['emailtut'] ?? '');
            $data['lugtratut'] = self::trimCampo($state['lugtratut'] ?? '');
            $data['telltt'] = self::trimCampo($state['telltt'] ?? '');
        } else {
            $data['nombretut'] = '';
            $data['dnitut'] = 0;
            $data['teletut'] = '';
            $data['emailtut'] = '';
            $data['lugtratut'] = '';
            $data['telltt'] = '';
        }

        return array_merge(
            $data,
            ActualizacionDatosPersonalesComun::datosDestinatarioFacturacionAfipParaGuardar($state)
        );
    }

    public static function guardar(Legajo $legajo, Matricula $matricula, array $state): void
    {
        $matricula = $matricula->fresh();
        if ($matricula === null || ! self::todasAceptadas($matricula)) {
            throw new \RuntimeException('Debe aceptar todos los documentos institucionales antes de guardar.');
        }

        ActualizacionDatosPersonalesComun::persistirLegajo($legajo, self::datosParaGuardar($state));
    }

    /**
     * @return array<string, string>
     */
    public static function atributosParaFormulario(Legajo $legajo): array
    {
        $attrs = self::atributosDesdeLegajo($legajo);
        foreach ($attrs as $k => $v) {
            $attrs[$k] = (string) $v;
        }

        return $attrs;
    }

    /**
     * @return array<string, mixed>
     */
    public static function reglasValidacion(string $needes = ''): array
    {
        $req = ActualizacionDatosPersonalesComun::reglaTextoObligatorioOGuion();
        $reqEmail = self::reglaEmailObligatorio();
        $opc = ['nullable', 'string', 'max:200'];

        $rules = [
            'reglamApenom' => ActualizacionDatosPersonalesComun::reglaTextoObligatorioOGuion(100),
            'reglamDni' => ActualizacionDatosPersonalesComun::reglaTextoObligatorioOGuion(20),
            'reglamEmail' => $reqEmail,
            'fechnaci' => ['required', 'date_format:d/m/Y'],
            'ln_depto' => $req,
            'ln_provincia' => $req,
            'ln_pais' => $req,
            'callenum' => $req,
            'barrio' => $req,
            'localidad' => $req,
            'email' => $reqEmail,
            'escori' => $opc,
            'needes' => ['required', Rule::in(['no', 'si'])],
            'needes_detalle' => self::reglasNeedesDetalle($needes),
            'nombrepad' => $req,
            'dnipad' => $req,
            'telepad' => $req,
            'emailpad' => $reqEmail,
            'ocupacpad' => $req,
            'lugtrapad' => $opc,
            'telltp' => $opc,
            'nombremad' => $req,
            'dnimad' => $req,
            'telemad' => $req,
            'emailmad' => $reqEmail,
            'ocupacmad' => $req,
            'lugtramad' => $opc,
            'telltm' => $opc,
            'ec_padres' => $req,
            'vivecon' => $req,
            'contacto1' => $req,
            'contacto2' => $opc,
            'contacto3' => $opc,
            'retira1' => $req,
            'obs_web' => $opc,
            'nombretut' => $opc,
            'dnitut' => ['nullable', 'string', 'max:20'],
            'teletut' => $opc,
            'emailtut' => self::reglaEmailOpcional(),
            'lugtratut' => $opc,
            'telltt' => $opc,
            'respAdmiNom' => ActualizacionDatosPersonalesComun::reglaNombreDestinatarioFacturacionAfip(),
            'respAdmiDni' => ActualizacionDatosPersonalesComun::reglaDniDestinatarioFacturacionAfip(),
        ];

        if (studentEsNivelSecundario()) {
            $rules['telefono'] = $req;
        } else {
            $rules['telefono'] = $opc;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function mensajesValidacion(): array
    {
        return array_merge([
            'fechnaci.date_format' => 'La fecha de nacimiento debe ser dd/mm/aaaa.',
            'reglamEmail.email' => 'El e-mail del adulto responsable no es válido.',
            'email.email' => 'El e-mail institucional del estudiante no es válido.',
            'emailpad.email' => 'El e-mail del padre no es válido.',
            'emailmad.email' => 'El e-mail de la madre no es válido.',
            '*.required' => 'Este campo es obligatorio. Si no corresponde, escriba un guión (-).',
            'needes.required' => 'Debe indicar si el estudiante tiene necesidades especiales.',
            'needes.in' => 'Seleccione «No» o «Sí» en necesidades especiales.',
            'needes_detalle.required' => 'Debe completar el detalle de necesidades especiales.',
        ], ActualizacionDatosPersonalesComun::mensajesValidacionDestinatarioFacturacionAfip());
    }

    /**
     * @return array<string, string>
     */
    public static function etiquetasCampos(): array
    {
        return [
            'reglamApenom' => 'Adulto responsable — Apellido y nombre',
            'reglamDni' => 'Adulto responsable — DNI',
            'reglamEmail' => 'Adulto responsable — E-mail',
            'fechnaci' => 'Estudiante — Fecha de nacimiento',
            'ln_depto' => 'Estudiante — Lugar de nac. (Depto/Partido)',
            'ln_provincia' => 'Estudiante — Provincia de nacimiento',
            'ln_pais' => 'Estudiante — País de nacimiento',
            'callenum' => 'Estudiante — Dirección (calle y nº)',
            'barrio' => 'Estudiante — Barrio',
            'localidad' => 'Estudiante — Localidad',
            'telefono' => 'Estudiante — Celular',
            'email' => 'Estudiante — E-mail institucional',
            'escori' => 'Estudiante — Escuela de origen',
            'needes' => 'Estudiante — Necesidades especiales',
            'needes_detalle' => 'Estudiante — Detalle de necesidades especiales',
            'nombrepad' => 'Padre — Apellidos y nombres',
            'dnipad' => 'Padre — DNI',
            'telepad' => 'Padre — Celular',
            'emailpad' => 'Padre — E-mail',
            'ocupacpad' => 'Padre — Ocupación',
            'lugtrapad' => 'Padre — Lugar de trabajo',
            'telltp' => 'Padre — Teléfono laboral',
            'nombremad' => 'Madre — Apellidos y nombres',
            'dnimad' => 'Madre — DNI',
            'telemad' => 'Madre — Celular',
            'emailmad' => 'Madre — E-mail',
            'ocupacmad' => 'Madre — Ocupación',
            'lugtramad' => 'Madre — Lugar de trabajo',
            'telltm' => 'Madre — Teléfono laboral',
            'nombretut' => 'Tutor legal — Nombre',
            'dnitut' => 'Tutor legal — DNI',
            'teletut' => 'Tutor legal — Teléfono',
            'emailtut' => 'Tutor legal — E-mail',
            'lugtratut' => 'Tutor legal — Lugar de trabajo',
            'telltt' => 'Tutor legal — Teléfono laboral',
            'ec_padres' => 'Adicionales — Estado civil de los padres',
            'vivecon' => 'Adicionales — Vive con',
            'contacto1' => 'Contacto de emergencia (1)',
            'contacto2' => 'Contacto de emergencia (2)',
            'contacto3' => 'Contacto de emergencia (3)',
            'retira1' => 'Personas autorizadas para el retiro del estudiante',
            'obs_web' => 'Observaciones',
        ] + ActualizacionDatosPersonalesComun::etiquetasDestinatarioFacturacionAfip();
    }

    /**
     * @return list<array{campo: string, etiqueta: string}>
     */
    public static function camposIncompletosDesdeErrores(\Illuminate\Support\MessageBag $errors): array
    {
        $etiquetas = self::etiquetasCampos();
        $lista = [];

        foreach ($errors->keys() as $campo) {
            $lista[] = [
                'campo' => $campo,
                'etiqueta' => $etiquetas[$campo] ?? ucfirst(str_replace('_', ' ', $campo)),
            ];
        }

        return $lista;
    }

    /**
     * @return list<mixed>
     */
    private static function reglasNeedesDetalle(string $needes): array
    {
        if ($needes !== 'si') {
            return ['nullable', 'string', 'max:500'];
        }

        return [
            'required',
            'string',
            'max:500',
            self::reglaClosureNeedesDetalleObligatorio(),
        ];
    }

    private static function reglaClosureNeedesDetalleObligatorio(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            $v = trim((string) $value);
            if ($v === '' || $v === '-') {
                $fail('Debe completar el detalle de necesidades especiales (centro o profesional y teléfono de contacto).');
            }
        };
    }

    private static function needesDetalleParaFormulario(Legajo $legajo): string
    {
        $v = trim((string) ($legajo->needes_detalle ?? ''));
        if ($v === '' || $v === '-') {
            return '';
        }

        return $v;
    }

    private static function needesDetalleParaGuardar(mixed $valor): string
    {
        $v = self::trimCampo($valor);
        if ($v === '-') {
            return '';
        }

        return $v;
    }

    public static function tutorActivo(string $nombre, string $dni): bool
    {
        $nombre = trim($nombre);
        $dniLimpio = preg_replace('/\D/', '', $dni) ?? '';

        if ($nombre === '') {
            return false;
        }

        return $dniLimpio !== '' && $dniLimpio !== '0';
    }

    /**
     * @return list<mixed>
     */
    private static function reglaEmailObligatorio(): array
    {
        return [
            'required',
            'string',
            'max:120',
            self::reglaClosureEmail(false),
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function reglaEmailOpcional(): array
    {
        return [
            'nullable',
            'string',
            'max:120',
            self::reglaClosureEmail(true),
        ];
    }

    private static function reglaClosureEmail(bool $opcional): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($opcional): void {
            if (! ActualizacionDatosPersonalesComun::emailInputAceptado($value, $opcional)) {
                $fail('Debe ingresar un e-mail válido o un guión (-) si no corresponde.');
            }
        };
    }

    private static function trimCampo(mixed $v): string
    {
        return ActualizacionDatosPersonalesComun::normalizarTextoInput($v);
    }

    private static function soloDigitosDni(mixed $v): int
    {
        $digits = preg_replace('/\D/', '', (string) $v) ?? '';

        return $digits === '' ? 0 : (int) $digits;
    }

    private static function needesParaFormulario(Legajo $legajo): string
    {
        $v = mb_strtolower(trim((string) ($legajo->needes ?? '')));

        if ($v === 'si' || $v === 'sí' || $v === '1') {
            return 'si';
        }

        if ($v === 'no' || $v === 'n' || $v === '0') {
            return 'no';
        }

        if ($v === '' && $legajo->fechActDatos !== null) {
            return 'no';
        }

        return '';
    }

    private static function fechaInput(mixed $valor): string
    {
        if ($valor instanceof Carbon) {
            return $valor->format('d/m/Y');
        }

        if ($valor === null || $valor === '') {
            return '';
        }

        try {
            return Carbon::parse((string) $valor)->format('d/m/Y');
        } catch (\Throwable) {
            return trim((string) $valor);
        }
    }

    private static function dnitutParaFormulario(mixed $valor): string
    {
        $s = trim((string) ($valor ?? ''));

        return ($s === '' || $s === '0') ? '' : $s;
    }

    private static function parseFecha(string $texto): ?string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $texto)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
