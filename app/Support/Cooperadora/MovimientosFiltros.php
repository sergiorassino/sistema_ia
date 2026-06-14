<?php

namespace App\Support\Cooperadora;

use App\Models\CoopRubroIngreso;

final class MovimientosFiltros
{
    public function __construct(
        public string $tipoMov = '',
        public int|string $idRubro = '',
        public int|string $idItem = '',
        public int|string $idProveedor = '',
        public string $tipoIngreso = '',
        public int|string $idMedioPago = '',
        public string $busqueda = '',
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desde(array $datos): self
    {
        $tipoMov = is_scalar($datos['tipoMov'] ?? $datos['tipo_mov'] ?? '') ? (string) ($datos['tipoMov'] ?? $datos['tipo_mov'] ?? '') : '';

        return new self(
            tipoMov: in_array($tipoMov, ['ingreso', 'egreso'], true) ? $tipoMov : '',
            idRubro: self::enteroOpcional($datos['idRubro'] ?? $datos['id_rubro'] ?? ''),
            idItem: self::enteroOpcional($datos['idItem'] ?? $datos['id_item'] ?? ''),
            idProveedor: self::enteroOpcional($datos['idProveedor'] ?? $datos['id_proveedor'] ?? ''),
            tipoIngreso: self::tipoIngresoValido($datos['tipoIngreso'] ?? $datos['tipo_ingreso'] ?? ''),
            idMedioPago: self::enteroOpcional($datos['idMedioPago'] ?? $datos['id_medio_pago'] ?? ''),
            busqueda: is_scalar($datos['busqueda'] ?? '') ? trim((string) ($datos['busqueda'] ?? '')) : '',
        );
    }

    public function incluyeIngresos(): bool
    {
        return $this->tipoMov !== 'egreso';
    }

    public function incluyeEgresos(): bool
    {
        return $this->tipoMov !== 'ingreso';
    }

    public function tieneAlguno(): bool
    {
        return $this->tipoMov !== ''
            || (int) $this->idRubro > 0
            || (int) $this->idItem > 0
            || (int) $this->idProveedor > 0
            || $this->tipoIngreso !== ''
            || (int) $this->idMedioPago > 0
            || $this->busqueda !== '';
    }

    /**
     * @return array<string, string|int>
     */
    public function aQuery(): array
    {
        $params = [];

        if ($this->tipoMov !== '') {
            $params['tipo_mov'] = $this->tipoMov;
        }
        if ((int) $this->idRubro > 0) {
            $params['id_rubro'] = (int) $this->idRubro;
        }
        if ((int) $this->idItem > 0) {
            $params['id_item'] = (int) $this->idItem;
        }
        if ((int) $this->idProveedor > 0) {
            $params['id_proveedor'] = (int) $this->idProveedor;
        }
        if ($this->tipoIngreso !== '') {
            $params['tipo_ingreso'] = $this->tipoIngreso;
        }
        if ((int) $this->idMedioPago > 0) {
            $params['id_medio_pago'] = (int) $this->idMedioPago;
        }
        if ($this->busqueda !== '') {
            $params['busqueda'] = $this->busqueda;
        }

        return $params;
    }

    private static function enteroOpcional(mixed $valor): int|string
    {
        if ($valor === '' || $valor === null) {
            return '';
        }

        $entero = (int) $valor;

        return $entero > 0 ? $entero : '';
    }

    private static function tipoIngresoValido(mixed $valor): string
    {
        $tipo = is_scalar($valor) ? (string) $valor : '';

        return in_array($tipo, CoopRubroIngreso::tiposValidos(), true) ? $tipo : '';
    }
}
