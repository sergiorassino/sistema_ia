<?php

namespace App\Support\Cooperadora;

use App\Models\Legajo;
use Illuminate\Support\Facades\Schema;

/**
 * Padre, madre y tutor del legajo para el ingreso cooperadora.
 * En BD legacy el nombre completo vive en nombrepad / nombremad / nombretut.
 */
final class ResponsablesLegajoCooperadora
{
    /** @var list<string> */
    public const VINCULOS = ['padre', 'madre', 'tutor'];

    /**
     * @return array{
     *   padre: array{apellido: string, nombre: string, dni: string, email: string},
     *   madre: array{apellido: string, nombre: string, dni: string, email: string},
     *   tutor: array{apellido: string, nombre: string, dni: string, email: string},
     * }
     */
    public static function estructuraVacia(): array
    {
        return [
            'padre' => self::filaVacia(),
            'madre' => self::filaVacia(),
            'tutor' => self::filaVacia(),
        ];
    }

    public static function legajo(int $idLegajo): ?Legajo
    {
        if ($idLegajo < 1 || BusquedaEstudianteCooperadora::legajo($idLegajo) === null) {
            return null;
        }

        return Legajo::query()->find($idLegajo);
    }

    /**
     * @return array{
     *   padre: array{apellido: string, nombre: string, dni: string, email: string},
     *   madre: array{apellido: string, nombre: string, dni: string, email: string},
     *   tutor: array{apellido: string, nombre: string, dni: string, email: string},
     * }
     */
    public static function cargarDatosDesdeLegajo(int $idLegajo): array
    {
        $legajo = self::legajo($idLegajo);

        return $legajo !== null ? self::desdeLegajo($legajo) : self::estructuraVacia();
    }

    /**
     * @return array{
     *   padre: array{apellido: string, nombre: string, dni: string, email: string},
     *   madre: array{apellido: string, nombre: string, dni: string, email: string},
     *   tutor: array{apellido: string, nombre: string, dni: string, email: string},
     * }
     */
    public static function desdeLegajo(Legajo $legajo): array
    {
        return [
            'padre' => self::filaDesdeColumnas(
                (string) ($legajo->nombrepad ?? ''),
                (string) ($legajo->dnipad ?? ''),
                (string) ($legajo->emailpad ?? ''),
            ),
            'madre' => self::filaDesdeColumnas(
                (string) ($legajo->nombremad ?? ''),
                (string) ($legajo->dnimad ?? ''),
                (string) ($legajo->emailmad ?? ''),
            ),
            'tutor' => self::filaDesdeColumnas(
                (string) ($legajo->nombretut ?? ''),
                (string) ($legajo->dnitut ?? ''),
                (string) ($legajo->emailtut ?? ''),
            ),
        ];
    }

    /**
     * @param  array{
     *   padre?: array{apellido?: string, nombre?: string, dni?: string, email?: string},
     *   madre?: array{apellido?: string, nombre?: string, dni?: string, email?: string},
     *   tutor?: array{apellido?: string, nombre?: string, dni?: string, email?: string},
     * }  $responsables
     * @param  list<int>  $idsLegajo
     */
    public static function guardarEnLegajos(array $responsables, array $idsLegajo): void
    {
        $idsLegajo = array_values(array_unique(array_filter(array_map('intval', $idsLegajo), fn (int $id) => $id > 0)));
        if ($idsLegajo === []) {
            return;
        }

        $payload = self::payloadLegajo($responsables);
        if ($payload === []) {
            return;
        }

        foreach ($idsLegajo as $idLegajo) {
            if (BusquedaEstudianteCooperadora::legajo($idLegajo) === null) {
                continue;
            }
            Legajo::query()->whereKey($idLegajo)->update($payload);
        }
    }

    /**
     * @param  array{
     *   padre?: array{apellido?: string, nombre?: string, dni?: string, email?: string},
     *   madre?: array{apellido?: string, nombre?: string, dni?: string, email?: string},
     *   tutor?: array{apellido?: string, nombre?: string, dni?: string, email?: string},
     * }  $responsables
     */
    public static function nombrePagador(array $responsables, string $vinculo): string
    {
        if (! in_array($vinculo, self::VINCULOS, true)) {
            return '';
        }

        $fila = $responsables[$vinculo] ?? [];
        $texto = self::unirNombre(
            trim((string) ($fila['apellido'] ?? '')),
            trim((string) ($fila['nombre'] ?? '')),
        );

        return $texto !== '' ? mb_strtoupper($texto, 'UTF-8') : '';
    }

