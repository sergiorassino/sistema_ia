<?php

namespace App\Support\Seguimiento;

use App\Comunicaciones\CanalesPolicy;
use App\Comunicaciones\ComunicacionesRepository;
use App\Models\Matricula;
use App\Models\Profesor;
use App\Models\Sancion;
use App\Support\Comunicaciones\ComCanalRolCatalog;

/**
 * Envía un comunicado institucional a la familia del alumno
 * al registrar una sanción disciplinaria (botón «Notif. Padres»).
 *
 * El remitente es el profesor configurado en sanciontipo.idProfesorNotif.
 * Los medios incluyen siempre push (si el canal lo permite) y también email
 * cuando sanciontipo.refuerzoMail está activo y el canal lo permite.
 */
final class NotificarFamiliaSancion
{
    /**
     * @return bool true si se creó el hilo de comunicación correctamente.
     */
    public static function despachar(Sancion $sancion, Matricula $matricula): bool
    {
        $ctx = schoolCtx();
        $idNivel  = (int) ($ctx->idNivel  ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        if ($idNivel < 1 || $idTerlec < 1) {
            return false;
        }

        // La sanción debe pertenecer al contexto actual
        if ((int) ($matricula->idNivel ?? 0)  !== $idNivel
            || (int) ($matricula->idTerlec ?? 0) !== $idTerlec) {
            return false;
        }

        $sancion->loadMissing(['tipo.profesorNotif', 'profesor']);
        $tipo = $sancion->tipo;

        if (! $tipo) {
            return false;
        }

        // Verificar que este tipo permite notificar a padres
        if (isset($tipo->permiteNotifPadres) && ! $tipo->permiteNotifPadres) {
            return false;
        }

        // Remitente fijo configurado en el tipo
        $idProfesorNotif = (int) ($tipo->idProfesorNotif ?? 0);
        if ($idProfesorNotif < 1) {
            return false;
        }

        $profesorNotif = Profesor::query()->find($idProfesorNotif);
        if ($profesorNotif === null) {
            return false;
        }

        $rolEmisor = CanalesPolicy::claveRolDeProfesor($profesorNotif);

        if (! CanalesPolicy::puedeIniciar($rolEmisor, ComCanalRolCatalog::CLAVE_FAMILIA, $idNivel)) {
            return false;
        }

        $mediosCanal = CanalesPolicy::mediosPermitidos($rolEmisor, ComCanalRolCatalog::CLAVE_FAMILIA, $idNivel);
        if ($mediosCanal === []) {
            return false;
        }

        // Filtrar medios: push siempre incluido si el canal lo permite;
        // email solo si refuerzoMail está activo para este tipo.
        $mediosEfectivos = array_values(array_filter($mediosCanal, function (string $medio) use ($tipo): bool {
            if ($medio === 'push') {
                return true;
            }
            if ($medio === 'email') {
                return (bool) ($tipo->refuerzoMail ?? false);
            }
            // whatsapp y otros: no incluir en notificaciones automáticas disciplinarias
            return false;
        }));

        if ($mediosEfectivos === []) {
            return false;
        }

        $idLegajo = (int) ($matricula->idLegajos ?? 0);
        if ($idLegajo < 1) {
            return false;
        }

        $matricula->loadMissing(['legajo', 'curso']);

        $alumno  = trim(($matricula->legajo?->apellido ?? '').', '.($matricula->legajo?->nombre ?? ''));
        $curso   = $matricula->curso?->nombreParaListado() ?? '';
        $tipoNombre = trim((string) ($tipo->tipo ?? ''));
        $fecha   = $sancion->fecha ? $sancion->fecha->format('d/m/Y') : '—';
        $cantidad = isset($sancion->cantidad) && $sancion->cantidad !== null ? (string) $sancion->cantidad : '—';
        $motivo  = trim((string) ($sancion->motivo ?? ''));
        $solipor = trim((string) ($sancion->solipor ?? ''));
        if ($solipor === '') {
            $solipor = $sancion->profesor?->nombre_completo ?? '—';
        }

        // Prefijo del mensaje: texto configurado en el tipo o texto default
        $prefijo = trim((string) ($tipo->textoNotifPadres ?? ''));
        if ($prefijo === '') {
            $prefijo = 'Le informamos que se ha registrado la siguiente sanción disciplinaria en el Cuaderno de Seguimiento.';
        }

        $lineas = [$prefijo, ''];
        if ($alumno !== ', ') {
            $lineas[] = 'Alumno/a: '.$alumno;
        }
        if ($curso !== '') {
            $lineas[] = 'Curso: '.$curso;
        }
        $lineas[] = 'Fecha: '.$fecha;
        $lineas[] = 'Tipo de sanción: '.$tipoNombre;
        $lineas[] = 'Cantidad: '.$cantidad;
        if ($motivo !== '') {
            $lineas[] = 'Motivo: '.$motivo;
        }
        $lineas[] = 'Solicitada por: '.$solipor;

        $asunto = 'Sanción disciplinaria — '.$alumno;

        ComunicacionesRepository::crearHiloConMensaje([
            'asunto'                   => $asunto,
            'contenido'                => implode("\n", $lineas),
            'scope'                    => 'alumno',
            'id_legajos'               => [$idLegajo],
            'id_curso'                 => (int) ($matricula->idCursos ?? 0) ?: null,
            'cursos_envio'             => null,
            'id_nivel'                 => $idNivel,
            'id_terlec'                => $idTerlec,
            'creado_por_tipo'          => 'profesor',
            'creado_por_id'            => $idProfesorNotif,
            'creado_por_rol'           => $rolEmisor,
            'rol_receptor'             => ComCanalRolCatalog::CLAVE_FAMILIA,
            'vinculo_familiar'         => null,
            'nombre_remitente'         => $profesorNotif->nombre_completo,
            'dni_remitente'            => (string) ($profesorNotif->dni ?? ''),
            'destinatarios_profesores' => [],
            'familia_puede_responder'  => false,
        ], $mediosEfectivos);

        return true;
    }
}
