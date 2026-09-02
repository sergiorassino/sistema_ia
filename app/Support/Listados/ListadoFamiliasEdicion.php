<?php

namespace App\Support\Listados;

use App\Models\Familia;
use App\Support\DniInput;

/**
 * Normalización y reglas para editar datos de familia desde el listado.
 */
final class ListadoFamiliasEdicion
{
    /**
     * @return array{apellido: string, responsable: string, dniResp: string, email: string}
     */
    public static function filaDesdeModelo(Familia $familia, bool $tieneDniResp): array
    {
        return [
            'apellido' => (string) ($familia->apellido ?? ''),
            'responsable' => (string) ($familia->responsable ?? ''),
            'dniResp' => $tieneDniResp ? (string) ($familia->dniResp ?? '') : '',
            'email' => (string) ($familia->email ?? ''),
        ];
    }

    /**
     * Combina el registro de BD con lo tipeado en la grilla (la grilla pisa campo a campo).
     *
     * @param  array{apellido: string, responsable: string, dniResp: string, email: string}  $base
     * @param  array<string, mixed>  $grilla
     * @return array{apellido: string, responsable: string, dniResp: string, email: string}
     */
    public static function mezclar(array $base, array $grilla): array
    {
        foreach (['apellido', 'responsable', 'dniResp', 'email'] as $campo) {
            if (array_key_exists($campo, $grilla)) {
                $base[$campo] = $grilla[$campo];
            }
        }

        return $base;
    }

    /**
     * DNI con puntos de miles para la grilla. La persistencia usa solo dígitos.
     */
    public static function dniParaGrilla(string $dni): string
    {
        $digits = DniInput::digitsOnly($dni);
        if ($digits === '') {
            return '';
        }

        return number_format((int) $digits, 0, '', '.');
    }

    /**
     * @param  array{apellido: string, responsable: string, dniResp: string, email: string}  $fila
     * @return array{apellido: string, responsable: string, dniResp: string, email: string}
     */
    public static function filaParaGrilla(array $fila, bool $tieneDniResp): array
    {
        if ($tieneDniResp) {
            $fila['dniResp'] = self::dniParaGrilla((string) ($fila['dniResp'] ?? ''));
        }

        return $fila;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{apellido: string, responsable: string, dniResp: string, email: string}
     */
    public static function normalizar(array $fila, bool $tieneDniResp): array
    {
        return [
            'apellido' => trim((string) ($fila['apellido'] ?? '')),
            'responsable' => trim((string) ($fila['responsable'] ?? '')),
            'dniResp' => $tieneDniResp ? DniInput::digitsOnly((string) ($fila['dniResp'] ?? '')) : '',
            'email' => trim((string) ($fila['email'] ?? '')),
        ];
    }

    /**
     * @param  array{apellido: string, responsable: string, dniResp: string, email: string}  $fila
     * @return array<string, list<string>>
     */
    public static function reglas(string $id, array $fila, bool $tieneDniResp): array
    {
        $prefijo = 'filas.'.$id;
        $email = $fila['email'];
        $dni = $fila['dniResp'];

        $rules = [
            $prefijo.'.apellido' => ['required', 'string', 'max:50'],
            $prefijo.'.responsable' => ['nullable', 'string', 'max:100'],
            $prefijo.'.email' => $email === ''
                ? ['nullable', 'string', 'max:150']
                : ['email', 'max:150'],
        ];

        if ($tieneDniResp) {
            $rules[$prefijo.'.dniResp'] = $dni === ''
                ? ['nullable', 'string', 'max:11']
                : ['digits_between:7,11'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(string $id): array
    {
        $prefijo = 'filas.'.$id;

        return [
            $prefijo.'.apellido.required' => 'El apellido de la familia es obligatorio.',
            $prefijo.'.apellido.max' => 'El apellido no puede superar los 50 caracteres.',
            $prefijo.'.responsable.max' => 'El responsable no puede superar los 100 caracteres.',
            $prefijo.'.dniResp.digits_between' => 'El DNI del responsable debe tener entre 7 y 11 dígitos.',
            $prefijo.'.email.email' => 'El email no es válido.',
            $prefijo.'.email.max' => 'El email no puede superar los 150 caracteres.',
        ];
    }

    /**
     * @param  array{apellido: string, responsable: string, dniResp: string, email: string}  $fila
     * @return array<string, mixed>
     */
    public static function payload(array $fila, bool $tieneDniResp): array
    {
        $payload = [
            'apellido' => $fila['apellido'],
            'responsable' => $fila['responsable'],
            'email' => $fila['email'],
        ];

        if ($tieneDniResp) {
            $payload['dniResp'] = $fila['dniResp'] !== '' ? $fila['dniResp'] : null;
        }

        return $payload;
    }

    /**
     * @param  array{apellido: string, responsable: string, dniResp: string, email: string}  $fila
     */
    public static function hash(array $fila): string
    {
        return hash('sha256', json_encode([
            $fila['apellido'],
            $fila['responsable'],
            $fila['dniResp'],
            $fila['email'],
        ], JSON_UNESCAPED_UNICODE));
    }
}
