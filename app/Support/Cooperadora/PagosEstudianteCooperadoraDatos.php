<?php

namespace App\Support\Cooperadora;

/**
 * Datos para el PDF de pagos del estudiante (cooperadora).
 */
final class PagosEstudianteCooperadoraDatos
{
    /**
     * @return array<string, mixed>|null
     */
    public static function paraLegajo(int $idLegajo): ?array
    {
        $encabezado = PagosEstudianteCooperadoraConsulta::encabezadoEstudiante($idLegajo);
        if ($encabezado === null) {
            return null;
        }

        $filas = PagosEstudianteCooperadoraConsulta::filas($idLegajo);

        return [
            'header' => CooperadoraConfig::datosPdfHeader(),
            'fechaImpresion' => now()->format('d/m/Y H:i'),
            'apellidoNombre' => mb_strtoupper(trim(($encabezado['apellido'] ?? '').' '.($encabezado['nombre'] ?? ''))),
            'dni' => (string) ($encabezado['dni'] ?? ''),
            'curso' => (string) ($encabezado['curso'] ?? ''),
            'terlecAno' => (string) ($encabezado['terlecAno'] ?? ''),
            'filas' => $filas,
            'totales' => PagosEstudianteCooperadoraConsulta::totalesDesdeFilas($filas),
        ];
    }
}
