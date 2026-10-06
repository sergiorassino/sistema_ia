<?php

namespace App\Support\PortalDocente;

use App\Comunicaciones\CanalesPolicy;
use App\Comunicaciones\ComunicacionesRepository;
use App\Models\Matricula;
use App\Models\Profesor;
use App\Models\Sancion;
use App\Models\SituacionAulicaDestinatario;
use App\Support\PreceptoresPorCurso;
use Illuminate\Support\Facades\Schema;

/**
 * Segundo aviso de situación áulica: profesores cargados en Parametrización.
 * No reemplaza ni altera el aviso al preceptor del curso.
 */
final class NotificarDestinatariosSituacionAulica
{
    public const OMITIDO = 'omitido';

    public const ENVIADO = 'enviado';

    public const FALLIDO = 'fallido';

    public static function despachar(Sancion $sancion, Matricula $matricula): string
    {
        if (! Schema::hasTable(SituacionAulicaDestinatario::TABLA)) {
            return self::OMITIDO;
        }

        $ctx = schoolCtx();
        $idProfesor = (int) ($ctx->idProfesor ?? 0);
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);
        $idCurso = (int) ($matricula->idCursos ?? 0);

        if ($idProfesor < 1 || $idNivel < 1 || $idTerlec < 1 || $idCurso < 1) {
            return self::FALLIDO;
        }

        $ids = SituacionAulicaDestinatario::idsDelNivel($idNivel);
        $ids = array_values(array_diff($ids, [$idProfesor]));
        $ids = array_values(array_diff(
            $ids,
            PreceptoresPorCurso::idsPreceptores($idCurso, $idNivel, $idTerlec)
        ));
        if ($ids === []) {
            return self::OMITIDO;
        }

        $profesor = Profesor::query()->find($idProfesor);
        if ($profesor === null) {
            return self::FALLIDO;
        }

        $rolEmisor = CanalesPolicy::claveRolDeProfesor($profesor);
        $destinos = Profesor::query()->with('tipo')->whereIn('id', $ids)->get();
        if ($destinos->isEmpty()) {
            return self::FALLIDO;
        }

        $texto = NotificarPreceptorSituacionAulica::asuntoYContenido($sancion, $matricula, $profesor);

        $porRol = [];
        foreach ($destinos as $dest) {
            $clave = CanalesPolicy::claveRolDeProfesor($dest);
            $porRol[$clave][] = (int) $dest->id;
        }

        $enviado = false;
        foreach ($porRol as $clave => $idsRol) {
            if (! CanalesPolicy::puedeIniciar($rolEmisor, $clave, $idNivel)) {
                continue;
            }

            try {
                ComunicacionesRepository::crearHiloConMensaje([
                    'asunto' => $texto['asunto'],
                    'contenido' => $texto['contenido'],
                    'scope' => 'docentes',
                    'id_legajos' => [],
                    'id_curso' => $idCurso,
                    'cursos_envio' => null,
                    'id_nivel' => $idNivel,
                    'id_terlec' => $idTerlec,
                    'creado_por_tipo' => 'profesor',
                    'creado_por_id' => $idProfesor,
                    'creado_por_rol' => $rolEmisor,
                    'rol_receptor' => $clave,
                    'vinculo_familiar' => null,
                    'nombre_remitente' => $profesor->nombre_completo,
                    'dni_remitente' => (string) ($profesor->dni ?? ''),
                    'destinatarios_profesores' => $idsRol,
                    'familia_puede_responder' => false,
                    'docentes_permite_respuestas' => true,
                ], CanalesPolicy::mediosPermitidos($rolEmisor, $clave, $idNivel));
                $enviado = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $enviado ? self::ENVIADO : self::FALLIDO;
    }
}
