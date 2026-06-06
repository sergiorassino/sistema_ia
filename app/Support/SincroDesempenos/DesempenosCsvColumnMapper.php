<?php

namespace App\Support\SincroDesempenos;

/**
 * Detecta columnas del CSV de desempeños (encabezado flexible) y repara filas
 * cuando el texto de desempeño contiene «;» y rompe el parseo.
 */
final class DesempenosCsvColumnMapper
{
    /** @var array<string, int|null> */
    private array $map;

    private int $expectedCount;

    /**
     * @param  list<string|null>  $headerRow
     */
    public function __construct(array $headerRow)
    {
        $header = [];
        foreach ($headerRow as $cell) {
            $header[] = $this->ensureUtf8((string) ($cell ?? ''));
        }

        $this->expectedCount = count($header);
        $headerTrim = array_map(
            fn (string $h) => mb_strtolower(trim($h)),
            $header
        );

        $this->map = [
            'grado' => null,
            'division' => null,
            'turno' => null,
            'dni' => null,
            'apellido' => null,
            'nombre' => null,
            'desemp' => null,
            'just' => null,
            'inju' => null,
        ];

        foreach ($headerTrim as $i => $h) {
            if ($h === '') {
                continue;
            }
            if (str_contains($h, 'grado') || str_contains($h, 'año') || str_contains($h, 'ano')) {
                $this->map['grado'] = $i;
            } elseif (str_contains($h, 'divis')) {
                $this->map['division'] = $i;
            } elseif (str_contains($h, 'turno')) {
                $this->map['turno'] = $i;
            } elseif (str_contains($h, 'nro') || str_contains($h, 'docum') || str_contains($h, 'document')) {
                $this->map['dni'] = $i;
            } elseif (str_contains($h, 'apelli')) {
                $this->map['apellido'] = $i;
            } elseif (str_contains($h, 'nombre') && $this->map['nombre'] === null) {
                $this->map['nombre'] = $i;
            } elseif (str_contains($h, 'desem')) {
                $this->map['desemp'] = $i;
            } elseif (str_contains($h, 'justificad') && $this->map['just'] === null) {
                $this->map['just'] = $i;
            } elseif (str_contains($h, 'injust') || (str_contains($h, 'inasist') && str_contains($h, 'injust'))) {
                $this->map['inju'] = $i;
            } elseif (str_contains($h, 'inasist') && $this->map['just'] === null) {
                $this->map['just'] = $i;
            }
        }

        if ($this->map['apellido'] === null && isset($headerTrim[4])) {
            $this->map['apellido'] = 4;
        }
        if ($this->map['nombre'] === null && isset($headerTrim[5])) {
            $this->map['nombre'] = 5;
        }
        if ($this->map['dni'] === null && isset($headerTrim[3])) {
            $this->map['dni'] = 3;
        }
        if ($this->map['desemp'] === null) {
            foreach ($headerTrim as $i => $h) {
                if ($h !== '' && (str_contains($h, 'desemp') || str_contains($h, 'coment') || str_contains($h, 'observ'))) {
                    $this->map['desemp'] = $i;
                    break;
                }
            }
        }

        if ($this->map['desemp'] === null) {
            $this->map['desemp'] = 6;
        }
        if ($this->map['apellido'] === null) {
            $this->map['apellido'] = 4;
        }
        if ($this->map['nombre'] === null) {
            $this->map['nombre'] = 5;
        }
        if ($this->map['dni'] === null) {
            $this->map['dni'] = 3;
        }
        if ($this->map['turno'] === null) {
            $this->map['turno'] = 2;
        }
        if ($this->map['division'] === null) {
            $this->map['division'] = 1;
        }
        if ($this->map['grado'] === null) {
            $this->map['grado'] = 0;
        }
        if ($this->map['just'] === null) {
            $this->map['just'] = 7;
        }
        if ($this->map['inju'] === null) {
            $this->map['inju'] = 8;
        }
    }

    public function expectedColumnCount(): int
    {
        return $this->expectedCount;
    }

    /** @return array<string, int> */
    public function indices(): array
    {
        return array_map(fn ($v) => (int) $v, $this->map);
    }

    public function indiceDesempeno(): int
    {
        return (int) $this->map['desemp'];
    }

    public function indiceDni(): int
    {
        return (int) $this->map['dni'];
    }

    /**
     * Encabezado mínimo válido: al menos grado + DNI + desempeño detectables.
     */
    public function esEncabezadoValido(): bool
    {
        return $this->expectedCount >= 4
            && $this->map['dni'] !== null
            && $this->map['desemp'] !== null
            && $this->map['grado'] !== null;
    }

    /**
     * @param  list<string|null>  $cols
     * @return list<string>
     */
    public function normalizarFila(array $cols): array
    {
        if (count($cols) !== $this->expectedCount) {
            $cols = $this->repararFilaPorDesemp($cols);
        }

        $out = [];
        foreach ($cols as $valor) {
            $out[] = $this->ensureUtf8((string) ($valor ?? ''));
        }

        return $out;
    }

    /**
     * @param  list<string>  $row
     * @return array{grado: string, division: string, turno: string, dni: string, apellido: string, nombre: string, desemp: string, just: string, inju: string}
     */
    public function extraerCampos(array $row): array
    {
        $idx = $this->indices();

        return [
            'grado' => (string) ($row[$idx['grado']] ?? ''),
            'division' => (string) ($row[$idx['division']] ?? ''),
            'turno' => (string) ($row[$idx['turno']] ?? ''),
            'dni' => (string) ($row[$idx['dni']] ?? ''),
            'apellido' => (string) ($row[$idx['apellido']] ?? ''),
            'nombre' => (string) ($row[$idx['nombre']] ?? ''),
            'desemp' => (string) ($row[$idx['desemp']] ?? ''),
            'just' => (string) ($row[$idx['just']] ?? ''),
            'inju' => (string) ($row[$idx['inju']] ?? ''),
        ];
    }

    /**
     * @param  list<string|null>  $cols
     * @return list<string>
     */
    private function repararFilaPorDesemp(array $cols): array
    {
        $total = count($cols);
        $expected = $this->expectedCount;
        $desempIdx = $this->indiceDesempeno();

        if ($total === $expected) {
            return array_map(fn ($v) => (string) ($v ?? ''), $cols);
        }

        if ($total < $expected) {
            return array_map(fn ($v) => (string) ($v ?? ''), $cols);
        }

        $finalCount = $expected - ($desempIdx + 1);
        if ($finalCount < 0) {
            $finalCount = 0;
        }

        $startFinales = $total - $finalCount;
        $partesDesemp = array_slice($cols, $desempIdx, $startFinales - $desempIdx);
        $desempReconstruido = trim(implode(';', array_map(fn ($v) => (string) ($v ?? ''), $partesDesemp)));

        $antes = array_slice($cols, 0, $desempIdx);
        $finales = array_slice($cols, $startFinales);

        $nuevo = array_merge($antes, [$desempReconstruido], $finales);
        $nuevo = array_map(fn ($v) => (string) ($v ?? ''), $nuevo);

        while (count($nuevo) < $expected) {
            $nuevo[] = '';
        }

        if (count($nuevo) > $expected) {
            $nuevo = array_slice($nuevo, 0, $expected);
        }

        return $nuevo;
    }

    private function ensureUtf8(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return $s;
        }

        if (mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }

        return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }
}
