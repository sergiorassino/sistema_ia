<?php

namespace App\Support\Alumnos;

use App\Models\Legajo;
use App\Models\Matricula;
use Carbon\Carbon;
use Illuminate\Support\MessageBag;

/**
 * Actualización de datos personales — variante estándar (padre, madre y tutor).
 */
final class ActualizacionDatosPersonalesEstandar
{
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
     * @return array<string, mixed>
     */
    public static function atributosDesdeLegajo(Legajo $legajo): array
    {
        return [
            'nombrepad' => (string) ($legajo->nombrepad ?? ''),
            'dnipad' => (string) ($legajo->dnipad ?? ''),
            'fechnacpad' => self::fechaInput($legajo->fechnacpad),
            'nacionpad' => (string) ($legajo->nacionpad ?? ''),
            'domipad' => (string) ($legajo->domipad ?? ''),
            'telepad' => (string) ($legajo->telepad ?? ''),
            'emailpad' => ActualizacionDatosPersonalesComun::normalizarEmailInput($legajo->emailpad ?? ''),
            'ocupacpad' => (string) ($legajo->ocupacpad ?? ''),
            'telltp' => (string) ($legajo->telltp ?? ''),
            'nombremad' => (string) ($legajo->nombremad ?? ''),
            'dnimad' => (string) ($legajo->dnimad ?? ''),
            'fechnacmad' => self::fechaInput($legajo->fechnacmad),
            'nacionmad' => (string) ($legajo->nacionmad ?? ''),
            'domimad' => (string) ($legajo->domimad ?? ''),
            'telemad' => (string) ($legajo->telemad ?? ''),
            'emailmad' => ActualizacionDatosPersonalesComun::normalizarEmailInput($legajo->emailmad ?? ''),
            'ocupacmad' => (string) ($legajo->ocupacmad ?? ''),
            'telltm' => (string) ($legajo->telltm ?? ''),
            'nombretut' => (string) ($legajo->nombretut ?? ''),
            'dnitut' => (string) ($legajo->dnitut ?? ''),
            'teletut' => (string) ($legajo->teletut ?? ''),
            'emailtut' => ActualizacionDatosPersonalesComun::normalizarEmailInput($legajo->emailtut ?? ''),
            'ocupactut' => (string) ($legajo->ocupactut ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function datosParaGuardar(array $state): array
    {
        return [
            'nombrepad' => self::trimCampo($state['nombrepad'] ?? ''),
            'dnipad' => self::trimCampo($state['dnipad'] ?? ''),
            'fechnacpad' => self::parseFecha($state['fechnacpad'] ?? '') ?: null,
            'nacionpad' => self::trimCampo($state['nacionpad'] ?? ''),
            'domipad' => self::trimCampo($state['domipad'] ?? ''),
            'telepad' => self::trimCampo($state['telepad'] ?? ''),
            'emailpad' => ActualizacionDatosPersonalesComun::normalizarEmailInput($state['emailpad'] ?? ''),
            'ocupacpad' => self::trimCampo($state['ocupacpad'] ?? ''),
            'telltp' => self::trimCampo($state['telltp'] ?? ''),
            'nombremad' => self::trimCampo($state['nombremad'] ?? ''),
            'dnimad' => self::trimCampo($state['dnimad'] ?? ''),
            'fechnacmad' => self::parseFecha($state['fechnacmad'] ?? '') ?: null,
            'nacionmad' => self::trimCampo($state['nacionmad'] ?? ''),
            'domimad' => self::trimCampo($state['domimad'] ?? ''),
            'telemad' => self::trimCampo($state['telemad'] ?? ''),
            'emailmad' => ActualizacionDatosPersonalesComun::normalizarEmailInput($state['emailmad'] ?? ''),
            'ocupacmad' => self::trimCampo($state['ocupacmad'] ?? ''),
            'telltm' => self::trimCampo($state['telltm'] ?? ''),
            'nombretut' => self::trimCampo($state['nombretut'] ?? ''),
            'dnitut' => self::trimCampo($state['dnitut'] ?? ''),
            'teletut' => self::trimCampo($state['teletut'] ?? ''),
            'emailtut' => ActualizacionDatosPersonalesComun::normalizarEmailInput($state['emailtut'] ?? ''),
            'ocupactut' => self::trimCampo($state['ocupactut'] ?? ''),
            'fechActDatos' => now(),
        ];
    }

    public static function guardar(Legajo $legajo, array $state): void
    {
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
    public static function reglasValidacion(): array
    {
        $req = ['required', 'string', 'max:200'];
        $reqEmail = self::reglaEmailObligatorio();
        $opc = ['nullable', 'string', 'max:200'];

        return [
            'nombrepad' => $req,
            'dnipad' => $req,
            // type="date" no admite guión (-); vacío = no corresponde (parseFecha → null).
            'fechnacpad' => self::reglaFechaOpcional(),
            'nacionpad' => $req,
            'domipad' => $req,
            'telepad' => $req,
            'emailpad' => $reqEmail,
            'ocupacpad' => $req,
            'telltp' => $opc,
            'nombremad' => $req,
            'dnimad' => $req,
            'fechnacmad' => self::reglaFechaOpcional(),
            'nacionmad' => $req,
            'domimad' => $req,
            'telemad' => $req,
            'emailmad' => $reqEmail,
            'ocupacmad' => $req,
            'telltm' => $opc,
            'nombretut' => $req,
            'dnitut' => $req,
            'teletut' => $req,
            'emailtut' => $reqEmail,
            'ocupactut' => $req,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajesValidacion(): array
    {
        return [
            'fechnacpad.date' => 'La fecha de nacimiento del padre no es válida.',
            'fechnacmad.date' => 'La fecha de nacimiento de la madre no es válida.',
            'emailpad.email' => 'El e-mail del padre no es válido.',
            'emailmad.email' => 'El e-mail de la madre no es válido.',
            'emailtut.email' => 'El e-mail del tutor no es válido.',
            '*.required' => 'Este campo es obligatorio. Si no corresponde, escriba un guión (-).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function etiquetasCampos(): array
    {
        return [
            'nombrepad' => 'Padre — Apellidos y nombres',
            'dnipad' => 'Padre — DNI',
            'fechnacpad' => 'Padre — Fecha de nacimiento',
            'nacionpad' => 'Padre — Nacionalidad',
            'domipad' => 'Padre — Domicilio (calle, nº y barrio)',
            'telepad' => 'Padre — Celular',
            'emailpad' => 'Padre — E-mail',
            'ocupacpad' => 'Padre — Ocupación',
            'telltp' => 'Padre — Teléfono laboral',
            'nombremad' => 'Madre — Apellidos y nombres',
            'dnimad' => 'Madre — DNI',
            'fechnacmad' => 'Madre — Fecha de nacimiento',
            'nacionmad' => 'Madre — Nacionalidad',
            'domimad' => 'Madre — Domicilio (calle, nº y barrio)',
            'telemad' => 'Madre — Celular',
            'emailmad' => 'Madre — E-mail',
            'ocupacmad' => 'Madre — Ocupación',
            'telltm' => 'Madre — Teléfono laboral',
            'nombretut' => 'Tutor — Apellidos y nombres',
            'dnitut' => 'Tutor — DNI',
            'teletut' => 'Tutor — Celular',
            'emailtut' => 'Tutor — E-mail',
            'ocupactut' => 'Tutor — Ocupación',
        ];
    }

    /**
     * @return list<array{campo: string, etiqueta: string}>
     */
    public static function camposIncompletosDesdeErrores(MessageBag $errors): array
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
    private static function reglaEmailObligatorio(): array
    {
        return [
            'required',
            'string',
            'max:120',
            static function (string $attribute, mixed $value, \Closure $fail): void {
                if (! ActualizacionDatosPersonalesComun::emailInputAceptado($value, false)) {
                    $fail('Debe ingresar un e-mail válido o un guión (-) si no corresponde.');
                }
            },
        ];
    }

    /**
     * Fecha opcional: vacío o guión = no corresponde (el input type="date" no permite escribir "-").
     *
     * @return list<mixed>
     */
    private static function reglaFechaOpcional(): array
    {
        return [
            'nullable',
            static function (string $attribute, mixed $value, \Closure $fail): void {
                $texto = trim((string) ($value ?? ''));
                if ($texto === '' || $texto === '-') {
                    return;
                }

                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
                    [$y, $m, $d] = array_map('intval', explode('-', $texto));
                    if (! checkdate($m, $d, $y)) {
                        $fail('La fecha de nacimiento no es válida.');
                    }

                    return;
                }

                $dt = Carbon::createFromFormat('d/m/Y', $texto);
                if ($dt === false || $dt->format('d/m/Y') !== $texto) {
                    $fail('La fecha de nacimiento no es válida.');
                }
            },
        ];
    }

    private static function trimCampo(mixed $v): string
    {
        return trim((string) $v);
    }

    private static function fechaInput(mixed $valor): string
    {
        if ($valor instanceof Carbon) {
            return $valor->format('Y-m-d');
        }

        if ($valor === null || $valor === '') {
            return '';
        }

        try {
            return Carbon::parse((string) $valor)->format('Y-m-d');
        } catch (\Throwable) {
            return trim((string) $valor);
        }
    }

    private static function parseFecha(string $texto): ?string
    {
        $texto = trim($texto);
        if ($texto === '' || $texto === '-') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
            return $texto;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $texto)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
