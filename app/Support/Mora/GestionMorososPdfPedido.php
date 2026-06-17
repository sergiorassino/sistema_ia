<?php

namespace App\Support\Mora;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Filtros del PDF de gestión de morosos en caché (evita URLs enormes y fallos de sesión al abrir en otra pestaña).
 */
final class GestionMorososPdfPedido
{
    private const TTL_MINUTES = 30;

    public const TIPO_LISTADO = 'listado';

    public const TIPO_NOTIFICACION = 'notificacion';

    /**
     * @param  array<string, mixed>  $filtros  Normalizados con {@see GestionMorososFiltros::normalizarDesdeLivewire}
     */
    public static function guardar(array $filtros, string $tipo): string
    {
        $token = Str::random(48);
        $idProfesor = (int) schoolCtx()->idProfesor;

        Cache::put(self::cacheKey($token), [
            'tipo' => $tipo,
            'filtros' => $filtros,
            'idProfesor' => $idProfesor,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /**
     * @return array<string, mixed>|null  Filtros normalizados
     */
    public static function leer(string $token, string $tipo): ?array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 64) {
            return null;
        }

        $data = Cache::get(self::cacheKey($token));
        if (! is_array($data)
            || ($data['tipo'] ?? '') !== $tipo
            || (int) ($data['idProfesor'] ?? 0) !== (int) schoolCtx()->idProfesor) {
            return null;
        }

        $filtros = $data['filtros'] ?? null;

        return is_array($filtros) ? $filtros : null;
    }

    private static function cacheKey(string $token): string
    {
        return tenantSlug().':mora_gestion_morosos_pdf:'.$token;
    }
}
