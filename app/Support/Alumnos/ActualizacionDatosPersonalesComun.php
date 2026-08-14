<?php

namespace App\Support\Alumnos;

use App\Models\Legajo;
use App\Models\Matricula;
use App\Support\Database\PersistenciaColumnas;
use App\Support\InformeInasistencias;
use App\Support\MatriculaBloqueos;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Utilidades compartidas entre variantes de actualización de datos personales.
 */
final class ActualizacionDatosPersonalesComun
{
    /** @var array<string, string>|null columna => DATA_TYPE (minúsculas) */
    private static ?array $tiposColumnasLegajo = null;

    /**
     * @return array{legajo: Legajo, matricula: Matricula}|null
     */
    public static function contexto(): ?array
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $matricula = InformeInasistencias::matriculaAutogestion();
        if ($matricula === null) {
            return null;
        }

        $legajo = Legajo::query()->where('id', (int) $ctx->idLegajo)->first();
        if ($legajo === null) {
            return null;
        }

        return ['legajo' => $legajo, 'matricula' => $matricula];
    }

    public static function estaBloqueado(Matricula $matricula): bool
    {
        return self::estadoBloqueo($matricula)['bloqueado'];
    }

    public static function mensajeBloqueo(Matricula $matricula): string
    {
        return self::estadoBloqueo($matricula)['mensaje'];
    }

    /**
     * @return array{bloqueado: bool, mensaje: string}
     */
    public static function estadoBloqueo(Matricula $matricula): array
    {
        $restriccion = MatriculaBloqueos::impideFichaYDatosAutogestion($matricula);

        return [
            'bloqueado' => $restriccion['bloqueada'],
            'mensaje' => $restriccion['mensaje'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function persistirLegajo(Legajo $legajo, array $data): void
    {
        $id = (int) $legajo->id;
        if ($id < 1) {
            throw new \InvalidArgumentException('Legajo inválido.');
        }

        $data = self::adaptarValoresAEsquema($data);
        $data = self::filtrarColumnasLegajoActualizables($data);

        $preparado = PersistenciaColumnas::prepararPayload('legajos', $data);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            throw new \RuntimeException(
                PersistenciaColumnas::mensajeColumnasInexistentes(
                    'legajos',
                    $preparado['columnas_con_valor_sin_columna']
                )
            );
        }

        $payload = $preparado['payload'];
        if ($payload === []) {
            throw new \RuntimeException('No hay datos para guardar.');
        }

        if (! Legajo::query()->where('id', $id)->exists()) {
            throw new \RuntimeException('No se encontró el legajo a actualizar.');
        }

        try {
            Legajo::query()->where('id', $id)->update($payload);
        } catch (QueryException $e) {
            report($e);
            throw new \RuntimeException(
                PersistenciaColumnas::mensajeDesdeQueryException($e)
                    ?? 'No se pudieron guardar los datos. Intente nuevamente o contacte a secretaría.',
                0,
                $e
            );
        }

        $esperados = $payload;
        unset($esperados['fechActDatos']);

        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            'legajos',
            ['id' => $id],
            $esperados
        );
        if ($noPersistidas !== []) {
            throw new \RuntimeException(
                PersistenciaColumnas::mensajeColumnasNoPersistidas('legajos', $noPersistidas)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function filtrarColumnasLegajoActualizables(array $data): array
    {
        if (! Schema::hasColumn('legajos', 'fechActDatos')) {
            unset($data['fechActDatos']);
        }

        return $data;
    }

    /**
     * Limpia espacios y caracteres invisibles frecuentes al copiar/pegar (p. ej. NBSP).
     * Uno o más guiones (ASCII o tipográficos) se normalizan a "-" (dato no corresponde).
     */
    public static function normalizarTextoInput(mixed $value): string
    {
        $v = (string) $value;
        $v = preg_replace('/[\x{00A0}\x{200B}-\x{200D}\x{FEFF}]/u', '', $v) ?? $v;
        $v = trim($v);

        if (self::esGuionNoCorresponde($v)) {
            return '-';
        }

        return $v;
    }

    /**
     * Guión de “no corresponde”: uno o más guiones ASCII o rayas tipográficas
     * (p. ej. `-`, `--`, `---`, en-dash). No convierte fechas ni textos con letras.
     */
    public static function esGuionNoCorresponde(mixed $value): bool
    {
        $v = trim((string) $value);
        if ($v === '') {
            return false;
        }

        $compacto = preg_replace('/\s+/u', '', $v) ?? $v;

        return (bool) preg_match('/^[\-\x{2010}-\x{2015}\x{2212}]+$/u', $compacto);
    }

    /**
     * DNI 0 en columnas numéricas legacy = “no corresponde” (se muestra como guión).
     * Vacío en VARCHAR se deja vacío.
     */
    public static function textoDniDesdeLegajo(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        $s = trim((string) $valor);
        if ($s === '0') {
            return '-';
        }

        return $s;
    }

    /**
     * Campo de texto obligatorio: contenido real o un guión si no corresponde.
     */
    public static function textoObligatorioAceptado(mixed $value): bool
    {
        return self::normalizarTextoInput($value) !== '';
    }

    /**
     * @return list<mixed>
     */
    public static function reglaTextoObligatorioOGuion(int $max = 200): array
    {
        return [
            'required',
            'string',
            'max:'.$max,
            static function (string $attribute, mixed $value, \Closure $fail): void {
                if (! self::textoObligatorioAceptado($value)) {
                    $fail('Este campo es obligatorio. Si no corresponde, escriba un guión (-).');
                }
            },
        ];
    }

    /**
     * Limpia espacios y caracteres invisibles frecuentes al copiar/pegar (p. ej. NBSP).
     */
    public static function normalizarEmailInput(mixed $value): string
    {
        return self::normalizarTextoInput($value);
    }

    /**
     * E-mail obligatorio, guión (-) si no corresponde, o vacío si es opcional.
     */
    public static function emailInputAceptado(mixed $value, bool $opcional): bool
    {
        $v = self::normalizarEmailInput($value);
        if ($opcional && $v === '') {
            return true;
        }
        if (self::esGuionNoCorresponde($v)) {
            return true;
        }
        if ($v === '') {
            return false;
        }

        return filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Ajusta valores al tipo real de cada columna (p. ej. DNI INT no acepta "-").
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function adaptarValoresAEsquema(array $data): array
    {
        $tipos = self::tiposColumnasLegajo();
        $adaptado = [];

        foreach ($data as $columna => $valor) {
            $tipo = $tipos[$columna] ?? '';

            if (self::esTipoEntero($tipo)) {
                $adaptado[$columna] = self::enteroDesdeInput($valor);

                continue;
            }

            if (in_array($tipo, ['date', 'datetime', 'timestamp'], true)) {
                if ($valor instanceof \DateTimeInterface) {
                    $adaptado[$columna] = $valor->format($tipo === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s');

                    continue;
                }
                if (is_string($valor) && self::esGuionNoCorresponde($valor)) {
                    $adaptado[$columna] = null;

                    continue;
                }
            }

            $adaptado[$columna] = $valor;
        }

        return $adaptado;
    }

    private static function enteroDesdeInput(mixed $valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }
        if (is_int($valor)) {
            return $valor;
        }
        if (is_float($valor)) {
            return (int) $valor;
        }

        $texto = self::normalizarTextoInput($valor);
        if (self::esGuionNoCorresponde($texto)) {
            return 0;
        }

        $digits = preg_replace('/\D/', '', $texto) ?? '';

        return $digits === '' ? 0 : (int) $digits;
    }

    private static function esTipoEntero(string $tipo): bool
    {
        return in_array($tipo, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true);
    }

    /**
     * @return array<string, string>
     */
    private static function tiposColumnasLegajo(): array
    {
        if (self::$tiposColumnasLegajo !== null) {
            return self::$tiposColumnasLegajo;
        }

        self::$tiposColumnasLegajo = [];

        if (! Schema::hasTable('legajos')) {
            return self::$tiposColumnasLegajo;
        }

        $db = DB::getDatabaseName();
        if ($db === '') {
            return self::$tiposColumnasLegajo;
        }

        $rows = DB::select(
            'SELECT COLUMN_NAME, DATA_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$db, 'legajos']
        );

        foreach ($rows as $row) {
            self::$tiposColumnasLegajo[(string) $row->COLUMN_NAME] = strtolower((string) $row->DATA_TYPE);
        }

        return self::$tiposColumnasLegajo;
    }
}
