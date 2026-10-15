<?php

namespace App\Support\Alumnos;

use App\Models\LegajoAutorizadoEmergencia;
use App\Support\Database\PersistenciaColumnas;
use App\Support\DniInput;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Lista unificada de personas autorizadas para comunicación y retiro
 * (San Francisco de Asís). Hasta 6 registros; mínimo 2.
 */
final class AutorizadosEmergenciaRetiro
{
    public const TABLA = 'legajo_autorizados_emergencia';

    public const MIN = 2;

    public const MAX = 6;

    public static function habilitado(): bool
    {
        return tenantAutogestionContactosEmergenciaUnificados();
    }

    /**
     * @return array{apellido_nombre: string, dni: string, relacion: string, telefono: string}
     */
    public static function filaVacia(): array
    {
        return [
            'apellido_nombre' => '',
            'dni' => '',
            'relacion' => '',
            'telefono' => '',
        ];
    }

    /**
     * Filas guardadas, sin rellenar vacíos. Lista vacía si la tabla no existe.
     *
     * @return list<array{apellido_nombre: string, dni: string, relacion: string, telefono: string}>
     */
    public static function filasDeLegajo(int $idLegajo): array
    {
        if ($idLegajo < 1 || ! Schema::hasTable(self::TABLA)) {
            return [];
        }

        return LegajoAutorizadoEmergencia::query()
            ->where('idLegajo', $idLegajo)
            ->orderBy('orden')
            ->orderBy('id')
            ->limit(self::MAX)
            ->get(['apellido_nombre', 'dni', 'relacion', 'telefono'])
            ->map(static fn (LegajoAutorizadoEmergencia $row): array => [
                'apellido_nombre' => (string) $row->apellido_nombre,
                'dni' => (string) $row->dni,
                'relacion' => (string) $row->relacion,
                'telefono' => (string) $row->telefono,
            ])
            ->all();
    }

    /**
     * Filas para el formulario: las guardadas, completadas hasta el mínimo.
     *
     * @return list<array{apellido_nombre: string, dni: string, relacion: string, telefono: string}>
     */
    public static function filasParaFormulario(int $idLegajo): array
    {
        $filas = self::filasDeLegajo($idLegajo);

        while (count($filas) < self::MIN) {
            $filas[] = self::filaVacia();
        }

        return array_slice($filas, 0, self::MAX);
    }

