<?php

namespace App\Support\Auth;

use App\Mail\RecuperacionContrasenaMail;
use App\Models\Legajo;
use App\Models\Matricula;
use App\Models\Profesor;
use App\Support\DniInput;
use App\Support\Mail\MailInstitucionalConfig;
use App\Support\NivelSistema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Recuperación de contraseña por correo (login staff y alumnos).
 * Remitente: cuenta institucional del nivel (`ento.ctaEnvioMail` / `passEnvioMail`,
 * Parámetros → Correo institucional). No usa la casilla compartida SE_CLIENTES_MAIL_*.
 */
final class RecuperacionContrasenaPorCorreo
{
    private const MAX_INTENTOS = 5;

    private const SEGUNDOS_BLOQUEO = 300;

    public static function enviar(
        string $dni,
        RecuperacionContrasenaOrigen $origen,
        string $ip,
        ?int $idNivel = null,
    ): RecuperacionContrasenaResultado {
        $dni = DniInput::digitsOnly($dni);
        if ($dni === '' || strlen($dni) < 7 || strlen($dni) > 11) {
            return RecuperacionContrasenaResultado::dniInvalido();
        }

        if ($origen === RecuperacionContrasenaOrigen::Profesor) {
            if ($idNivel === null || $idNivel <= 0 || ! NivelSistema::nivelPermitidoEnLogin($idNivel)) {
                return RecuperacionContrasenaResultado::nivelInvalido();
            }
        }

        $throttleKey = 'recuperar-contrasena:'.$origen->value.':'.$ip;
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_INTENTOS)) {
            return RecuperacionContrasenaResultado::limiteIntentos(
                RateLimiter::availableIn($throttleKey),
            );
        }

        RateLimiter::hit($throttleKey, self::SEGUNDOS_BLOQUEO);

        $datos = match ($origen) {
            RecuperacionContrasenaOrigen::Profesor => self::datosProfesor($dni, (int) $idNivel),
            RecuperacionContrasenaOrigen::Alumno => self::datosAlumno($dni),
        };

        if ($datos === null) {
            return RecuperacionContrasenaResultado::dniNoEncontrado();
        }

        if ($datos['email'] === '') {
            return RecuperacionContrasenaResultado::sinEmail();
        }

        $contrasena = ContrasenaAlmacenada::textoPlanoRecuperable($datos['pwrd']);
        if ($contrasena === null) {
            return RecuperacionContrasenaResultado::contrasenaNoEnviable();
        }

        $idNivelMail = $origen === RecuperacionContrasenaOrigen::Profesor
            ? (int) $idNivel
            : (int) $datos['idNivel'];

        if ($idNivelMail < 1 || ! MailInstitucionalConfig::aplicarParaNivel($idNivelMail)) {
            Log::warning('Recuperación de contraseña: sin cuenta institucional del nivel', [
                'origen' => $origen->value,
                'idNivel' => $idNivelMail,
            ]);

            return RecuperacionContrasenaResultado::mailNoConfigurado();
        }

        $cuenta = MailInstitucionalConfig::leer($idNivelMail);
        $fromAddress = trim((string) ($cuenta['username'] ?? ''));
        $fromName = trim((string) ($cuenta['from_name'] ?? ''));

        $portalEtiqueta = match ($origen) {
            RecuperacionContrasenaOrigen::Profesor => 'Portal del personal',
            RecuperacionContrasenaOrigen::Alumno => 'Portal de estudiantes',
        };

        $nombreInstitucion = ($fromName !== '' && ! str_contains($fromName, '@'))
            ? $fromName
            : trim((string) config('tenant.nombre', 'Colegio'));
        if ($nombreInstitucion === '') {
            $nombreInstitucion = trim((string) config('app.name', 'Sistemas Escolares'));
        }
        if ($fromName === '') {
            $fromName = $nombreInstitucion;
        }

        try {
            Mail::to($datos['email'])->send(new RecuperacionContrasenaMail(
                nombreDestinatario: $datos['nombre'],
                contrasena: $contrasena,
                portalEtiqueta: $portalEtiqueta,
                nombreInstitucion: $nombreInstitucion,
                fromAddress: $fromAddress,
                fromName: $fromName,
            ));

            Log::info('Recuperación de contraseña enviada', [
                'origen' => $origen->value,
                'dni' => $dni,
                'idNivel' => $idNivelMail,
                'destinatario' => $datos['email'],
                'remitente' => $fromAddress,
            ]);

            return RecuperacionContrasenaResultado::enviado($datos['email']);
        } catch (Throwable $e) {
            Log::error('Error al enviar recuperación de contraseña', [
                'origen' => $origen->value,
                'dni' => $dni,
                'idNivel' => $idNivelMail,
                'destinatario' => $datos['email'],
                'error' => $e->getMessage(),
            ]);

            return RecuperacionContrasenaResultado::errorEnvio(
                'No se pudo enviar el correo en este momento. Intente nuevamente más tarde o contacte a secretaría.',
            );
        }
    }

    /**
     * @return array{nombre: string, email: string, pwrd: string, idNivel: int}|null
     */
    private static function datosProfesor(string $dni, int $idNivel): ?array
    {
        $profesor = Profesor::query()
            ->where('dni', $dni)
            ->where('nivel', $idNivel)
            ->first(['apellido', 'nombre', 'email', 'emailInsti', 'pwrd']);

        if ($profesor === null) {
            return null;
        }

        return [
            'nombre' => trim($profesor->apellido.', '.$profesor->nombre, ' ,'),
            'email' => self::normalizarEmail($profesor->email ?? null, $profesor->emailInsti ?? null),
            'pwrd' => (string) ($profesor->pwrd ?? ''),
            'idNivel' => $idNivel,
        ];
    }

    /**
     * @return array{nombre: string, email: string, pwrd: string, idNivel: int}|null
     */
    private static function datosAlumno(string $dni): ?array
    {
        $columnas = ['id', 'apellido', 'nombre', 'email', 'pwrd'];
        if (Schema::hasColumn('legajos', 'idnivel')) {
            $columnas[] = 'idnivel';
        }

        $legajo = Legajo::query()
            ->where('dni', $dni)
            ->orderBy('id')
            ->first($columnas);

        if ($legajo === null) {
            return null;
        }

        return [
            'nombre' => trim($legajo->apellido.', '.$legajo->nombre, ' ,'),
            'email' => self::normalizarEmail($legajo->email ?? null),
            'pwrd' => (string) ($legajo->pwrd ?? ''),
            'idNivel' => self::idNivelAlumno($legajo),
        ];
    }

    private static function idNivelAlumno(Legajo $legajo): int
    {
        $idNivel = (int) ($legajo->idnivel ?? 0);
        if ($idNivel > 0) {
            return $idNivel;
        }

        return (int) (Matricula::query()
            ->where('idLegajos', (int) $legajo->id)
            ->orderByDesc('idTerlec')
            ->orderByDesc('id')
            ->value('idNivel') ?? 0);
    }

    private static function normalizarEmail(?string ...$candidatos): string
    {
        foreach ($candidatos as $email) {
            $email = mb_strtolower(trim((string) $email), 'UTF-8');
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return '';
    }
}
