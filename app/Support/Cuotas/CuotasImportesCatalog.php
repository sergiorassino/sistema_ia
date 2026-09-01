<?php

namespace App\Support\Cuotas;

use App\Models\Cuota;
use App\Models\CuotasImporte;
use App\Models\Curso;
use Illuminate\Validation\Rule;

/**
 * Reglas y catálogos del ABM de importes por curso (`cuotasimportes`).
 */
final class CuotasImportesCatalog
{
    /** @return array<string, string> */
    public static function opcionesSigno(): array
    {
        return [
            '-' => 'Bonificación (−)',
            '+' => 'Interés (+)',
        ];
    }

    /** @return array<string, string> */
    public static function opcionesPorcan(): array
    {
        return [
            '%' => '%',
            '$' => '$',
            'm' => 'm',
            'p' => 'p',
        ];
    }

    /** @return array<string, string> */
    public static function leyendasPorcan(): array
    {
        return [
            '%' => 'Porcentaje sobre el saldo.',
            '$' => 'Monto fijo.',
            'm' => 'Monto fijo mensual acumulado desde el 1.er vencimiento.',
            'p' => 'Porcentaje mensual acumulado sobre el saldo desde el 1.er vencimiento.',
        ];
    }

    /**
     * Campos que el editor de importes puede persistir celda a celda.
     *
     * @return list<string>
     */
    public static function camposEditables(): array
    {
        return [
            'importe',
            'signo1v', 'valor1v', 'porcan1v',
            'signo2v', 'valor2v', 'porcan2v',
            'signo3v', 'valor3v', 'porcan3v',
            'signo4v', 'valor4v', 'porcan4v',
        ];
    }

    public static function esCampoMonto(string $field): bool
    {
        return $field === 'importe' || preg_match('/^valor[1-4]v$/', $field) === 1;
    }

    public static function formatearCampoParaInput(string $field, mixed $valor): string
    {
        if ($field === 'importe') {
            return CuotasFormato::importeParaInput($valor);
        }

        if (preg_match('/^valor[1-4]v$/', $field) === 1) {
            return number_format((float) ($valor ?? 0), 2, ',', '');
        }

        if (str_starts_with($field, 'signo')) {
            $fallback = $field === 'signo1v' ? '-' : '+';

            return self::normalizarSigno($valor, $fallback);
        }

        if (str_starts_with($field, 'porcan')) {
            return self::normalizarPorcan($valor);
        }

        return trim((string) ($valor ?? ''));
    }

    /**
     * Valores de la fila listos para inputs (sin etiqueta de curso).
     *
     * @return array<string, string>
     */
    public static function valoresDraftDesdeRegistro(CuotasImporte $registro): array
    {
        $out = [];
        foreach (self::camposEditables() as $campo) {
            $out[$campo] = self::formatearCampoParaInput($campo, $registro->{$campo} ?? null);
        }

        return $out;
    }

    public static function valorPersistidoParaCampo(string $field, mixed $valorDraft): float|string
    {
        if ($field === 'importe') {
            return CuotasFormato::parseImporte((string) $valorDraft);
        }

        if (preg_match('/^valor[1-4]v$/', $field) === 1) {
            return self::parseValorCampo($valorDraft);
        }

        if (str_starts_with($field, 'signo')) {
            return self::normalizarSigno($valorDraft, '-');
        }

        if (str_starts_with($field, 'porcan')) {
            return self::normalizarPorcan($valorDraft);
        }

        return trim((string) $valorDraft);
    }

    public static function campoEquivaleAlRegistro(CuotasImporte $registro, string $field, float|string $persistido): bool
    {
        $actual = $registro->{$field} ?? null;

        if (self::esCampoMonto($field)) {
            return round((float) ($actual ?? 0), 2) === round((float) $persistido, 2);
        }

        return trim((string) ($actual ?? '')) === trim((string) $persistido);
    }

    public static function idTerlecActivo(): int
    {
        return CuotasPlantillaCatalog::idTerlecActivo();
    }

    public static function cuotaDelCicloOrFail(int $idCuotas): Cuota
    {
        return Cuota::query()
            ->whereKey($idCuotas)
            ->where('idTerlec', self::idTerlecActivo())
            ->firstOrFail();
    }

    public static function importeDelCicloOrFail(int $id, int $idCuotas): CuotasImporte
    {
        return CuotasImporte::query()
            ->whereKey($id)
            ->where('idCuotas', $idCuotas)
            ->firstOrFail();
    }

