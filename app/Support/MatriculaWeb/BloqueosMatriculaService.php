<?php

namespace App\Support\MatriculaWeb;

use App\Models\Matricula;
use App\Support\Database\PersistenciaColumnas;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Database\QueryException;

final class BloqueosMatriculaService
{
    /**
     * @return array{exito: bool, mensaje: string, valor: bool|null}
     */
    public static function alternar(int $idMatricula, string $campo): array
    {
        if (! in_array($campo, ['bloqmatr', 'bloqadmi'], true)) {
            return [
                'exito' => false,
                'mensaje' => 'Campo de bloqueo inválido.',
                'valor' => null,
            ];
        }

        $registro = BloqueosMatriculaConsulta::matriculaEnAlcance($idMatricula);
        if ($registro === null) {
            return [
                'exito' => false,
                'mensaje' => 'No se encontró la matrícula o no pertenece al nivel y ciclo activos.',
                'valor' => null,
            ];
        }

        $valorActual = (bool) ($registro->{$campo} ?? false);
        $valorNuevo = ! $valorActual;

        $actualizados = Matricula::query()
            ->whereKey($idMatricula)
            ->update([$campo => $valorNuevo ? 1 : 0]);

        if ($actualizados < 1) {
            return [
                'exito' => false,
                'mensaje' => 'No se pudo actualizar el bloqueo.',
                'valor' => null,
            ];
        }

        return [
            'exito' => true,
            'mensaje' => 'Bloqueo actualizado.',
            'valor' => $valorNuevo,
        ];
    }

    /**
     * Aplica bloqueo o desbloqueo a todos los alumnos del listado (filtro de curso actual).
     *
     * @return array{exito: bool, mensaje: string, afectados: int}
     */
    public static function aplicarMasivo(int $idCurso, string $campo, bool $bloquear): array
    {
        if (! in_array($campo, ['bloqmatr', 'bloqadmi'], true)) {
            return [
                'exito' => false,
                'mensaje' => 'Campo de bloqueo inválido.',
                'afectados' => 0,
            ];
        }

        $valor = $bloquear ? 1 : 0;
        $preparado = PersistenciaColumnas::prepararPayload('matricula', [$campo => $valor]);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            return [
                'exito' => false,
                'mensaje' => PersistenciaColumnas::mensajeColumnasInexistentes(
                    'matricula',
                    $preparado['columnas_con_valor_sin_columna']
                ),
                'afectados' => 0,
            ];
        }

        $ids = BloqueosMatriculaConsulta::idsDelListado($idCurso);
        if ($ids->isEmpty()) {
            return [
                'exito' => false,
                'mensaje' => 'No hay alumnos regulares en el listado actual.',
                'afectados' => 0,
            ];
        }

        $idTerlec = (int) schoolCtx()->idTerlec;

        try {
            foreach ($ids->chunk(200) as $chunk) {
                $query = Matricula::query()
                    ->whereIn('id', $chunk->all())
                    ->where('idTerlec', $idTerlec);
                SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNivel');
                $query->update($preparado['payload']);
            }
        } catch (QueryException $e) {
            return [
                'exito' => false,
                'mensaje' => PersistenciaColumnas::mensajeDesdeQueryException($e)
                    ?? 'No se pudo actualizar el bloqueo masivo.',
                'afectados' => 0,
            ];
        }

        $muestra = (int) $ids->first();
        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            'matricula',
            ['id' => $muestra],
            $preparado['payload']
        );
        if ($noPersistidas !== []) {
            return [
                'exito' => false,
                'mensaje' => PersistenciaColumnas::mensajeColumnasNoPersistidas('matricula', $noPersistidas),
                'afectados' => 0,
            ];
        }

        $n = $ids->count();
        $etiqueta = $campo === 'bloqmatr' ? 'pedagógico' : 'administrativo';
        $accion = $bloquear ? 'bloqueo' : 'desbloqueo';
        $alumnos = $n === 1 ? 'alumno regular' : 'alumnos regulares';

        return [
            'exito' => true,
            'mensaje' => "Se aplicó el {$accion} {$etiqueta} a {$n} {$alumnos}.",
            'afectados' => $n,
        ];
    }
}
