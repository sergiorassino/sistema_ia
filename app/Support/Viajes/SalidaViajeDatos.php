<?php

namespace App\Support\Viajes;

use App\Models\Curso;
use App\Models\Matricula;
use App\Models\SalidaViaje;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\Listados\EstudiantesDatosConsulta;
use Illuminate\Support\Collection;

/**
 * Datos para autorizaciones de salidas educativas (PDF).
 */
final class SalidaViajeDatos
{
    /**
     * @param  list<int>  $idsMatricula
     * @return list<array<string, mixed>>
     */
    public static function alumnosParaPdf(SalidaViaje $viaje, array $idsMatricula, int $cursoId): array
    {
        if (! $viaje->perteneceAlContexto()) {
            return [];
        }

        $ids = BoletinSecundarioLoteParams::resolverIdsMatriculasDesdeLista($idsMatricula, $cursoId);
        if ($ids === []) {
            return [];
        }

        $ctx = schoolCtx();

        /** @var Collection<int, Matricula> $matriculas */
        $matriculas = Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idCursos', $cursoId)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (Matricula $m) => (int) $m->id);

        $filas = [];

        foreach ($ids as $idMatricula) {
            $matricula = $matriculas->get((int) $idMatricula);
            if ($matricula === null) {
                continue;
            }

            $legajo = $matricula->legajo;
            if ($legajo === null) {
                continue;
            }

            $filas[] = [
                'apellido' => trim((string) ($legajo->apellido ?? '')),
                'nombre' => trim((string) ($legajo->nombre ?? '')),
                'dni' => trim((string) ($legajo->dni ?? '')),
                'gruposang' => EstudiantesDatosConsulta::valorGrupoSanguineo($legajo),
                'cursec' => trim((string) ($matricula->curso?->cursec ?? '')),
                'callenum' => trim((string) ($legajo->callenum ?? '')),
                'localidad' => trim((string) ($legajo->localidad ?? '')),
            ];
        }

        return $filas;
    }

    public static function viajeParaPdf(int $idViaje): ?SalidaViaje
    {
        if ($idViaje <= 0) {
            return null;
        }

        $viaje = SalidaViaje::queryEnContexto()->find($idViaje);
        if ($viaje === null) {
            return null;
        }

        return $viaje;
    }

    /**
     * @return Collection<int, Curso>
     */
    public static function cursosEnContexto(): Collection
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'c', 's', 'idTurnoClase', 'idCurPlan']);
    }

    public static function rutaMembrete(string $archivo): ?string
    {
        foreach ([
            public_path('img/viajes/'.$archivo),
            public_path('img/certificados/'.$archivo),
            public_path('img/'.$archivo),
        ] as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }
}
