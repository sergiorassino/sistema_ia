<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Favicon para /favicon.ico: `1.png` (tema claro), `2.png` (tema oscuro).
 */
class InstitutionalIconController extends Controller
{
    public function __invoke(Request $request): Response|BinaryFileResponse
    {
        $preferDark = $request->header('Sec-CH-Prefers-Color-Scheme') === 'dark'
            || (is_string($request->query('theme')) && $request->query('theme') === 'dark');

        $filename = $preferDark ? '2.png' : '1.png';

        return response()->file(public_path('img/'.$filename), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600, must-revalidate',
        ]);
    }
}
