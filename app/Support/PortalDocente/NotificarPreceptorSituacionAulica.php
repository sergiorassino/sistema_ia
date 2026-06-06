<?php

namespace App\Support\PortalDocente;

use App\Comunicaciones\CanalesPolicy;
use App\Comunicaciones\ComunicacionesRepository;
use App\Models\Matricula;
use App\Models\Profesor;
use App\Models\Sancion;
use App\Support\PreceptoresPorCurso;

/**
 * Aviso al preceptor del curso tras registrar situación áulica (mismos medios que un comunicado docente→preceptor).
 */
final class NotificarPreceptorSituacionAulica
{
    /**
     * @return bool true si se creó al menos un hilo de comunicación
     */
    public static function despachar(Sancion $sancion, Matricula $matricula): bool
    {
        $ctx = schoolCtx();
        $idProfesor = (int) ($ctx->idProfesor ?? 0);
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);
        $idCurso = (int) ($matricula->idCursos ?? 0);

        if ($idProfesor < 1 || $idNivel < 1 || $idTerlec < 1 || $idCurso < 1) {
            return false;
        }

        $idsPreceptor = PreceptoresPorCurso::idsPreceptores($idCurso, $idNivel, $idTerlec);
        $idsPreceptor = array_values(array_diff($idsPreceptor, [$idProfesor]));
        if ($idsPreceptor === []) {
            return false;
        }

        $profesor = Profesor::query()->find($idProfesor);
        if ($profesor === null) {
            return false;
        }

        $rolEmisor = CanalesPolicy::claveRolDeProfesor($profesor);

        $preceptorRef = Profesor::query()->find($idsPreceptor[0]);
        if ($preceptorRef === null) {
            return false;
        }
        $claveReceptor = CanalesPolicy::claveRolDeProfesor($preceptorRef);
        if (! CanalesPolicy::puedeIniciar($rolEmisor, $claveReceptor)) {
            return false;
        }

        $sancion->loadMissing(['tipo']);
        $matricula->loadMissing(['legajo', 'curso']);

        $alumno = trim(($matricula->legajo?->apellido ?? '').', '.($matricula->legajo?->nombre ?? ''));
        $curso = $matricula->curso?->nombreParaListado() ?? ('Curso '.$idCurso);
        $tipo = $sancion->tipo?->tipo ?? SituacionAulicaTipo::label();
        $fecha = $sancion->fecha ? $sancion->fecha->format('d/m/Y') : '—';
        $motivo = trim((string) ($sancion->motivo ?? ''));

        $asunto = 'Situación áulica — '.$alumno;
        $lineas = [
            'Se registró una nueva situación áulica en el Cuaderno de Seguimiento.',
            '',
            'Alumno/a: '.$alumno,
            'Curso: '.$curso,
            'Tipo: '.$tipo,
            'Fecha: '.$fecha,
            'Motivo: '.($motivo !== '' ? $motivo : '—'),
        ];
        $lineas[] = '';
        $lineas[] = 'Registrado por: '.$profesor->nombre_completo;

        $mediosCanal = CanalesPolicy::mediosPermitidos($rolEmisor, $claveReceptor);

        ComunicacionesRepository::crearHiloConMensaje([
            'asunto' => $asunto,
            'contenido' => implode("\n", $lineas),
            'scope' => 'docentes',
            'id_legajos' => [],
            'id_curso' => $idCurso,
            'cursos_envio' => null,
            'id_nivel' => $idNivel,
            'id_terlec' => $idTerlec,
            'creado_por_tipo' => 'profesor',
            'creado_por_id' => $idProfesor,
            'creado_por_rol' => $rolEmisor,
            'rol_receptor' => $claveReceptor,
            'vinculo_familiar' => null,
            'nombre_remitente' => $profesor->nombre_completo,
            'dni_remitente' => (string) ($profesor->dni ?? ''),
            'destinatarios_profesores' => $idsPreceptor,
            'familia_puede_responder' => false,
            'docentes_permite_respuestas' => true,
        ], $mediosCanal);

        return true;
    }
}