    /**
     * Valores por defecto de fórmulas al dar de alta una plantilla.
     * Definidos en `config/tenant.php`; override en `config/tenants/{slug}.php`.
     *
     * @return array<string, float|string>
     */
    public static function valoresInicialesRegistro(): array
    {
        /** @var array<string, mixed> $raw */
        $raw = config('tenant.cuotas.formulas_iniciales_plantilla', []);

        return [
            'importe' => round((float) ($raw['importe'] ?? 0), 2),
            'signo1v' => self::normalizarSigno($raw['signo1v'] ?? '+', '+'),
            'valor1v' => round((float) ($raw['valor1v'] ?? 0), 2),
            'porcan1v' => self::normalizarPorcan($raw['porcan1v'] ?? '%'),
            'signo2v' => self::normalizarSigno($raw['signo2v'] ?? '+', '+'),
            'valor2v' => round((float) ($raw['valor2v'] ?? 0), 2),
            'porcan2v' => self::normalizarPorcan($raw['porcan2v'] ?? '%'),
            'signo3v' => self::normalizarSigno($raw['signo3v'] ?? '+', '+'),
            'valor3v' => round((float) ($raw['valor3v'] ?? 0), 2),
            'porcan3v' => self::normalizarPorcan($raw['porcan3v'] ?? '%'),
            'signo4v' => self::normalizarSigno($raw['signo4v'] ?? '+', '+'),
            'valor4v' => round((float) ($raw['valor4v'] ?? 0), 2),
            'porcan4v' => self::normalizarPorcan($raw['porcan4v'] ?? '%'),
        ];
    }

    /**
     * Fórmulas de bonificación / interés por vencimiento (sin importe por curso).
     *
     * @return array<string, float|string>
     */
    public static function formulasDesdeRegistro(CuotasImporte $registro): array
    {
        return [
            'importe' => 0.0,
            'signo1v' => self::normalizarSigno($registro->signo1v),
            'valor1v' => round((float) ($registro->valor1v ?? 0), 2),
            'porcan1v' => self::normalizarPorcan($registro->porcan1v),
            'signo2v' => self::normalizarSigno($registro->signo2v, '+'),
            'valor2v' => round((float) ($registro->valor2v ?? 0), 2),
            'porcan2v' => self::normalizarPorcan($registro->porcan2v),
            'signo3v' => self::normalizarSigno($registro->signo3v, '+'),
            'valor3v' => round((float) ($registro->valor3v ?? 0), 2),
            'porcan3v' => self::normalizarPorcan($registro->porcan3v),
            'signo4v' => self::normalizarSigno($registro->signo4v, '+'),
            'valor4v' => round((float) ($registro->valor4v ?? 0), 2),
            'porcan4v' => self::normalizarPorcan($registro->porcan4v),
        ];
    }

    /**
     * @return array<int, array<string, float|string>>
     */
    public static function formulasPorCursoDesdeCuotaModelo(int $idCuotaModelo, ?int $idTerlec = null): array
    {
        $idTerlec = $idTerlec ?? self::idTerlecActivo();

        Cuota::query()
            ->whereKey($idCuotaModelo)
            ->where('idTerlec', $idTerlec)
            ->firstOrFail();

        $porCurso = [];

        $registros = CuotasImporte::query()
            ->where('idCuotas', $idCuotaModelo)
            ->get(['idCursos', 'signo1v', 'valor1v', 'porcan1v', 'signo2v', 'valor2v', 'porcan2v', 'signo3v', 'valor3v', 'porcan3v', 'signo4v', 'valor4v', 'porcan4v']);

        foreach ($registros as $registro) {
            $idCurso = (int) $registro->idCursos;
            if ($idCurso > 0) {
                $porCurso[$idCurso] = self::formulasDesdeRegistro($registro);
            }
        }

        return $porCurso;
    }

    /**
     * Crea un registro en `cuotasimportes` por cada curso del ciclo lectivo indicado.
     * Si `$idCuotaModelo` está definido, copia fórmulas curso a curso; el importe siempre queda en 0.
     */
    public static function crearRegistrosParaCuota(
        int $idCuotas,
        ?int $idTerlec = null,
        ?int $idCuotaModelo = null,
    ): void {
        $idTerlec = $idTerlec ?? self::idTerlecActivo();
        $defaults = self::valoresInicialesRegistro();
        $formulasPorCurso = $idCuotaModelo !== null && $idCuotaModelo > 0
            ? self::formulasPorCursoDesdeCuotaModelo($idCuotaModelo, $idTerlec)
            : [];

        $idCursos = Curso::query()
            ->where('idTerlec', $idTerlec)
            ->orderBy('Id')
            ->pluck('Id');

        if ($idCursos->isEmpty()) {
            return;
        }

        $filas = $idCursos->map(function (int $idCurso) use ($idCuotas, $formulasPorCurso, $defaults): array {
            $base = $formulasPorCurso[$idCurso] ?? $defaults;

            return array_merge($base, [
                'idCuotas' => $idCuotas,
                'idCursos' => $idCurso,
                'importe' => 0.0,
            ]);
        })->all();

        CuotasImporte::query()->insert($filas);
    }

