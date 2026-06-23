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
     * @return array{
     *     destinatario_tipo:string,
     *     id_nivel_destinatario:int,
     *     docente:array{id:int,label:string}
     * }|null
     */
    public static function datosDestinatarioProfesor(int $idProfesor): ?array
    {
        if ($idProfesor <= 0 || ! ComunicacionesRutasGestion::accesoNuevoComunicado()) {
            return null;
        }

        $ctx = schoolCtx();
        $idProfSesion = (int) ($ctx->idProfesor ?? 0);

        if ($idProfesor === $idProfSesion) {
            return null;
        }

        $profesor = Profesor::query()
            ->where('id', $idProfesor)
            ->first();

        if ($profesor === null) {
            return null;
        }

        $idNivelDestinatario = (int) ($profesor->nivel ?? 0);
        if ($idNivelDestinatario <= 0) {
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
        if (! CanalesPolicy::puedeIniciar($rolEmisor, $destinatarioTipo, $idNivelDestinatario)) {
            return null;
        }

        $idsValidos = ComunicacionesRepository::filtrarIdsProfesoresPorIdTipoProf(
            [$idProfesor],
            $idNivelDestinatario,
            $idTipoProf
        );

        if ($idsValidos === []) {
            return null;
        }

        return [
            'destinatario_tipo'      => $destinatarioTipo,
            'id_nivel_destinatario'  => $idNivelDestinatario,
            'docente'                => [
                'id'    => $idProfesor,
                'label' => $profesor->nombre_completo,
            ],
        ];
    }

    public static function urlParaProfesor(int $idProfesor): ?string
    {
        $datos = self::datosDestinatarioProfesor($idProfesor);
        if ($datos === null) {
            return null;
        }

        return ComunicacionesRutasGestion::route('nuevo', [
            'destinatario'       => $idProfesor,
            'nivel_destinatario' => $datos['id_nivel_destinatario'],
        ]);
    }
}
