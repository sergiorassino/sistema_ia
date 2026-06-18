<?php

namespace App\Support\Comunicaciones;

use App\Comunicaciones\CanalesPolicy;
use App\Comunicaciones\ComunicacionesRepository;
use App\Models\Profesor;
use App\Support\ComunicacionesRutasGestion;

/**
 * Enlace desde otros módulos hacia «Nuevo comunicado» con docente destinatario prefijado.
 */
final class NuevoComunicadoDocenteDestino
{
    /**
     * @return array{destinatario_tipo:string,docente:array{id:int,label:string}}|null
     */
    public static function datosDestinatarioProfesor(int $idProfesor): ?array
    {
        if ($idProfesor <= 0 || ! ComunicacionesRutasGestion::accesoNuevoComunicado()) {
            return null;
        }

        $ctx = schoolCtx();
        $idNivel      = (int) ($ctx->idNivel ?? 0);
        $idProfSesion = (int) ($ctx->idProfesor ?? 0);

        if ($idNivel <= 0 || $idProfesor === $idProfSesion) {
            return null;
        }

        $profesor = Profesor::query()
            ->where('id', $idProfesor)
            ->where('nivel', $idNivel)
            ->first();

        if ($profesor === null) {
            return null;
        }

        $idTipoProf = (int) ($profesor->IdTipoProf ?? 0);
        if ($idTipoProf <= 0) {
            return null;
        }

        $destinatarioTipo = 'tipo:'.$idTipoProf;

        $profEmisor = $ctx->profesor();
        if ($profEmisor === null) {
            return null;
        }

        $rolEmisor = CanalesPolicy::claveRolDeProfesor($profEmisor);
        if (! CanalesPolicy::puedeIniciar($rolEmisor, $destinatarioTipo, $idNivel)) {
            return null;
        }

        $idsValidos = ComunicacionesRepository::filtrarIdsProfesoresPorIdTipoProf(
            [$idProfesor],
            $idNivel,
            $idTipoProf
        );

        if ($idsValidos === []) {
            return null;
        }

        return [
            'destinatario_tipo' => $destinatarioTipo,
            'docente'           => [
                'id'    => $idProfesor,
                'label' => $profesor->nombre_completo,
            ],
        ];
    }

    public static function urlParaProfesor(int $idProfesor): ?string
    {
        if (self::datosDestinatarioProfesor($idProfesor) === null) {
            return null;
        }

        return ComunicacionesRutasGestion::route('nuevo', ['destinatario' => $idProfesor]);
    }
}
