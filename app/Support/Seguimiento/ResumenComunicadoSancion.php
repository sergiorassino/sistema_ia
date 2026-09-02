<?php

namespace App\Support\Seguimiento;

use App\Models\Sancion;
use App\Models\SancionTipo;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de «Hasta la fecha registra un total de» en el comunicado PDF.
 */
final class ResumenComunicadoSancion
{
    /**
     * Totales al corte de `$hastaFecha` (inclusive). Sin esa fecha, no hay tope
     * (no usar en el PDF: una reimpresión incluiría sanciones posteriores).
     *
     * @return list<array{tipo: string, total: int}>
     */
    public static function lineas(
        int $idMatricula,
        ?int $excluirIdSancion = null,
        DateTimeInterface|string|null $hastaFecha = null,
    ): array {
        if ($idMatricula < 1) {
            return [];
        }

        $corte = self::normalizarHastaFecha($hastaFecha);

        if (Schema::hasTable('sanciontipo') && Schema::hasColumn('sanciontipo', 'enResumenComunicado')) {
            return self::desdeFlag($idMatricula, $excluirIdSancion, $corte);
        }

        return self::legadoApercibAmonest($idMatricula, $excluirIdSancion, $corte);
    }

    /**
     * Nombre del tipo según cantidad: 1 → singular; 0 o 2+ → plural.
     * «1 Amonestación», «3 Amonestaciones», «1 Firma», «2 Firmas».
     * En frases, se flexiona la primera palabra («1 Llamado de Atención»).
     */
    public static function etiquetaSegunCantidad(string $tipo, int $cantidad): string
    {
        $tipo = trim($tipo);
        if ($tipo === '') {
            return $tipo;
        }

        $partes = preg_split('/\s+/u', $tipo) ?: [$tipo];
        $partes[0] = $cantidad === 1
            ? self::palabraSingular($partes[0])
            : self::palabraPlural($partes[0]);

        return implode(' ', $partes);
    }

    private static function palabraSingular(string $palabra): string
    {
        if ($palabra === '') {
            return $palabra;
        }

        if (str_contains($palabra, '/')) {
            return explode('/', $palabra, 2)[0];
        }

        if (preg_match('/ciones$/iu', $palabra)) {
            return (string) preg_replace('/ciones$/iu', 'ción', $palabra);
        }
        if (preg_match('/siones$/iu', $palabra)) {
            return (string) preg_replace('/siones$/iu', 'sión', $palabra);
        }
        if (preg_match('/([^aeiouáéíóúü])es$/iu', $palabra)) {
            return mb_substr($palabra, 0, -2);
        }
        if (preg_match('/[aeiouáéíóúü]s$/iu', $palabra) && ! preg_match('/ís$/iu', $palabra)) {
            return mb_substr($palabra, 0, -1);
        }

        return $palabra;
    }

    private static function palabraPlural(string $palabra): string
    {
        $sing = self::palabraSingular($palabra);
        if ($sing === '') {
            return $sing;
        }

        if (preg_match('/ción$/iu', $sing)) {
            return (string) preg_replace('/ción$/iu', 'ciones', $sing);
        }
        if (preg_match('/sión$/iu', $sing)) {
            return (string) preg_replace('/sión$/iu', 'siones', $sing);
        }
        if (preg_match('/z$/iu', $sing)) {
            return (string) preg_replace('/z$/iu', 'ces', $sing);
        }

        $ultima = mb_strtolower(mb_substr($sing, -1));
        if (preg_match('/[aeiouáéíóúü]/u', $ultima)) {
            return $sing.'s';
        }

        return $sing.'es';
    }

    /**
     * @return list<array{tipo: string, total: int}>
     */
    private static function desdeFlag(int $idMatricula, ?int $excluirIdSancion, ?string $hastaFecha): array
    {
        $tipos = SancionTipo::query()
            ->where('enResumenComunicado', 1)
            ->orderBy('id')
            ->get(['id', 'tipo']);

        if ($tipos->isEmpty()) {
            return [];
        }

        $query = Sancion::query()
            ->where('idMatricula', $idMatricula)
            ->whereIn('idTipoSancion', $tipos->pluck('id'));

        self::aplicarCorteFecha($query, $hastaFecha, 'fecha');

        if ($excluirIdSancion !== null && $excluirIdSancion > 0) {
            $query->where('id', '!=', $excluirIdSancion);
        }

        $cantidades = $query
            ->selectRaw('idTipoSancion, COALESCE(SUM(COALESCE(cantidad, 1)), 0) as total')
            ->groupBy('idTipoSancion')
            ->pluck('total', 'idTipoSancion');

        $lineas = [];
        foreach ($tipos as $tipo) {
            $nombre = trim((string) ($tipo->tipo ?? ''));
            if ($nombre === '') {
                continue;
            }
            $total = (int) ($cantidades[$tipo->id] ?? 0);
            $lineas[] = [
                'tipo' => self::etiquetaSegunCantidad($nombre, $total),
                'total' => $total,
            ];
        }

        return $lineas;
    }

    /**
     * Tenant sin columna: mismo criterio histórico (nombres con apercib / amonest).
     *
     * @return list<array{tipo: string, total: int}>
     */
    private static function legadoApercibAmonest(int $idMatricula, ?int $excluirIdSancion, ?string $hastaFecha): array
    {
        $query = Sancion::query()
            ->join('sanciontipo', 'sanciontipo.id', '=', 'sanciones.idTipoSancion')
            ->where('sanciones.idMatricula', $idMatricula);

        self::aplicarCorteFecha($query, $hastaFecha, 'sanciones.fecha');

        if ($excluirIdSancion !== null && $excluirIdSancion > 0) {
            $query->where('sanciones.id', '!=', $excluirIdSancion);
        }

        $totales = $query
            ->select([
                DB::raw('LOWER(COALESCE(sanciontipo.tipo, "")) as tipo_lower'),
                DB::raw('COALESCE(sanciones.cantidad, 1) as cantidad'),
            ])
            ->get();

        $totalApercib = (int) $totales
            ->filter(fn ($r) => is_string($r->tipo_lower) && str_contains($r->tipo_lower, 'apercib'))
            ->sum(fn ($r) => (int) $r->cantidad);

        $totalAmonest = (int) $totales
            ->filter(fn ($r) => is_string($r->tipo_lower) && str_contains($r->tipo_lower, 'amonest'))
            ->sum(fn ($r) => (int) $r->cantidad);

        return [
            ['tipo' => self::etiquetaSegunCantidad('Apercibimiento', $totalApercib), 'total' => $totalApercib],
            ['tipo' => self::etiquetaSegunCantidad('Amonestación', $totalAmonest), 'total' => $totalAmonest],
        ];
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Sancion>|\Illuminate\Database\Query\Builder  $query */
    private static function aplicarCorteFecha(mixed $query, ?string $hastaFecha, string $columna): void
    {
        if ($hastaFecha === null) {
            return;
        }

        $query->whereDate($columna, '<=', $hastaFecha);
    }

    private static function normalizarHastaFecha(DateTimeInterface|string|null $hastaFecha): ?string
    {
        if ($hastaFecha === null) {
            return null;
        }

        if ($hastaFecha instanceof DateTimeInterface) {
            return $hastaFecha->format('Y-m-d');
        }

        $texto = trim($hastaFecha);
        if ($texto === '') {
            return null;
        }

        try {
            return Carbon::parse($texto)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
