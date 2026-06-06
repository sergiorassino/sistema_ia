<?php

namespace App\Support\Examenes;

use Illuminate\Support\Str;

/**
 * Parámetros del PDF en sesión (evita URLs enormes al seleccionar muchos alumnos).
 */
final class PermisoExamenPdfPedido
{
    private const SESSION_KEY = 'permiso_examen_pdf_pedido';

    /**
     * @param  list<int>  $idsAlumnos
     */
    public static function guardar(int $idNivel, array $idsAlumnos, int $numeroPermisoInicio, string $fechaYmd): string
    {
        $token = Str::random(48);
        $idsAlumnos = collect($idsAlumnos)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        session([
            self::SESSION_KEY => [
                'token' => $token,
                'idNivel' => $idNivel,
                'ids' => $idsAlumnos,
                'numero' => max(1, $numeroPermisoInicio),
                'fecha' => $fechaYmd,
                'expira' => now()->addMinutes(30)->timestamp,
            ],
        ]);

        return $token;
    }

    /**
     * @return array{ids: list<int>, numero: int, fecha: string}|null
     */
    public static function consumir(string $token, int $idNivel): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $data = session(self::SESSION_KEY);
        if (! is_array($data)
            || ($data['token'] ?? '') !== $token
            || (int) ($data['idNivel'] ?? 0) !== $idNivel
            || (int) ($data['expira'] ?? 0) < now()->timestamp) {
            return null;
        }

        session()->forget(self::SESSION_KEY);

        $ids = $data['ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return null;
        }

        $fecha = trim((string) ($data['fecha'] ?? ''));
        if ($fecha === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return null;
        }

        return [
            'ids' => array_values(array_map('intval', $ids)),
            'numero' => max(1, (int) ($data['numero'] ?? 1)),
            'fecha' => $fecha,
        ];
    }
}
