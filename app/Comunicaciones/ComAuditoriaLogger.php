<?php

namespace App\Comunicaciones;

use App\Models\ComAuditoria;
use App\Models\ComHilo;
use App\Models\ComMensaje;
use App\Models\ComMensajeDestinatario;
use App\Models\Legajo;
use App\Models\Profesor;
use App\Support\ComunicacionesRutasGestion;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de auditoría para acciones en bandejas de comunicación institucional.
 */
final class ComAuditoriaLogger
{
    /**
     * @param  list<int>  $idsMensajes  Mensajes que pasaron a leído en esta acción
     */
    public static function registrarMarcarLeidoHilo(
        int $idHilo,
        int $idNivel,
        int $idTerlec,
        array $idsMensajes,
        ?int $idProfesor = null,
        ?int $idLegajo = null
    ): void {
        if ($idsMensajes === []) {
            return;
        }

        $mensajes = self::cargarMensajesHilo($idHilo, $idsMensajes);
        if ($mensajes->isEmpty()) {
            return;
        }

        foreach ($mensajes as $mensaje) {
            if ($idProfesor !== null) {
                self::registrarDesdeProfesor(
                    ComAuditoria::ACCION_MARCAR_LEIDO,
                    $idHilo,
                    $mensaje,
                    $idProfesor,
                    $idNivel,
                    $idTerlec,
                    destinatarioActor: self::destinatarioActorProfesor($mensaje, $idProfesor)
                );

                continue;
            }

            if ($idLegajo !== null) {
                self::registrarDesdeFamilia(
                    ComAuditoria::ACCION_MARCAR_LEIDO,
                    $idHilo,
                    $mensaje,
                    $idLegajo,
                    $idNivel,
                    $idTerlec,
                    destinatarioActor: self::destinatarioActorFamilia($mensaje, $idLegajo)
                );
            }
        }
    }

    public static function registrarMarcarNoLeido(
        ComMensaje $mensaje,
        int $idHilo,
        int $idNivel,
        int $idTerlec,
        ?int $idProfesor = null,
        ?int $idLegajo = null
    ): void {
        if ($idProfesor !== null) {
            self::registrarDesdeProfesor(
                ComAuditoria::ACCION_MARCAR_NO_LEIDO,
                $idHilo,
                $mensaje,
                $idProfesor,
                $idNivel,
                $idTerlec,
                destinatarioActor: self::destinatarioActorProfesor($mensaje, $idProfesor)
            );

            return;
        }

        if ($idLegajo !== null) {
            self::registrarDesdeFamilia(
                ComAuditoria::ACCION_MARCAR_NO_LEIDO,
                $idHilo,
                $mensaje,
                $idLegajo,
                $idNivel,
                $idTerlec,
                destinatarioActor: self::destinatarioActorFamilia($mensaje, $idLegajo)
            );
        }
    }

    public static function registrarBorrado(
        ComHilo $hilo,
        ComMensaje $mensaje,
        bool $eliminaHiloCompleto,
        ?int $idProfesor = null,
        ?int $idLegajo = null
    ): void {
        $accion = $eliminaHiloCompleto
            ? ComAuditoria::ACCION_BORRAR_HILO
            : ComAuditoria::ACCION_BORRAR_MENSAJE;

        $idNivel  = (int) $hilo->id_nivel;
        $idTerlec = (int) $hilo->id_terlec;

        if ($idProfesor !== null) {
            self::registrarDesdeProfesor($accion, (int) $hilo->id, $mensaje, $idProfesor, $idNivel, $idTerlec, $hilo);

            return;
        }

        if ($idLegajo !== null) {
            self::registrarDesdeFamilia($accion, (int) $hilo->id, $mensaje, $idLegajo, $idNivel, $idTerlec, $hilo);
        }
    }

    private static function registrarDesdeProfesor(
        string $accion,
        int $idHilo,
        ComMensaje $mensaje,
        int $idProfesor,
        int $idNivel,
        int $idTerlec,
        ?ComHilo $hilo = null,
        ?ComMensajeDestinatario $destinatarioActor = null
    ): void {
        $prof = Profesor::query()->find($idProfesor);
        if ($prof === null) {
            return;
        }

        $hilo ??= ComHilo::query()->find($idHilo);
        if ($hilo === null) {
            return;
        }

        $categoria = (int) ($prof->IdTipoProf ?? 0) === ProfesorMenuPortal::ID_TIPO_PROFESOR_AULA
            ? 'profesor'
            : 'personal';

        self::insertar(self::filaMensaje([
            'accion'                 => $accion,
            'portal'                 => ComunicacionesRutasGestion::esPortalDocente() ? 'docente' : 'secretaria',
            'tipo_actor'             => 'profesor',
            'actor_categoria'        => $categoria,
            'id_profesor_actor'      => $idProfesor,
            'id_legajo_actor'        => null,
            'nombre_actor_snapshot'  => trim((string) $prof->apellido . ', ' . (string) $prof->nombre),
            'dni_actor_snapshot'     => $prof->dni !== null ? (string) $prof->dni : null,
            'id_hilo'                => (int) $hilo->id,
            'hilo_asunto_snapshot'   => mb_substr((string) $hilo->asunto, 0, 200),
            'id_nivel'               => $idNivel,
            'id_terlec'              => $idTerlec,
        ], $mensaje, $destinatarioActor));
    }

