<?php

use App\Http\Middleware\EnsureMenuPortal;
use App\Http\Middleware\EnsureSchoolContext;
use App\Http\Middleware\EnsureStudentContext;
use App\Http\Middleware\ForceHttpsBehindProxy;
use App\Http\Middleware\NoStoreResponse;
use App\Http\Middleware\PwaPortalPrefixRewrite;
use App\Http\Middleware\PwaPortalPrefixSession;
use App\Http\Middleware\RegenerarSesionPostLogin;
use App\Support\Alumnos\SinMatriculaAutogestionException;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->prepend(PwaPortalPrefixRewrite::class);
        $middleware->prependToGroup('web', ForceHttpsBehindProxy::class);
        $middleware->appendToGroup('web', PwaPortalPrefixSession::class);
        $middleware->appendToGroup('web', RegenerarSesionPostLogin::class);

        $middleware->redirectGuestsTo(function (Request $request) {
            $path = trim($request->path(), '/');
            $esPortalAlumno = $path === 'alumnos' || str_starts_with($path, 'alumnos/');

            if (! $request->expectsJson() && $request->hasSession()) {
                $request->session()->flash(
                    'error',
                    $esPortalAlumno
                        ? 'Su sesión expiró. Ingrese nuevamente con su DNI y contraseña.'
                        : 'Debe iniciar sesión para continuar.',
                );
            }

            if ($esPortalAlumno) {
                return se_route_url('alumnos.login');
            }

            $referer = (string) $request->headers->get('referer', '');
            if (
                str_contains($referer, '/alumnos')
                || str_contains($referer, 'loginEstudiante')
                || str_contains($referer, 'pwa-familias')
            ) {
                if ($request->hasSession() && ! $request->session()->has('error')) {
                    $request->session()->flash(
                        'error',
                        'Su sesión expiró. Ingrese nuevamente con su DNI y contraseña.',
                    );
                }

                return se_route_url('alumnos.login');
            }

            if ($request->hasSession()) {
                foreach (array_keys($request->session()->all()) as $key) {
                    $key = (string) $key;
                    if (str_starts_with($key, 'login_alumno_') || str_starts_with($key, 'student.')) {
                        return se_route_url('alumnos.login');
                    }
                }
            }

            return se_route_url('login');
        });

        $middleware->alias([
            'school.context' => EnsureSchoolContext::class,
            'student.context' => EnsureStudentContext::class,
            'login.limpiar-sesion' => \App\Http\Middleware\LimpiarSesionEnPaginaLogin::class,
            'no-store'       => NoStoreResponse::class,
            'permiso'        => \App\Http\Middleware\CheckPermiso::class,
            'permiso-config' => \App\Http\Middleware\CheckPermisoConfiguracion::class,
            'menu.portal'    => EnsureMenuPortal::class,
            'administracion.nivel' => \App\Http\Middleware\EnsureNivelAdministracion::class,
            'autogestion.comunicaciones' => \App\Http\Middleware\EnsureAutogestionComunicaciones::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (SinMatriculaAutogestionException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            // No cerrar sesión: el alumno ya autenticado debe poder volver al menú.
            // Cerrar aquí provocaba “vuelve al login” al abrir un PDF/opción del portal.
            if (auth('alumno')->check()) {
                return redirect()
                    ->to(se_route_url(tenantAutogestionRutaInicio()))
                    ->with('se_swal_error', $e->getMessage())
                    ->with('se_swal_error_titulo', 'Acceso no disponible');
            }

            return redirect()
                ->to(se_route_url('alumnos.login'))
                ->with('se_swal_error', $e->getMessage())
                ->with('se_swal_error_titulo', 'Acceso no disponible');
        });
    })->create();
