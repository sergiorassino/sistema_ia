<?php

namespace App\Support\Cuotas\Siro\Descarga;

/**
 * Resumen de una operación de descarga o impacto SIRO.
 */
final class SiroDescargaRendicionResumen
{
    /**
     * @param  list<array{linea: ?int, mensaje: string}>  $advertencias
     * @param  list<array{linea: ?int, mensaje: string}>  $errores
     * @param  list<array{linea: int, canal: string, idFacturaBuscado: string, modalidadIdentificacion: string, estado: string, detalle: ?string}>  $registrosArchivo
     */
    public function __construct(
        public int $procesados = 0,
        public int $omitidos = 0,
        public int $rechazos = 0,
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

    public function agregarAdvertencia(string $mensaje, ?int $linea = null): void
    {
        $this->agregarProblema($this->advertencias, $mensaje, $linea);
    }

    public function agregarError(string $mensaje, ?int $linea = null): void
    {
        $this->agregarProblema($this->errores, $mensaje, $linea);
    }

    public function debeMostrarModal(string $contexto = ''): bool
    {
        if ($contexto === 'descarga' && $this->registrosArchivo !== []) {
            return true;
        }

        return $this->errores !== []
            || $this->advertencias !== []
            || $this->omitidos > 0
            || $this->rechazos > 0
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
        $duplicados = 0;
        foreach ($this->registrosArchivo as $registro) {
            if (($registro['estado'] ?? '') === 'encontrado_duplicado') {
                $duplicados++;
            }
        }
        if ($duplicados > 0) {
            $lineas[] = 'Pagos duplicados (se registran igual): '.$duplicados.'.';
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
        if ($this->rechazos > 0) {
            $lineas[] = 'Rechazos SIRO: '.$this->rechazos.'.';
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
     * @return list<array{linea: ?int, mensaje: string}>
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
     *     problemas: list<array{linea: ?int, mensaje: string}>,
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
            $lineas[] = '• '.$this->textoProblema($adv);
        }
        foreach (array_slice($this->errores, 0, 5) as $err) {
            $lineas[] = '• '.$this->textoProblema($err);
        }

        if (count($this->advertencias) > 8) {
            $lineas[] = '… y '.(count($this->advertencias) - 8).' advertencias más.';
        }

        return $lineas !== [] ? implode("\n", $lineas) : 'Operación finalizada.';
    }

    /**
     * @param  list<array{linea: ?int, mensaje: string}>  $destino
     */
    private function agregarProblema(array &$destino, string $mensaje, ?int $linea): void
    {
        $mensaje = trim($mensaje);
        if ($mensaje === '') {
            return;
        }

        foreach ($destino as $existente) {
            if ($existente['mensaje'] === $mensaje && $existente['linea'] === $linea) {
                return;
            }
        }

        $destino[] = [
            'linea' => $linea,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @param  array{linea: ?int, mensaje: string}  $problema
     */
    private function textoProblema(array $problema): string
    {
        $linea = $problema['linea'] ?? null;
        if ($linea !== null) {
            return 'Registro '.$linea.': '.$problema['mensaje'];
        }

        return $problema['mensaje'];
    }
}
