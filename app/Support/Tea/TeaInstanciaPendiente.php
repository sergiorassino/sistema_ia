<?php

namespace App\Support\Tea;

/**
 * Instancia TEA que corresponde cargar según inasistencias acumuladas y aún no registrada.
 */
final class TeaInstanciaPendiente
{
    public function __construct(
        public readonly int $idTipo,
        public readonly string $etiqueta,
        public readonly int $umbral,
        public readonly string $metrica,
    ) {}

    public function etiquetaResumida(): string
    {
        $tipo = trim($this->etiqueta);
        if ($tipo !== '' && $tipo !== '—') {
            return $tipo;
        }

        return match ($this->metrica) {
            'injustificadas' => "{$this->umbral} inas. injustificadas",
            default => "{$this->umbral} inasistencias",
        };
    }
}
