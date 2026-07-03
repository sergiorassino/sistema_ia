<?php

namespace App\Support\MatriculaWeb;

use App\Models\Matricula;

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
}
