<?php

namespace App\Http\Controllers\Navegacion;

use App\Http\Controllers\Controller;
use App\Support\Navegacion\ContextoEstudianteSesion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class EstablecerContextoEstudianteController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'destino' => ['required', 'string', 'max:120'],
            'alcance' => ['required', 'string', 'max:80'],
            'matricula' => ['nullable', 'integer', 'min:1'],
            'curso' => ['nullable', 'integer', 'min:1'],
            'idLegajos' => ['nullable', 'integer', 'min:1'],
            'idCuotaGenerada' => ['nullable', 'integer', 'min:1'],
            'materia' => ['nullable', 'integer', 'min:1'],
            'tipo' => ['nullable'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'abrir_matriculas' => ['nullable', 'boolean'],
            'buscar' => ['nullable', 'string', 'max:120'],
        ]);

        $destino = (string) $validated['destino'];
        abort_unless(Route::has($destino), 404);

        $datosContexto = [
            'matricula' => isset($validated['matricula']) ? (int) $validated['matricula'] : null,
            'curso' => isset($validated['curso']) ? (int) $validated['curso'] : null,
            'idLegajos' => isset($validated['idLegajos']) ? (int) $validated['idLegajos'] : null,
            'materia' => isset($validated['materia']) ? (int) $validated['materia'] : null,
            'tipo' => $validated['tipo'] ?? null,
            'desde' => $validated['desde'] ?? null,
            'hasta' => $validated['hasta'] ?? null,
        ];

        if (isset($validated['idCuotaGenerada'])) {
            $datosContexto['idCuotaGenerada'] = (int) $validated['idCuotaGenerada'];
        } elseif (in_array($destino, ['cuotas.estudiante', 'cuotas.estudiante.generar'], true)) {
            $datosContexto['idCuotaGenerada'] = 0;
        }

        ContextoEstudianteSesion::fijar((string) $validated['alcance'], $datosContexto);

        if ($request->boolean('abrir_matriculas')) {
            session()->flash('legajo_abrir_matriculas', true);
        }

        $buscarListado = trim((string) ($validated['buscar'] ?? ''));
        if ($buscarListado !== '') {
            \App\Support\MatrizAnaliticos\LibroMatrizAnalitico::persistirBuscarListado($buscarListado);
        }

        $parametrosRuta = match ($destino) {
            'portalDocente.cuadernoSeguimiento.alumno' => array_filter([
                'curso' => (int) ($validated['curso'] ?? 0),
                'materia' => (int) ($validated['materia'] ?? 0),
            ], fn ($v) => $v > 0),
            'matrizAnaliticos.libroMatriz.editar', 'matrizAnaliticos.libroMatriz.datosAdicionales' => \App\Support\MatrizAnaliticos\LibroMatrizAnalitico::queryFiltroListado(
                $buscarListado !== '' ? $buscarListado : null,
            ),
            default => [],
        };

        return redirect()->route($destino, $parametrosRuta);
    }
}
