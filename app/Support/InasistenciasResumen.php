<?php

namespace App\Support;

use App\Models\Inasistencia;
use Illuminate\Support\Collection;

/**
 * Totales de inasistencias por matrícula (suma de {@see Inasistencia::$cantidad}).
 *
 * Criterio alineado al boletín/consulta estándar (`itemsboletin`, p. ej. caixalsf):
 * - Justificadas / injustificadas: solo tipo {@see self::TIPO_CLASE}.
 * - Llegadas tarde 1/4 y 1/2: tipos {@see self::TIPO_LLEGADA_TARDE_CUARTO} y {@see self::TIPO_LLEGADA_TARDE_MEDIO}.
 * - Retiro anticipado: tipo {@see self::TIPO_RETIRO}.
 * - Total diario: tipos {@see self::TIPOS_TOTAL_DIARIO}.
 * - Educación física: tipo {@see self::TIPO_EDUCACION_FISICA} (aparte; no entra en just/injust ni en el total diario).
 */
final class InasistenciasResumen
{
    public const TIPO_CLASE = 2;

    public const TIPO_LLEGADA_TARDE_CUARTO = 3;

    public const TIPO_LLEGADA_TARDE_MEDIO = 4;

    public const TIPO_EDUCACION_FISICA = 5;

    public const TIPO_RETIRO = 6;

    /**
     * Tipos que suman al total de inasistencias diarias (sin educación física).
     *
     * @var list<int>
     */
    public const TIPOS_TOTAL_DIARIO = [2, 3, 4, 6];

    public function __construct(
        public readonly float $justificadas,
        public readonly float $injustificadas,
        public readonly float $educacionFisica,
        public readonly int $educacionFisicaRegistros = 0,
        public readonly float $llegadasTardeCuarto = 0.0,
        public readonly float $llegadasTardeMedio = 0.0,
        public readonly float $retirosAnticipados = 0.0,
        public readonly float $totalDiarias = 0.0,
    ) {}

    /** Suma de inasistencias diarias (tipos 2, 3, 4 y 6; sin educación física). */
    public function totalClase(): float
    {
        return round($this->totalDiarias, 2);
    }

    /** @param Collection<int, Inasistencia> $inasistencias */
    public static function desdeColeccion(Collection $inasistencias): self
    {
        $tipoClase = (string) self::TIPO_CLASE;
        $tipoTardeCuarto = (string) self::TIPO_LLEGADA_TARDE_CUARTO;
        $tipoTardeMedio = (string) self::TIPO_LLEGADA_TARDE_MEDIO;
        $tipoEdFisica = (string) self::TIPO_EDUCACION_FISICA;
        $tipoRetiro = (string) self::TIPO_RETIRO;
        $tiposTotal = array_map('strval', self::TIPOS_TOTAL_DIARIO);

        $justificadas = 0.0;
        $injustificadas = 0.0;
        $llegadasTardeCuarto = 0.0;
        $llegadasTardeMedio = 0.0;
        $retirosAnticipados = 0.0;
        $educacionFisica = 0.0;
        $educacionFisicaRegistros = 0;
        $totalDiarias = 0.0;

        foreach ($inasistencias as $i) {
            $cant = (float) ($i->cantidad ?? 0);
            $tipo = trim((string) ($i->tipo ?? ''));
            $tipoNorm = $tipo !== '' ? (string) (int) $tipo : '';

            if ($tipoNorm === $tipoEdFisica) {
                $educacionFisica += $cant;
                $educacionFisicaRegistros++;

                continue;
            }

            if ($tipoNorm !== '' && in_array($tipoNorm, $tiposTotal, true)) {
                $totalDiarias += $cant;
            }

            if ($tipoNorm === $tipoClase) {
                if (strtoupper(trim((string) ($i->just ?? ''))) === 'J') {
                    $justificadas += $cant;
                } else {
                    $injustificadas += $cant;
                }

                continue;
            }

            if ($tipoNorm === $tipoTardeCuarto) {
                $llegadasTardeCuarto += $cant;

                continue;
            }

            if ($tipoNorm === $tipoTardeMedio) {
                $llegadasTardeMedio += $cant;

                continue;
            }

            if ($tipoNorm === $tipoRetiro) {
                $retirosAnticipados += $cant;
            }
        }

        return new self(
            justificadas: round($justificadas, 2),
            injustificadas: round($injustificadas, 2),
            educacionFisica: round($educacionFisica, 2),
            educacionFisicaRegistros: $educacionFisicaRegistros,
            llegadasTardeCuarto: round($llegadasTardeCuarto, 2),
            llegadasTardeMedio: round($llegadasTardeMedio, 2),
            retirosAnticipados: round($retirosAnticipados, 2),
            totalDiarias: round($totalDiarias, 2),
        );
    }

    public function formatear(float $valor): string
    {
        return number_format($valor, 2, ',', '');
    }
}
