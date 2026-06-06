<?php

namespace App\Push;

use Illuminate\Support\Facades\Auth;

final class PushUserKey
{
    public static function forAuthenticatedUser(): string
    {
        if (Auth::guard('alumno')->check()) {
            return (string) Auth::guard('alumno')->id();
        }

        $idProfesor = schoolCtx()->idProfesor ?? null;
        if ($idProfesor) {
            return 'p:'.$idProfesor;
        }

        abort(401);
    }
}