    private static function registrarDesdeFamilia(
        string $accion,
        int $idHilo,
        ComMensaje $mensaje,
        int $idLegajo,
        int $idNivel,
        int $idTerlec,
        ?ComHilo $hilo = null,
        ?ComMensajeDestinatario $destinatarioActor = null
    ): void {
        $legajo = Legajo::query()->find($idLegajo);
        if ($legajo === null) {
            return;
        }

        $hilo ??= ComHilo::query()->find($idHilo);
        if ($hilo === null) {
            return;
        }

        self::insertar(self::filaMensaje([
            'accion'                 => $accion,
            'portal'                 => 'familia',
            'tipo_actor'             => 'familia',
            'actor_categoria'        => 'estudiante',
            'id_profesor_actor'      => null,
            'id_legajo_actor'        => $idLegajo,
            'nombre_actor_snapshot'  => trim((string) $legajo->apellido . ', ' . (string) $legajo->nombre),
            'dni_actor_snapshot'     => $legajo->dni !== null ? (string) $legajo->dni : null,
            'id_hilo'                => (int) $hilo->id,
            'hilo_asunto_snapshot'   => mb_substr((string) $hilo->asunto, 0, 200),
            'id_nivel'               => $idNivel,
            'id_terlec'              => $idTerlec,
        ], $mensaje, $destinatarioActor));
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private static function filaMensaje(
        array $base,
        ComMensaje $mensaje,
        ?ComMensajeDestinatario $destinatarioActor = null
    ): array {
        $base['id_mensaje']                  = (int) $mensaje->id;
        $base['mensaje_contenido_snapshot']  = self::recortarContenido((string) $mensaje->contenido);
        $base['mensaje_fecha_snapshot']      = $mensaje->fecha;
        $base['mensaje_remitente_snapshot']  = self::snapshotRemitente($mensaje);
        $base['mensaje_destinatario_snapshot'] = self::snapshotDestinatarios(
            $mensaje,
            $destinatarioActor
        );

        return $base;
    }

    /**
     * @param  list<int>  $idsMensajes
     * @return Collection<int, ComMensaje>
     */
    private static function cargarMensajesHilo(int $idHilo, array $idsMensajes): Collection
    {
        return ComMensaje::query()
            ->where('id_hilo', $idHilo)
            ->whereIn('id', $idsMensajes)
            ->orderBy('id')
            ->get();
    }

    private static function destinatarioActorProfesor(ComMensaje $mensaje, int $idProfesor): ?ComMensajeDestinatario
    {
        return ComMensajeDestinatario::query()
            ->where('id_mensaje', $mensaje->id)
            ->where('tipo_destinatario', 'profesor')
            ->where('id_profesor', $idProfesor)
            ->first();
    }

    private static function destinatarioActorFamilia(ComMensaje $mensaje, int $idLegajo): ?ComMensajeDestinatario
    {
        return ComMensajeDestinatario::query()
            ->where('id_mensaje', $mensaje->id)
            ->where('tipo_destinatario', 'familia')
            ->where('id_legajo', $idLegajo)
            ->first();
    }

    private static function snapshotRemitente(ComMensaje $mensaje): string
    {
        $nombre = trim((string) $mensaje->nombre_remitente_snapshot);
        if ($nombre === '') {
            $nombre = $mensaje->tipo_remitente === 'familia' ? 'Familia' : 'Personal escolar';
        }

        $rol = trim((string) ($mensaje->rol_remitente ?? ''));
        if ($rol !== '') {
            $nombre .= ' (' . $rol . ')';
        }

        $dni = trim((string) ($mensaje->dni_remitente_snapshot ?? ''));
        if ($dni !== '') {
            $nombre .= ' · DNI ' . $dni;
        }

        return mb_substr($nombre, 0, 200);
    }

    private static function snapshotDestinatarios(
        ComMensaje $mensaje,
        ?ComMensajeDestinatario $destinatarioActor = null
    ): string {
        if ($destinatarioActor !== null) {
            return self::formatDestinatario($destinatarioActor);
        }

        $dests = ComMensajeDestinatario::query()
            ->where('id_mensaje', $mensaje->id)
            ->orderBy('tipo_destinatario')
            ->orderBy('id')
            ->get();

        if ($dests->isEmpty()) {
            return '—';
        }

        $partes = $dests
            ->map(fn (ComMensajeDestinatario $d) => self::formatDestinatario($d))
            ->unique()
            ->values()
            ->all();

        return mb_substr(implode('; ', $partes), 0, 4000);
    }

    private static function formatDestinatario(ComMensajeDestinatario $dest): string
    {
        $nombre = trim((string) $dest->nombre_snapshot);
        if ($nombre === '') {
            $nombre = $dest->tipo_destinatario === 'familia' ? 'Familia' : 'Personal';
        }

        $rol = trim((string) ($dest->rol_destinatario ?? ''));
        if ($rol !== '') {
            $nombre .= ' (' . $rol . ')';
        }

        $dni = trim((string) ($dest->dni_snapshot ?? ''));
        if ($dni !== '') {
            $nombre .= ' · DNI ' . $dni;
        }

        return $nombre;
    }

    /** @param array<string, mixed> $datos */
    private static function insertar(array $datos): void
    {
        if (! Schema::hasTable('com_auditoria')) {
            return;
        }

        if (! Schema::hasColumn('com_auditoria', 'mensaje_remitente_snapshot')) {
            unset($datos['mensaje_remitente_snapshot'], $datos['mensaje_destinatario_snapshot']);
        }

        $datos['ip_address'] = request()->ip();
        $datos['created_at'] = now();

        DB::table('com_auditoria')->insert($datos);
    }

    private static function recortarContenido(string $contenido): string
    {
        $texto = trim(strip_tags($contenido));

        return mb_substr($texto, 0, 4000);
    }
}
