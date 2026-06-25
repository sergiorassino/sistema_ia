<?php

namespace App\Support\Cuotas\Siro\Descarga;

/**
 * Resumen de una operación de descarga o impacto SIRO.
 */
final class SiroDescargaRendicionResumen
{
    /**
     * @param  list<string>  $advertencias
     * @param  list<string>  $errores
     */
    public function __construct(
        public int $procesados = 0,
        public int $omitidos = 0,
        public int $impactados = 0,
        public int $noImpactados = 0,
        public float $montoPagado = 0.0,
        public float $montoImpactado = 0.0,
        public array $advertencias = [],
        public array $errores = [],
    ) {}

    public function agregarAdvertencia(string $mensaje): void
    {
        if ($mensaje !== '' && ! in_array($mensaje, $this->advertencias, true)) {
            $this->advertencias[] = $mensaje;
        }
    }

    public function agregarError(string $mensaje): void
    {
        if ($mensaje !== '' && ! in_array($mensaje, $this->errores, true)) {
            $this->errores[] = $mensaje;
        }
    }

    public function mensajeSwal(): string
    {
        $lineas = [];
        if ($this->procesados > 0) {
            $lineas[] = 'Registros procesados: '.$this->procesados.'.';
        }
        if ($this->impactados > 0) {
            $lineas[] = 'Cuotas impactadas: '.$this->impactados.'.';
        }
        if ($this->montoPagado > 0) {
            $lineas[] = 'Monto descargado: $'.number_format($this->montoPagado, 2, ',', '.').'.';
        }
        if ($this->montoImpactado > 0) {
            $lineas[] = 'Monto impactado: $'.number_format($this->montoImpactado, 2, ',', '.').'.';
        }
        if ($this->omitidos > 0) {
            $lineas[] = 'Omitidos: '.$this->omitidos.'.';
        }
        if ($this->noImpactados > 0) {
            $lineas[] = 'Sin impactar: '.$this->noImpactados.'.';
        }

        foreach (array_slice($this->advertencias, 0, 8) as $adv) {
            $lineas[] = '• '.$adv;
        }
        foreach (array_slice($this->errores, 0, 5) as $err) {
            $lineas[] = '• '.$err;
        }

        if (count($this->advertencias) > 8) {
            $lineas[] = '… y '.(count($this->advertencias) - 8).' advertencias más.';
        }

        return $lineas !== [] ? implode("\n", $lineas) : 'Operación finalizada.';
    }
}
