<?php

namespace App\Http\Controllers\Aspirantes;

use App\Http\Controllers\Controller;
use App\Models\Aspiento;
use Illuminate\Http\Request;

/**
 * Resuelve el token de la URL pública y muestra el form de registro
 * o una pantalla amable si la instancia está fuera de ventana / inactiva / inexistente.
 */
class RegistroAspiranteController extends Controller
{
    public function show(Request $request, string $token)
    {
        $instancia = Aspiento::query()->where('token', $token)->first();

        if (! $instancia) {
            return response()
                ->view('aspirantes.publico.no-disponible', [
                    'titulo'  => 'Enlace no válido',
                    'mensaje' => 'El enlace de registro no es correcto. Verificá la URL con el colegio.',
                ], 404);
        }

        if (! $instancia->aceptaRegistros()) {
            return response()->view('aspirantes.publico.no-disponible', [
                'titulo'  => $instancia->activo ? 'Inscripción fuera de fecha' : 'Inscripción cerrada',
                'mensaje' => $this->mensajeFueraDeVentana($instancia),
            ]);
        }

        return view('aspirantes.publico.registro', [
            'instancia' => $instancia,
            'token'     => $token,
        ]);
    }

    protected function mensajeFueraDeVentana(Aspiento $i): string
    {
        $hoy = now()->format('Y-m-d');
        if ($i->fechdesde && $hoy < $i->fechdesde->format('Y-m-d')) {
            return 'La inscripción todavía no comenzó. Reintentá a partir del '.$i->fechdesde->format('d/m/Y').'.';
        }
        if ($i->fechhasta && $hoy > $i->fechhasta->format('Y-m-d')) {
            return 'La inscripción finalizó el '.$i->fechhasta->format('d/m/Y').'. Si necesitás más información, comunicate con el colegio.';
        }

        return 'La inscripción no está disponible en este momento.';
    }
}
