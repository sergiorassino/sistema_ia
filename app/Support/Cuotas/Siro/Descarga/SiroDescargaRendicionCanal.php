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
     * True si la abreviatura del canal SIRO existe en {@see CuotaTipoPago} (medios de pago válidos).
     * Canales de rechazo (BPR, DDR, MCR, VSR, etc.) no están en la tabla y deben descartarse.
     */
    public static function esMedioPagoConocido(string $canalAbrev): bool
    {
        $canalAbrev = strtoupper(trim($canalAbrev));
        if ($canalAbrev === '') {
            return false;
        }

        self::cargarMapa();

        return isset(self::$porAbrev[$canalAbrev]);
    }

    /**
     * Mensaje de detalle cuando el canal no es un medio de pago configurado.
     */
    public static function detalleRechazoCanal(string $canalAbrev, string $textoTrasCanal = ''): string
    {
        $canalAbrev = strtoupper(trim($canalAbrev));
        $canalEtiqueta = $canalAbrev !== '' ? $canalAbrev : '(vacío)';
        $detalle = 'Canal '.$canalEtiqueta.' no está en medios de pago (rechazo SIRO).';

        $textoTrasCanal = trim(preg_replace('/\s+/', ' ', $textoTrasCanal) ?? '');
        if ($textoTrasCanal !== '') {
            $detalle .= ' '.$textoTrasCanal;
        }

        return $detalle;
    }

    /**
     * @return list<array{id: int, label: string, abrev: string, tipoPago: string}>
     */
    public static function opcionesPlanilla(): array
    {
        return self::mapearOpciones(
            CuotaTipoPago::query()
                ->where('tipoPago', '!=', '')
                ->orderBy('id')
                ->get(['id', 'tipoPago', 'abrev'])
        );
    }

    /**
     * Opciones para el formulario de alta (filtradas por tenant si corresponde).
     *
     * @return list<array{id: int, label: string, abrev: string, tipoPago: string}>
     */
    public static function opcionesPlanillaParaAlta(): array
    {
        $opciones = self::opcionesPlanilla();
        $filtros = tenantCuotasSiroDescargaRendicionCanalesPlanilla();

        if ($filtros === []) {
            return $opciones;
        }

        return array_values(array_filter(
            $opciones,
            static fn (array $opcion): bool => self::coincideCanalPlanilla($opcion, $filtros)
        ));
    }

    public static function canalPlanillaPorDefecto(): int
    {
        $opciones = self::opcionesPlanillaParaAlta();

        return $opciones !== [] ? (int) $opciones[0]['id'] : 8;
    }

    /**
     * @param  list<array{abrev: string, tipoPago: string}>|array{abrev: string, tipoPago: string}  $opcion
     * @param  list<string>  $filtros
     */
    public static function coincideCanalPlanilla(array $opcion, array $filtros): bool
    {
        $abrev = strtoupper(trim((string) ($opcion['abrev'] ?? '')));
        $tipoPago = mb_strtolower(trim((string) ($opcion['tipoPago'] ?? '')));

        foreach ($filtros as $filtro) {
            $filtro = trim((string) $filtro);
            if ($filtro === '') {
                continue;
            }

            if ($abrev !== '' && $abrev === strtoupper($filtro)) {
                return true;
            }

            $filtroLower = mb_strtolower($filtro);
            if ($tipoPago !== '' && ($tipoPago === $filtroLower || str_contains($tipoPago, $filtroLower))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CuotaTipoPago>  $tipos
     * @return list<array{id: int, label: string, abrev: string, tipoPago: string}>
     */
    private static function mapearOpciones($tipos): array
    {
        return $tipos
            ->map(static fn (CuotaTipoPago $t) => [
                'id' => (int) $t->id,
                'label' => trim((string) $t->tipoPago).' ('.trim((string) $t->abrev).')',
                'abrev' => trim((string) $t->abrev),
                'tipoPago' => trim((string) $t->tipoPago),
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
