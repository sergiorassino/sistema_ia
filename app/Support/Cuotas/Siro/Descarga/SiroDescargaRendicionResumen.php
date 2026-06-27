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
     * @param  list<array{linea: int, canal: string, idFacturaBuscado: string, modalidadIdentificacion: string, estado: string, detalle: ?string}>  $registrosArchivo
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
        public array $registrosArchivo = [],
    ) {}

    /**
     * @param  array{linea: int, canal: string, idFacturaBuscado: string, modalidadIdentificacion: string, estado: string, detalle: ?string}  $registro
     */
    public function agregarRegistroArchivo(array $registro): void
    {
        $this->registrosArchivo[] = $registro;
    }

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

    public function debeMostrarModal(string $contexto = ''): bool
    {
        if ($contexto === 'descarga' && $this->registrosArchivo !== []) {
            return true;
        }

        return $this->errores !== []
            || $this->advertencias !== []
            || $this->omitidos > 0
            || $this->noImpactados > 0;
    }

    /**
     * @return list<string>
     */
    public function lineasEncabezado(): array
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

        return $lineas;
    }

    /**
     * @return list<string>
     */
    public function lineasProblemas(): array
    {
        return array_values(array_merge($this->errores, $this->advertencias));
    }

    /**
     * @return array{
     *     titulo: string,
     *     contexto: string,
     *     encabezado: list<string>,
     *     problemas: list<string>,
     *     registrosArchivo: list<array{linea: int, canal: string, idFacturaBuscado: string, estado: string, detalle: ?string}>
     * }
     */
    public function paraModal(string $titulo, string $contexto): array
    {
        return [
            'titulo' => $titulo,
            'contexto' => $contexto,
            'encabezado' => $this->lineasEncabezado(),
            'problemas' => $this->lineasProblemas(),
            'registrosArchivo' => $this->registrosArchivo,
        ];
    }

    public function mensajeExitoBreve(): string
    {
        $lineas = $this->lineasEncabezado();

        return $lineas !== [] ? implode(' ', $lineas) : 'Operación finalizada correctamente.';
    }

    public function mensajeSwal(): string
    {
        $lineas = $this->lineasEncabezado();

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
