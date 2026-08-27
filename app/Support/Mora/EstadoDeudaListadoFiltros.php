<?php

namespace App\Support\Mora;

/**
 * Filtros del listado de estado de deuda (familia / estudiante) para exportar PDF y Excel.
 */
final class EstadoDeudaListadoFiltros
{
    public function __construct(
        public readonly string $search,
        public readonly int $idNivel,
        public readonly bool $soloConDeuda,
    ) {}

    public static function desdeLivewire(string $search, string $idNivel, bool $soloConDeuda): self
    {
        return new self(
            mb_substr(trim($search), 0, 120),
            EstadoDeudaFamiliarListado::normalizarIdNivel((int) $idNivel),
            $soloConDeuda,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function desdePayload(array $payload): self
    {
        return new self(
            mb_substr(trim((string) ($payload['b'] ?? '')), 0, 120),
            EstadoDeudaFamiliarListado::normalizarIdNivel((int) ($payload['n'] ?? 0)),
            (bool) ($payload['d'] ?? false),
        );
    }

    /**
     * @return array{b: string, n: int, d: bool}
     */
    public function aPayload(): array
    {
        return [
            'b' => $this->search,
            'n' => $this->idNivel,
            'd' => $this->soloConDeuda,
        ];
    }

    public function etiqueta(): string
    {
        $partes = ['Ciclo '.schoolCtx()->terlecAno()];

        if ($this->idNivel > 0) {
            $nivel = EstadoDeudaFamiliarListado::nivelesParaSelector()
                ->firstWhere('id', $this->idNivel);
            $nombre = trim((string) ($nivel?->nivel ?? ''));
            $partes[] = 'Nivel: '.($nombre !== '' ? $nombre : (string) $this->idNivel);
        } else {
            $partes[] = 'Nivel: Todos';
        }

        if ($this->soloConDeuda) {
            $partes[] = 'Solo alumnos con deuda';
        }

        if ($this->search !== '') {
            $partes[] = 'Búsqueda: '.$this->search;
        }

        return implode(' · ', $partes);
    }
}
