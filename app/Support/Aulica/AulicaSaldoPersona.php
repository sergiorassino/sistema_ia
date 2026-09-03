<?php

namespace App\Support\Aulica;

/**
 * Un ítem de POST /alumnos/ctacte/saldos.
 */
final class AulicaSaldoPersona
{
    public function __construct(
        public readonly int $idPersona,
        public readonly float $saldo,
        public readonly string $nroDoc,
        public readonly string $tipoDoc,
        public readonly string $nombre,
        public readonly string $apellido,
    ) {}

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function desdeRespuesta(array $fila): self
    {
        return new self(
            idPersona: (int) ($fila['idPersona'] ?? 0),
            saldo: self::aFloat($fila['saldo'] ?? 0),
            nroDoc: trim((string) ($fila['nroDoc'] ?? '')),
            tipoDoc: trim((string) ($fila['tipoDoc'] ?? 'DNI')),
            nombre: trim((string) ($fila['nombre'] ?? '')),
            apellido: trim((string) ($fila['apellido'] ?? '')),
        );
    }

    public function tieneDeuda(): bool
    {
        return $this->saldo > 0.009;
    }

    public function nombreCompleto(): string
    {
        $apellido = $this->apellido;
        $nombre = $this->nombre;
        if ($apellido !== '' && $nombre !== '') {
            return $apellido.', '.$nombre;
        }

        return trim($apellido.' '.$nombre);
    }

    public function saldoFormateado(): string
    {
        return '$ '.number_format($this->saldo, 2, ',', '.');
    }

    public function etiquetaListado(): string
    {
        $nombre = $this->nombreCompleto();
        $dni = $this->nroDoc !== '' ? ' (DNI '.$this->nroDoc.')' : '';

        return trim(($nombre !== '' ? $nombre : 'Estudiante').$dni).': '.$this->saldoFormateado();
    }

    /**
     * @return array{id_persona: int, tipo_doc: string, nro_doc: string, apellido: string, nombre: string, nombre_completo: string, saldo: float, saldo_texto: string}
     */
    public function aArray(): array
    {
        return [
            'id_persona' => $this->idPersona,
            'tipo_doc' => $this->tipoDoc !== '' ? $this->tipoDoc : 'DNI',
            'nro_doc' => $this->nroDoc,
            'apellido' => $this->apellido,
            'nombre' => $this->nombre,
            'nombre_completo' => $this->nombreCompleto(),
            'saldo' => $this->saldo,
            'saldo_texto' => $this->saldoFormateado(),
        ];
    }

    private static function aFloat(mixed $valor): float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $texto = str_replace([' ', ','], ['', '.'], trim((string) $valor));

        return is_numeric($texto) ? (float) $texto : 0.0;
    }
}
