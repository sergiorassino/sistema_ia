<?php

namespace App\Support\Listados;

/**
 * Filtros del listado de familias para exportar PDF y Excel.
 */
final class ListadoFamiliasFiltros
{
    public function __construct(
        public readonly string $search,
        public readonly int $idNivel,
    ) {}

    public static function desdeLivewire(string $search, string $idNivel): self
    {
        return new self(
            mb_substr(trim($search), 0, 120),
            ListadoFamiliasConsulta::idNivelEfectivo((int) $idNivel),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function desdePayload(array $payload): self
    {
        return new self(
            mb_substr(trim((string) ($payload['b'] ?? '')), 0, 120),
            ListadoFamiliasConsulta::idNivelEfectivo((int) ($payload['n'] ?? 0)),
        );
    }

    /**
     * @return array{b: string, n: int}
     */
    public function aPayload(): array
    {
        return [
            'b' => $this->search,
            'n' => $this->idNivel,
        ];
    }

    public function etiqueta(): string
    {
        $partes = ['Ciclo '.schoolCtx()->terlecAno()];

        if ($this->idNivel > 0) {
            $nivel = ListadoFamiliasConsulta::nivelesParaSelector()
                ->firstWhere('id', $this->idNivel);
            $nombre = trim((string) ($nivel?->nivel ?? ''));
            $partes[] = 'Nivel: '.($nombre !== '' ? $nombre : (string) $this->idNivel);
        } else {
            $partes[] = 'Nivel: Todos los niveles';
        }

        if ($this->search !== '') {
            $partes[] = 'Búsqueda: '.$this->search;
        }

        return implode(' · ', $partes);
    }
}
