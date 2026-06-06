<?php

namespace App\Support;

use App\Models\Inasistencia;
use App\Models\InasistenciaValor;
use Illuminate\Support\Collection;

/**
 * Totales de inasistencias por matrícula (suma de {@see Inasistencia::$cantidad}).
 *
 * - Educación física: tipos cuyo concepto en {@see InasistenciaValor} coincide con ed. física.
 * - Justificadas: `just = 'J'` en el resto de los tipos.
 * - Injustificadas: cualquier otro registro que no sea ed. física ni justificado.
 */
final class InasistenciasResumen
{
    public function __construct(
        public readonly float $justificadas,
        public readonly float $injustificadas,
        public readonly float $educacionFisica,
        public readonly int $educacionFisicaRegistros = 0,
    ) {}

    /** Suma de cantidades de inasistencias de clase (sin educación física). */
    public function totalClase(): float
    {
        return round($this->justificadas + $this->injustificadas, 2);
    }

    /** @param Collection<int, Inasistencia> $inasistencias */
    public static function desdeColeccion(Collection $inasistencias): self
    {
        $idsEdFisica = InasistenciaValor::idsEducacionFisica();

        $justificadas = 0.0;
        $injustificadas = 0.0;
        $educacionFisica = 0.0;
        $educacionFisicaRegistros = 0;

        foreach ($inasistencias as $i) {
            $cant = (float) ($i->cantidad ?? 0);
            $tipo = trim((string) ($i->tipo ?? ''));
            $tipoNorm = $tipo !== '' ? (string) (int) $tipo : '';

            if ($tipoNorm !== '' && $idsEdFisica->contains($tipoNorm)) {
                $educacionFisica += $cant;
                $educacionFisicaRegistros++;

                continue;
            }

            if (strtoupper(trim((string) ($i->just ?? ''))) === 'J') {
                $justificadas += $cant;
            } else {
                $injustificadas += $cant;
            }
        }

        return new self(
            justificadas: round($justificadas, 2),
            injustificadas: round($injustificadas, 2),
            educacionFisica: round($educacionFisica, 2),
            educacionFisicaRegistros: $educacionFisicaRegistros,
        );
    }

    public function formatear(float $valor): string
    {
        return number_format($valor, 2, ',', '');
    }
}
