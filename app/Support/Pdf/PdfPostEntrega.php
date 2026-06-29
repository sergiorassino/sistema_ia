<?php

namespace App\Support\Pdf;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Entrega temporal de PDFs generados por POST: redirige a GET con token opaco.
 * Permite vista en pestaña y descarga con nombre de archivo correcto.
 */
final class PdfPostEntrega
{
    private const TTL_MINUTOS = 10;

    public static function guardar(string $binario, string $nombreArchivo): string
    {
        $userId = (int) (auth()->id() ?? 0);
        if ($userId < 1) {
            abort(403);
        }

        $token = Str::random(64);
        Cache::put(
            self::cacheKey($userId, $token),
            [
                'binario' => $binario,
                'nombre' => $nombreArchivo,
            ],
            now()->addMinutes(self::TTL_MINUTOS),
        );

        return $token;
    }

    public static function respuesta(string $binario, string $nombreArchivo, Request $request): JsonResponse|RedirectResponse
    {
        $token = self::guardar($binario, $nombreArchivo);
        $url = self::urlVer($token);

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['url' => $url]);
        }

        return redirect($url);
    }

    /**
     * @return array{binario: string, nombre: string}|null
     */
    public static function recuperar(string $token): ?array
    {
        $userId = (int) (auth()->id() ?? 0);
        if ($userId < 1 || ! self::tokenValido($token)) {
            return null;
        }

        $data = Cache::get(self::cacheKey($userId, $token));
        if (! is_array($data)) {
            return null;
        }

        $binario = $data['binario'] ?? null;
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if (! is_string($binario) || $binario === '' || $nombre === '') {
            return null;
        }

        return [
            'binario' => $binario,
            'nombre' => $nombre,
        ];
    }

    public static function urlVer(string $token): string
    {
        return route('pdfPost.ver', ['token' => $token]);
    }

    private static function cacheKey(int $userId, string $token): string
    {
        return 'pdf-post:'.$userId.':'.hash('sha256', $token);
    }

    private static function tokenValido(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{32,128}$/', $token);
    }
}