    /**
     * @param  list<mixed>  $filas
     * @return list<array{apellido_nombre: string, dni: string, relacion: string, telefono: string}>
     */
    public static function normalizarLista(array $filas): array
    {
        $out = [];

        foreach ($filas as $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $out[] = [
                'apellido_nombre' => ActualizacionDatosPersonalesComun::normalizarTextoInput($fila['apellido_nombre'] ?? ''),
                'dni' => DniInput::digitsOnly((string) ($fila['dni'] ?? '')),
                'relacion' => ActualizacionDatosPersonalesComun::normalizarTextoInput($fila['relacion'] ?? ''),
                'telefono' => ActualizacionDatosPersonalesComun::normalizarTextoInput($fila['telefono'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function reglas(): array
    {
        return [
            'autorizadosEmergencia' => ['required', 'array', 'min:'.self::MIN, 'max:'.self::MAX],
            'autorizadosEmergencia.*.apellido_nombre' => [
                'required', 'string', 'min:3', 'max:120', self::reglaSinGuion('Indique apellido y nombre.'),
            ],
            'autorizadosEmergencia.*.dni' => ['required', 'string', 'regex:/^\d{7,11}$/'],
            'autorizadosEmergencia.*.relacion' => [
                'required', 'string', 'min:2', 'max:60', self::reglaSinGuion('Indique la relación con el estudiante.'),
            ],
            'autorizadosEmergencia.*.telefono' => [
                'required', 'string', 'max:40', self::reglaSinGuion('Indique el teléfono.'), self::reglaTelefono(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'autorizadosEmergencia.required' => 'Debe cargar al menos 2 personas autorizadas.',
            'autorizadosEmergencia.min' => 'Debe cargar al menos 2 personas autorizadas.',
            'autorizadosEmergencia.max' => 'Puede cargar hasta 6 personas autorizadas.',
            'autorizadosEmergencia.*.apellido_nombre.required' => 'Indique apellido y nombre.',
            'autorizadosEmergencia.*.apellido_nombre.min' => 'Indique apellido y nombre.',
            'autorizadosEmergencia.*.dni.required' => 'Indique el DNI.',
            'autorizadosEmergencia.*.dni.regex' => 'El DNI debe tener entre 7 y 11 dígitos.',
            'autorizadosEmergencia.*.relacion.required' => 'Indique la relación con el estudiante.',
            'autorizadosEmergencia.*.relacion.min' => 'Indique la relación con el estudiante.',
            'autorizadosEmergencia.*.telefono.required' => 'Indique el teléfono.',
        ];
    }

    public static function etiqueta(string $campo): ?string
    {
        if ($campo === 'autorizadosEmergencia') {
            return 'Personas autorizadas para comunicación y retiro';
        }

        if (! preg_match('/^autorizadosEmergencia\.(\d+)\.(apellido_nombre|dni|relacion|telefono)$/', $campo, $m)) {
            return null;
        }

        $n = (int) $m[1] + 1;
        $parte = match ($m[2]) {
            'apellido_nombre' => 'Apellido y nombre',
            'dni' => 'DNI',
            'relacion' => 'Relación con el estudiante',
            'telefono' => 'Teléfono',
            default => $m[2],
        };

        return "Persona autorizada {$n} — {$parte}";
    }

    /**
     * Reemplaza las personas del legajo. Debe ejecutarse dentro de la transacción del guardado.
     *
     * @param  list<array<string, mixed>>  $filas
     */
    public static function reemplazar(int $idLegajo, array $filas): void
    {
        if ($idLegajo < 1) {
            throw new \InvalidArgumentException('Legajo inválido.');
        }

        if (! Schema::hasTable(self::TABLA)) {
            throw new \RuntimeException(
                'No se pudieron guardar las personas autorizadas: falta la tabla legajo_autorizados_emergencia. '
                .'Ejecute la migración o el SQL del módulo.'
            );
        }

        $normalizadas = self::normalizarLista($filas);
        if (count($normalizadas) < self::MIN || count($normalizadas) > self::MAX) {
            throw new \RuntimeException('Debe indicar entre 2 y 6 personas autorizadas.');
        }

        $payloads = [];
        $orden = 1;
        foreach ($normalizadas as $fila) {
            if (! self::filaCompleta($fila)) {
                throw new \RuntimeException('Cada persona autorizada debe tener apellido y nombre, DNI, relación y teléfono.');
            }

            $payload = [
                'idLegajo' => $idLegajo,
                'orden' => $orden,
                'apellido_nombre' => $fila['apellido_nombre'],
                'dni' => $fila['dni'],
                'relacion' => $fila['relacion'],
                'telefono' => $fila['telefono'],
            ];
            $preparado = PersistenciaColumnas::prepararPayload(self::TABLA, $payload);
            if ($preparado['columnas_con_valor_sin_columna'] !== []) {
                throw new \RuntimeException(
                    PersistenciaColumnas::mensajeColumnasInexistentes(
                        self::TABLA,
                        $preparado['columnas_con_valor_sin_columna']
                    )
                );
            }

            $payloads[] = $preparado['payload'];
            $orden++;
        }

        try {
            LegajoAutorizadoEmergencia::query()->where('idLegajo', $idLegajo)->delete();

            foreach ($payloads as $payload) {
                $modelo = LegajoAutorizadoEmergencia::query()->create($payload);
                $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
                    self::TABLA,
                    ['id' => (int) $modelo->id],
                    $payload
                );
                if ($noPersistidas !== []) {
                    throw new \RuntimeException(
                        PersistenciaColumnas::mensajeColumnasNoPersistidas(self::TABLA, $noPersistidas)
                    );
                }
            }
        } catch (QueryException $e) {
            report($e);
            throw new \RuntimeException(
                PersistenciaColumnas::mensajeDesdeQueryException($e)
                    ?? 'No se pudieron guardar las personas autorizadas.',
                0,
                $e
            );
        }
    }

    /**
     * @param  array{apellido_nombre: string, dni: string, relacion: string, telefono: string}  $fila
     */
    private static function filaCompleta(array $fila): bool
    {
        if (ActualizacionDatosPersonalesComun::esGuionNoCorresponde($fila['apellido_nombre'])
            || ActualizacionDatosPersonalesComun::esGuionNoCorresponde($fila['relacion'])
            || ActualizacionDatosPersonalesComun::esGuionNoCorresponde($fila['telefono'])) {
            return false;
        }

        if (mb_strlen($fila['apellido_nombre']) < 3 || mb_strlen($fila['relacion']) < 2) {
            return false;
        }

        if (! preg_match('/^\d{7,11}$/', $fila['dni'])) {
            return false;
        }

        $digitosTel = preg_replace('/\D/', '', $fila['telefono']) ?? '';

        return strlen($digitosTel) >= 6 && mb_strlen($fila['telefono']) <= 40;
    }

    private static function reglaSinGuion(string $mensaje): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($mensaje): void {
            if (ActualizacionDatosPersonalesComun::esGuionNoCorresponde($value)) {
                $fail($mensaje);
            }
        };
    }

    private static function reglaTelefono(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            $digitos = preg_replace('/\D/', '', (string) $value) ?? '';
            if (strlen($digitos) < 6) {
                $fail('Indique un teléfono válido.');
            }
        };
    }
}
