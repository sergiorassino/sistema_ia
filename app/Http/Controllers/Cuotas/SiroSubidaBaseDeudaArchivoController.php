<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Models\CuotaGenerada;
use App\Support\Cuotas\Siro\SiroSubidaBaseDeudaArchivo;
use App\Support\Cuotas\Siro\SiroSubidaBaseDeudaProceso;
use App\Support\PermisosCuotas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class SiroSubidaBaseDeudaArchivoController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(PermisosCuotas::puedeSiroSubidaBaseDeuda(), 403);

        $key = 'siro-subida-archivo-dl:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $ids = session('siro_subida_ids', []);
        if (! is_array($ids) || $ids === []) {
            abort(404, 'No hay una subida pendiente. Aplique los filtros nuevamente.');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        if ($ids === []) {
            abort(404);
        }

        $registros = CuotaGenerada::query()
            ->with([
                'legajo:id,apellido,nombre,dni,idFamilias',
                'matricula:id,idLegajos,idTerlec,bloqmatr,bloqadmi',
                'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                'curso.nivel:id,nivel',
                'cuota:id,nombre,idTerlec',
                'cuota.terlec:id,ano',
            ])
            ->whereIn('id', $ids)
            ->where('faltapa', '>', 0)
            ->get();

        if ($registros->isEmpty()) {
            session()->forget(['siro_subida_filtros', 'siro_subida_ids']);
            abort(404, 'No se encontraron registros para procesar.');
        }

        try {
            $resultado = SiroSubidaBaseDeudaProceso::procesar($registros);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        $archivo = $resultado['archivo'];

        if (($archivo['cantidad'] ?? 0) < 1) {
            abort(422, 'Ningún registro pudo incluirse en el archivo SIRO.');
        }

        session()->forget(['siro_subida_filtros', 'siro_subida_ids']);

        $nombre = (string) ($archivo['nombre'] ?? 'siro-base-deuda');
        $contenido = SiroSubidaBaseDeudaArchivo::bytesParaDescarga((string) ($archivo['contenido'] ?? ''));

        $fechaNombre = str_contains($nombre, '.') ? substr($nombre, (int) strrpos($nombre, '.') + 1) : '';
        $fechaCabecera = substr($contenido, 8, 8);
        if ($fechaNombre !== '' && $fechaCabecera !== $fechaNombre) {
            abort(500, 'Inconsistencia interna: la fecha del nombre del archivo no coincide con la cabecera SIRO.');
        }

        $this->limpiarBuffersSalida();

        return response($contenido, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Content-Length' => (string) strlen($contenido),
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function limpiarBuffersSalida(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}