    /**
     * @param  array{
     *   padre?: array{apellido?: string, nombre?: string, dni?: string, email?: string},
     *   madre?: array{apellido?: string, nombre?: string, dni?: string, email?: string},
     *   tutor?: array{apellido?: string, nombre?: string, dni?: string, email?: string},
     * }  $responsables
     */
    public static function emailPagador(array $responsables, string $vinculo): string
    {
        if (! in_array($vinculo, self::VINCULOS, true)) {
            return '';
        }

        return mb_strtolower(trim((string) ($responsables[$vinculo]['email'] ?? '')), 'UTF-8');
    }

    /**
     * @return array{apellido: string, nombre: string, dni: string, email: string}
     */
    public static function filaVacia(): array
    {
        return [
            'apellido' => '',
            'nombre' => '',
            'dni' => '',
            'email' => '',
        ];
    }

    /**
     * @return array{apellido: string, nombre: string}
     */
    public static function separarNombre(string $nombreCompleto): array
    {
        $nombreCompleto = trim($nombreCompleto);
        if ($nombreCompleto === '') {
            return ['apellido' => '', 'nombre' => ''];
        }

        if (str_contains($nombreCompleto, ',')) {
            [$apellido, $nombre] = array_map('trim', explode(',', $nombreCompleto, 2));

            return [
                'apellido' => $apellido,
                'nombre' => $nombre ?? '',
            ];
        }

        $partes = preg_split('/\s+/u', $nombreCompleto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($partes) <= 1) {
            return ['apellido' => $partes[0] ?? '', 'nombre' => ''];
        }

        return [
            'apellido' => $partes[0],
            'nombre' => implode(' ', array_slice($partes, 1)),
        ];
    }

    public static function unirNombre(string $apellido, string $nombre): string
    {
        $apellido = trim($apellido);
        $nombre = trim($nombre);

        if ($apellido === '' && $nombre === '') {
            return '';
        }
        if ($apellido === '') {
            return $nombre;
        }
        if ($nombre === '') {
            return $apellido;
        }

        return $apellido.', '.$nombre;
    }

    /**
     * Primer vínculo con nombre cargado (madre → padre → tutor).
     */
    public static function vinculoPredeterminado(array $responsables): ?string
    {
        foreach (['madre', 'padre', 'tutor'] as $vinculo) {
            if (self::nombrePagador($responsables, $vinculo) !== '') {
                return $vinculo;
            }
        }

        return null;
    }

    /**
     * @return array{apellido: string, nombre: string, dni: string, email: string}
     */
    private static function filaDesdeColumnas(string $nombreCompleto, string $dni, string $email): array
    {
        $partes = self::separarNombre($nombreCompleto);

        return [
            'apellido' => $partes['apellido'],
            'nombre' => $partes['nombre'],
            'dni' => trim($dni),
            'email' => mb_strtolower(trim($email), 'UTF-8'),
        ];
    }

    /**
     * @param  array<string, array{apellido?: string, nombre?: string, dni?: string, email?: string}>  $responsables
     * @return array<string, string>
     */
    private static function payloadLegajo(array $responsables): array
    {
        $mapa = [
            'padre' => ['nombrepad', 'dnipad', 'emailpad'],
            'madre' => ['nombremad', 'dnimad', 'emailmad'],
            'tutor' => ['nombretut', 'dnitut', 'emailtut'],
        ];

        $payload = [];
        foreach ($mapa as $vinculo => [$colNombre, $colDni, $colEmail]) {
            $fila = $responsables[$vinculo] ?? [];
            if (! Schema::hasColumn('legajos', $colNombre)) {
                continue;
            }
            $payload[$colNombre] = self::unirNombre(
                trim((string) ($fila['apellido'] ?? '')),
                trim((string) ($fila['nombre'] ?? '')),
            );
            if (Schema::hasColumn('legajos', $colDni)) {
                $payload[$colDni] = trim((string) ($fila['dni'] ?? ''));
            }
            if (Schema::hasColumn('legajos', $colEmail)) {
                $payload[$colEmail] = mb_strtolower(trim((string) ($fila['email'] ?? '')), 'UTF-8');
            }
        }

        return $payload;
    }
}
