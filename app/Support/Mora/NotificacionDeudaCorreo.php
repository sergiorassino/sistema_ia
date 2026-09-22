<?php

namespace App\Support\Mora;

use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Mail\NotificacionDeudaMail;
use App\Models\CuotaGenerada;
use App\Models\Nivel;
use App\Support\Mail\MailDesarrollo;
use App\Support\Mail\MailInstitucionalConfig;
use App\Support\NivelSistema;
use App\Support\OrdenAlfabeticoEstudiante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Destinatarios y envío por mail de la notificación de deuda (responsable administrativo).
 */
final class NotificacionDeudaCorreo
{
    /**
     * @param  Collection<int, CuotaGenerada>  $items
     * @return array{
     *     clave: string,
     *     tipo: string,
     *     idFamilia: int,
     *     idLegajo: int,
     *     idNivel: int,
     *     apellido: string,
     *     nombre: string,
     *     email: string
     * }
     */
    public static function contactoDesdeGrupo(Collection $items, string $clave, int $idNivelFiltro = 0): array
    {
        $idFamilia = 0;
        $idLegajo = 0;
        if (str_starts_with($clave, 'f:')) {
            $idFamilia = (int) substr($clave, 2);
        } elseif (str_starts_with($clave, 'l:')) {
            $idLegajo = (int) substr($clave, 2);
        }

        $primero = $items->first();
        $familia = $primero?->legajo?->familia;
        $legajo = $primero?->legajo;

        $tipo = $idFamilia > 0 && $idFamilia !== LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR
            ? 'familia'
            : 'estudiante';

        if ($tipo === 'familia') {
            $apellido = trim((string) ($familia?->apellido ?? ''));
            $nombre = trim((string) ($familia?->responsable ?? ''));
            $email = trim((string) ($familia?->email ?? ''));
        } else {
            $apellido = trim((string) ($legajo?->apellido ?? ''));
            $nombre = trim((string) ($legajo?->nombre ?? ''));
            $email = '';
            if ($idLegajo < 1) {
                $idLegajo = (int) ($primero?->idLegajos ?? 0);
            }
        }

        return [
            'clave' => $clave,
            'tipo' => $tipo,
            'idFamilia' => $idFamilia,
            'idLegajo' => $idLegajo,
            'idNivel' => self::idNivelDeGrupo($items, $idNivelFiltro),
            'apellido' => $apellido,
            'nombre' => $nombre,
            'email' => $email,
        ];
    }

