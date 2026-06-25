<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\CuotaTipoPago;

/**
 * Mapeo canal SIRO → {@see CuotaTipoPago}.
 */
final class SiroDescargaRendicionCanal
{
    /** @var array<string, int>|null */
    private static ?array $porAbrev = null;

    public static function idCuotastipopago(string $canalAbrev): int
    {
        $canalAbrev = strtoupper(trim($canalAbrev));
        if ($canalAbrev === '') {
            return 8;
        }

        self::cargarMapa();

        return self::$porAbrev[$canalAbrev] ?? 8;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public static function opcionesPlanilla(): array
    {
        return CuotaTipoPago::query()
            ->where('tipoPago', '!=', '')
            ->orderBy('id')
            ->get(['id', 'tipoPago', 'abrev'])
            ->map(fn (CuotaTipoPago $t) => [
                'id' => (int) $t->id,
                'label' => trim((string) $t->tipoPago).' ('.trim((string) $t->abrev).')',
            ])
            ->all();
    }

    private static function cargarMapa(): void
    {
        if (self::$porAbrev !== null) {
            return;
        }

        self::$porAbrev = [];
        foreach (CuotaTipoPago::query()->get(['id', 'abrev']) as $tipo) {
            $abrev = strtoupper(trim((string) $tipo->abrev));
            if ($abrev !== '') {
                self::$porAbrev[$abrev] = (int) $tipo->id;
            }
        }
    }
}
