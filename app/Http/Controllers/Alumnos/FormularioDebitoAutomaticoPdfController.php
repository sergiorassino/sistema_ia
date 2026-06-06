<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Formulario de adhesión a débito automático (PDF estático por tenant).
 */
class FormularioDebitoAutomaticoPdfController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless(tenantAutogestionArancelesEscolaresHabilitada(), 404);

        $relative = config('tenant.autogestion.aranceles_escolares.debito_automatico.formulario_pdf');
        abort_unless(filled($relative), 404);

        $path = resource_path($relative);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="formulario-adhesion-debito-automatico.pdf"',
        ]);
    }
}