    public static function emailValido(?string $email): bool
    {
        $e = trim((string) $email);

        return $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param  array<string, mixed>  $filtros  Normalizados
     * @return array{
     *     destinatarios: list<array<string, mixed>>,
     *     total: int,
     *     listos: int,
     *     incompletos: int,
     *     cuentasSmtp: list<array{idNivel: int, nivel: string, cuenta: string}>,
     *     avisosSmtp: list<string>
     * }
     */
    public static function listarDestinatarios(array $filtros): array
    {
        $idNivelFiltro = (int) ($filtros['idNivel'] ?? 0);

        $registros = GestionMorososConsulta::cuotasAdeudadas($filtros)
            ->with([
                'legajo:id,apellido,nombre,idFamilias',
                'legajo.familia:id,apellido,responsable,email',
                'curso:Id,idNivel',
            ])
            ->get();

        $porGrupo = GestionMorososAgrupacion::porFamiliaOEstudiante($registros)
            ->sortBy(fn (Collection $items) => GestionMorososAgrupacion::claveOrden($items->first()));

        $crudos = [];
        foreach ($porGrupo as $clave => $items) {
            $clave = (string) $clave;
            if (! GestionMorososAgrupacion::claveEsValida($clave)) {
                continue;
            }
            $crudos[] = self::contactoDesdeGrupo($items, $clave, $idNivelFiltro);
        }

        return self::enriquecerDestinatarios($crudos);
    }

    /**
     * @param  array<string, mixed>  $filtros  Normalizados
     * @return array{ok: bool, mensaje: string, enviados: int, omitidos: int, errores: int}
     */
    public static function enviar(array $filtros, int $idProfesor): array
    {
        $key = 'mora-notificacion-deuda-mail:'.$idProfesor;
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return [
                'ok' => false,
                'mensaje' => 'Demasiados envíos. Espere un momento e intente nuevamente.',
                'enviados' => 0,
                'omitidos' => 0,
                'errores' => 0,
            ];
        }
        RateLimiter::hit($key, 120);

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        @ini_set('memory_limit', '768M');

        $datos = NotificacionDeudaDatos::build($filtros);
        if ($datos === null) {
            return [
                'ok' => false,
                'mensaje' => 'No hay registros.',
                'enviados' => 0,
                'omitidos' => 0,
                'errores' => 0,
            ];
        }

        /** @var list<array<string, mixed>> $paginas */
        $paginas = $datos['paginas'] ?? [];
        if ($paginas === []) {
            return [
                'ok' => false,
                'mensaje' => 'No hay registros.',
                'enviados' => 0,
                'omitidos' => 0,
                'errores' => 0,
            ];
        }

        $enviados = 0;
        $omitidos = 0;
        $errores = 0;
        $detalleErrores = [];

        $cfg = self::aplicarSmtpAdministracion();
        if ($cfg === null) {
            return [
                'ok' => false,
                'mensaje' => 'No hay cuenta de envío en Administración (ento.ctaEnvioMail). Cargala en Parametrización → Correo institucional Gmail, con el nivel Administración.',
                'enviados' => 0,
                'omitidos' => 0,
                'errores' => 0,
            ];
        }

        $fromAddress = trim((string) ($cfg['username'] ?? ''));
        $fromName = trim((string) ($cfg['from_name'] ?? ''));
        if ($fromAddress === '' || ! self::emailValido($fromAddress)) {
            return [
                'ok' => false,
                'mensaje' => 'La cuenta de Administración no es un email válido para remitente.',
                'enviados' => 0,
                'omitidos' => 0,
                'errores' => 0,
            ];
        }

        $header = (array) ($datos['pdfHeader'] ?? []);

        foreach ($paginas as $pagina) {
            $pagina = (array) $pagina;
            $email = trim((string) ($pagina['email'] ?? ''));
            $apellido = trim((string) ($pagina['apellido'] ?? ''));
            $nombre = trim((string) ($pagina['nombre'] ?? ''));
            $etiqueta = trim($apellido.($apellido !== '' && $nombre !== '' ? ' - ' : '').$nombre);
            if ($etiqueta === '') {
                $etiqueta = trim((string) ($pagina['familiaLinea'] ?? 'Destinatario'));
            }

            if (! self::emailValido($email)) {
                $omitidos++;

                continue;
            }

            try {
                Mail::to($email, $etiqueta !== '' ? $etiqueta : $email)->send(new NotificacionDeudaMail(
                    nombreColegio: trim((string) ($header['insti'] ?? '')),
                    localidad: (string) ($datos['localidad'] ?? ''),
                    fechaCarta: (string) ($datos['fechaCarta'] ?? ''),
                    familiaLinea: (string) ($pagina['familiaLinea'] ?? ''),
                    tituloFamilia: (string) ($pagina['tituloFamilia'] ?? ''),
                    textoInicial: (string) ($datos['textoInicial'] ?? ''),
                    textoFinal: self::textoFinalPagina($datos, $pagina),
                    filas: array_values(array_map(
                        static fn ($fila) => is_array($fila) ? $fila : [],
                        (array) ($pagina['filas'] ?? []),
                    )),
                    totales: is_array($pagina['totales'] ?? null) ? $pagina['totales'] : [],
                    fromAddress: $fromAddress,
                    fromName: $fromName !== '' ? $fromName : $fromAddress,
                ));
                $enviados++;
            } catch (Throwable $e) {
                $errores++;
                $detalleErrores[] = $etiqueta.': '.mb_substr($e->getMessage(), 0, 180);
            }
        }

        if ($enviados === 0 && $errores === 0) {
            $extraLocal = MailDesarrollo::bloquearSmtp()
                ? ' En este entorno local el correo no sale por SMTP (queda en el log).'
                : '';

            return [
                'ok' => false,
                'mensaje' => 'No hay destinatarios con email válido.'.($extraLocal !== '' ? $extraLocal : ''),
                'enviados' => 0,
                'omitidos' => $omitidos,
                'errores' => 0,
            ];
        }

        $mensaje = "Correos enviados: {$enviados}. Omitidos (sin email válido): {$omitidos}. Fallos: {$errores}.";
        if (MailDesarrollo::bloquearSmtp()) {
            $mensaje .= ' Entorno local: el envío quedó registrado en el log (no salió por SMTP).';
        }
        if ($detalleErrores !== []) {
            $mensaje .= ' Detalle: '.implode(' · ', array_slice($detalleErrores, 0, 8));
        }

        return [
            'ok' => $errores === 0,
            'mensaje' => $mensaje,
            'enviados' => $enviados,
            'omitidos' => $omitidos,
            'errores' => $errores,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $crudos
     * @return array{
     *     destinatarios: list<array<string, mixed>>,
     *     total: int,
     *     listos: int,
     *     incompletos: int,
     *     cuentasSmtp: list<array{idNivel: int, nivel: string, cuenta: string}>,
     *     avisosSmtp: list<string>
     * }
     */
    private static function enriquecerDestinatarios(array $crudos): array
    {
        $idsNivel = [];
        foreach ($crudos as $row) {
            $idNivel = (int) ($row['idNivel'] ?? 0);
            if ($idNivel > 0) {
                $idsNivel[$idNivel] = true;
            }
        }

        $idAdmin = NivelSistema::ADMINISTRACION;
        $idsNivel[$idAdmin] = true;

        $nombresNivel = Nivel::query()
            ->whereIn('id', array_keys($idsNivel))
            ->get(['id', 'nivel'])
            ->keyBy('id');

        $cfgAdmin = MailInstitucionalConfig::leer($idAdmin);
        $smtpOk = MailInstitucionalConfig::estaConfigurado($idAdmin);
        $cuentaAdmin = trim((string) ($cfgAdmin['username'] ?? ''));
        $nombreAdmin = trim((string) ($nombresNivel->get($idAdmin)?->nivel ?? ''));
        if ($nombreAdmin === '') {
            $nombreAdmin = 'Administración';
        }

        $destinatarios = [];
        foreach ($crudos as $row) {
            $idNivel = (int) ($row['idNivel'] ?? 0);
            $apellido = trim((string) ($row['apellido'] ?? ''));
            $nombre = trim((string) ($row['nombre'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $emailOk = self::emailValido($email);
            $nivelNombre = trim((string) ($nombresNivel->get($idNivel)?->nivel ?? ''));
            if ($nivelNombre === '' && $idNivel > 0) {
                $nivelNombre = 'Nivel '.$idNivel;
            }

            $faltantes = [];
            if ($apellido === '') {
                $faltantes[] = 'Apellido';
            }
            if ($nombre === '') {
                $faltantes[] = 'Nombre';
            }
            if (! $emailOk) {
                $faltantes[] = $email === '' ? 'Email' : 'Email inválido';
            }

            $avisoSmtp = $smtpOk ? '' : 'Sin cuenta institucional de Administración (ento.ctaEnvioMail)';

            $puedeEnviar = $emailOk && $smtpOk;
            $incompleto = $faltantes !== [] || $avisoSmtp !== '';

            $destinatarios[] = [
                'clave' => (string) ($row['clave'] ?? ''),
                'tipo' => (string) ($row['tipo'] ?? 'familia'),
                'idFamilia' => (int) ($row['idFamilia'] ?? 0),
                'idNivel' => $idNivel,
                'nivel' => $nivelNombre,
                'apellido' => $apellido,
                'nombre' => $nombre,
                'email' => $emailOk ? $email : ($email !== '' ? $email : ''),
                'faltantes' => $faltantes,
                'avisoSmtp' => $avisoSmtp,
                'puedeEnviar' => $puedeEnviar,
                'incompleto' => $incompleto,
                'sinFamilia' => ((string) ($row['tipo'] ?? '')) === 'estudiante',
            ];
        }

        $ordenados = OrdenAlfabeticoEstudiante::ordenarFilas(collect($destinatarios))
            ->values()
            ->all();

        $listos = 0;
        $incompletos = 0;
        $avisosSmtp = [];
        foreach ($ordenados as $d) {
            if ($d['puedeEnviar']) {
                $listos++;
            }
            if ($d['incompleto']) {
                $incompletos++;
            }
            $aviso = trim((string) $d['avisoSmtp']);
            if ($aviso !== '') {
                $avisosSmtp[$aviso] = $aviso;
            }
        }

        $cuentasSmtp = [];
        if ($smtpOk) {
            $cuentasSmtp[] = [
                'idNivel' => $idAdmin,
                'nivel' => $nombreAdmin,
                'cuenta' => $cuentaAdmin,
            ];
        }

        return [
            'destinatarios' => $ordenados,
            'total' => count($ordenados),
            'listos' => $listos,
            'incompletos' => $incompletos,
            'cuentasSmtp' => $cuentasSmtp,
            'avisosSmtp' => array_values($avisosSmtp),
        ];
    }

    /**
     * @param  Collection<int, CuotaGenerada>  $items
     */
    private static function idNivelDeGrupo(Collection $items, int $idNivelFiltro): int
    {
        if ($idNivelFiltro > 0 && ! NivelSistema::esAdministracion($idNivelFiltro)) {
            return $idNivelFiltro;
        }

        foreach ($items as $registro) {
            $id = (int) ($registro->curso?->idNivel ?? 0);
            if ($id > 0 && ! NivelSistema::esAdministracion($id)) {
                return $id;
            }
        }

        $ctx = 0;
        try {
            $ctx = (int) (schoolCtx()->idNivel ?? 0);
        } catch (Throwable) {
            $ctx = 0;
        }

        return ($ctx > 0 && ! NivelSistema::esAdministracion($ctx)) ? $ctx : 0;
    }

    /**
     * @return array{username: string, password: string, from_name: string, fuente: string}|null
     */
    private static function aplicarSmtpAdministracion(): ?array
    {
        $idAdmin = NivelSistema::ADMINISTRACION;
        if (! MailInstitucionalConfig::estaConfigurado($idAdmin)) {
            return null;
        }

        $cfg = MailInstitucionalConfig::leer($idAdmin);
        MailInstitucionalConfig::aplicar($cfg, $idAdmin);
        try {
            Mail::purge('smtp');
            Mail::purge('log');
        } catch (Throwable) {
        }

        return $cfg;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $pagina
     */
    private static function textoFinalPagina(array $datos, array $pagina): string
    {
        $usarBec = (bool) ($pagina['usarTextoFinalBec'] ?? false);

        return $usarBec
            ? trim((string) ($datos['textoFinalBec'] ?? ''))
            : trim((string) ($datos['textoFinal'] ?? ''));
    }
}
