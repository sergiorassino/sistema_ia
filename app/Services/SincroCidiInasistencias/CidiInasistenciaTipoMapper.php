<?php

namespace App\Services\SincroCidiInasistencias;

use App\Models\InasistenciaValor;
use Illuminate\Support\Collection;

/**
 * Traduce el texto «Tipo» del CSV CIDI al catálogo {@see InasistenciaValor}
 * mediante la columna {@see InasistenciaValor::$texto_cidi}.
 */
final class CidiInasistenciaTipoMapper
{
    private const FRACCIONES_TEXTO = [
        '1/4' => 0.25,
        '1/2' => 0.5,
        '3/4' => 0.75,
    ];

    /** @var Collection<int, InasistenciaValor> */
    private Collection $valores;

    /** @var array<string, InasistenciaValor> clave = texto CIDI normalizado */
    private array $porTextoCidi = [];

    /** @var array<string, ResolvedCidiInasistenciaTipo|null> */
    private array $cache = [];

    public function __construct()
    {
        $this->valores = InasistenciaValor::query()
            ->orderBy('concepto')
            ->get(['id', 'concepto', 'texto_cidi', 'cantidad']);

        foreach ($this->valores as $valor) {
            $texto = trim((string) ($valor->texto_cidi ?? ''));
            if ($texto === '') {
                continue;
            }

            $key = InasistenciaValor::normalizarTexto($texto);
            if ($key !== '') {
                $this->porTextoCidi[$key] = $valor;
            }
        }
    }

    public function catalogoVacio(): bool
    {
        return $this->valores->isEmpty();
    }

    public function tieneTextosCidiConfigurados(): bool
    {
        return $this->porTextoCidi !== [];
    }

    /**
     * @return ResolvedCidiInasistenciaTipo|null null si es PRESENTE o no se pudo mapear
     */
    public function resolve(string $cidiTipo): ?ResolvedCidiInasistenciaTipo
    {
        $key = InasistenciaValor::normalizarTexto($cidiTipo);
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if ($key === 'presente' || str_starts_with($key, 'presente ')) {
            $this->cache[$key] = null;

            return null;
        }

        $valor = $this->porTextoCidi[$key] ?? null;
        if ($valor === null) {
            $this->cache[$key] = null;

            return null;
        }

        $resolved = $this->resolverDesdeValor($valor, $cidiTipo, $key);
        $this->cache[$key] = $resolved;

        return $resolved;
    }

    public function esPresente(string $cidiTipo): bool
    {
        $key = InasistenciaValor::normalizarTexto($cidiTipo);

        return $key === 'presente' || str_starts_with($key, 'presente ');
    }

    private function resolverDesdeValor(InasistenciaValor $valor, string $cidiTipoOriginal, string $norm): ResolvedCidiInasistenciaTipo
    {
        $idTipo = (int) $valor->id;
        $concepto = (string) ($valor->concepto ?? '');

        $cantidad = $this->cantidadResuelta($cidiTipoOriginal, $valor);

        if ($this->valorSinJustificacion($concepto)) {
            return new ResolvedCidiInasistenciaTipo($idTipo, null, $cantidad);
        }

        if ($this->valorLlegadaTardeORetiro($concepto, $norm)) {
            return new ResolvedCidiInasistenciaTipo($idTipo, 'J', $cantidad);
        }

        if ($this->csvEsAusenteInjustificado($norm)) {
            return new ResolvedCidiInasistenciaTipo($idTipo, 'I', 1.0);
        }

        if ($this->csvEsAusenteJustificado($norm)) {
            return new ResolvedCidiInasistenciaTipo($idTipo, 'J', 1.0);
        }

        return new ResolvedCidiInasistenciaTipo($idTipo, null, $cantidad);
    }

    private function cantidadResuelta(string $cidiTipoOriginal, InasistenciaValor $valor): float
    {
        $fraccion = $this->fraccionDesdeTexto($cidiTipoOriginal);

        return $fraccion ?? ($valor->cantidad !== null ? (float) $valor->cantidad : 1.0);
    }

    /** Ed. física: no graba just (null en BD). */
    private function valorSinJustificacion(string $concepto): bool
    {
        return InasistenciaValor::conceptoEsEducacionFisica($concepto);
    }

    /** Llegada tarde y retiro anticipado: justificadas (J). */
    private function valorLlegadaTardeORetiro(string $concepto, string $normCidi): bool
    {
        if (InasistenciaValor::conceptoEsLlegadaTarde($concepto)
            || InasistenciaValor::conceptoEsRetiro($concepto)) {
            return true;
        }

        return str_contains($normCidi, 'llegada')
            || str_contains($normCidi, 'tarde')
            || str_contains($normCidi, 'tardanza')
            || str_contains($normCidi, 'retiro');
    }

    private function csvEsAusenteInjustificado(string $norm): bool
    {
        return str_contains($norm, 'ausent') && str_contains($norm, 'injustificad');
    }

    private function csvEsAusenteJustificado(string $norm): bool
    {
        return str_contains($norm, 'ausent')
            && str_contains($norm, 'justificad')
            && ! str_contains($norm, 'injustificad');
    }

    private function fraccionDesdeTexto(string $texto): ?float
    {
        $norm = InasistenciaValor::normalizarTexto($texto);
        foreach (self::FRACCIONES_TEXTO as $literal => $valor) {
            if (str_contains($norm, InasistenciaValor::normalizarTexto($literal))) {
                return $valor;
            }
        }

        if (preg_match('/\b1\s*\/\s*4\b/u', $texto)) {
            return 0.25;
        }
        if (preg_match('/\b1\s*\/\s*2\b/u', $texto)) {
            return 0.5;
        }
        if (preg_match('/\b3\s*\/\s*4\b/u', $texto)) {
            return 0.75;
        }

        return null;
    }
}

/**
 * Tipo CIDI resuelto contra el catálogo local.
 */
final class ResolvedCidiInasistenciaTipo
{
    /** @param  'J'|'I'|null  $just  null = sin justificación en BD (p. ej. ed. física) */
    public function __construct(
        public readonly int $idTipo,
        public readonly ?string $just,
        public readonly float $cantidad,
    ) {}
}