    private static function normalizarSigno(mixed $valor, string $fallback = '-'): string
    {
        $signo = trim((string) $valor);

        return array_key_exists($signo, self::opcionesSigno()) ? $signo : $fallback;
    }

    public static function normalizarPorcan(mixed $valor): string
    {
        $porcan = trim((string) $valor);

        return array_key_exists($porcan, self::opcionesPorcan()) ? $porcan : '%';
    }

    public static function eliminarPorCuota(int $idCuotas): void
    {
        CuotasImporte::query()->where('idCuotas', $idCuotas)->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function reglasFila(string $key, array $data): array
    {
        $signos = array_keys(self::opcionesSigno());
        $porcans = array_keys(self::opcionesPorcan());

        return [
            "draft.{$key}.importe" => ['required', 'string', 'max:24'],
            "draft.{$key}.signo1v" => ['required', 'string', Rule::in($signos)],
            "draft.{$key}.valor1v" => ['required', 'string', 'max:16'],
            "draft.{$key}.porcan1v" => ['required', 'string', Rule::in($porcans)],
            "draft.{$key}.signo2v" => ['required', 'string', Rule::in($signos)],
            "draft.{$key}.valor2v" => ['required', 'string', 'max:16'],
            "draft.{$key}.porcan2v" => ['required', 'string', Rule::in($porcans)],
            "draft.{$key}.signo3v" => ['required', 'string', Rule::in($signos)],
            "draft.{$key}.valor3v" => ['required', 'string', 'max:16'],
            "draft.{$key}.porcan3v" => ['required', 'string', Rule::in($porcans)],
            "draft.{$key}.signo4v" => ['required', 'string', Rule::in($signos)],
            "draft.{$key}.valor4v" => ['required', 'string', 'max:16'],
            "draft.{$key}.porcan4v" => ['required', 'string', Rule::in($porcans)],
        ];
    }

    /**
     * @param  array<string, mixed>  $draftRow
     * @return array<string, mixed>
     */
    public static function payloadDesdeDraft(array $draftRow): array
    {
        return [
            'importe' => CuotasFormato::parseImporte((string) ($draftRow['importe'] ?? '')),
            'signo1v' => (string) ($draftRow['signo1v'] ?? '-'),
            'valor1v' => self::parseValorCampo($draftRow['valor1v'] ?? 0),
            'porcan1v' => (string) ($draftRow['porcan1v'] ?? '%'),
            'signo2v' => (string) ($draftRow['signo2v'] ?? '+'),
            'valor2v' => self::parseValorCampo($draftRow['valor2v'] ?? 0),
            'porcan2v' => (string) ($draftRow['porcan2v'] ?? '%'),
            'signo3v' => (string) ($draftRow['signo3v'] ?? '+'),
            'valor3v' => self::parseValorCampo($draftRow['valor3v'] ?? 0),
            'porcan3v' => (string) ($draftRow['porcan3v'] ?? '%'),
            'signo4v' => (string) ($draftRow['signo4v'] ?? '+'),
            'valor4v' => self::parseValorCampo($draftRow['valor4v'] ?? 0),
            'porcan4v' => (string) ($draftRow['porcan4v'] ?? '%'),
        ];
    }

    public static function parseValorCampo(mixed $valor): float
    {
        return round(CuotasFormato::parseImporte(is_numeric($valor) ? (string) $valor : (string) $valor), 2);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function validarMontos(array $data, string $keyPrefix = ''): void
    {
        $importe = CuotasFormato::parseImporte((string) ($data['importe'] ?? ''));
        if ($importe < 0 || $importe > 99999999.99) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $keyPrefix.'importe' => 'El importe debe estar entre 0 y 99.999.999,99.',
            ]);
        }

        foreach ([1, 2, 3, 4] as $n) {
            $campo = "valor{$n}v";
            $v = self::parseValorCampo($data[$campo] ?? 0);
            if ($v < 0 || $v > 999999.99) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $keyPrefix.$campo => 'El valor debe estar entre 0 y 999.999,99.',
                ]);
            }
        }
    }
}
