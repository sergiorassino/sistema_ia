<?php

namespace App\Support\Auth;

use App\Models\Legajo;
use App\Models\Profesor;
use Illuminate\Contracts\Auth\Authenticatable;

class ActualizarContraseñaUsuario
{
    public static function aplicar(Authenticatable $user, string $guard, string $nuevaPlain): bool
    {
        $nuevaPlain = trim($nuevaPlain);
        if ($nuevaPlain === '') {
            return false;
        }

        $id = $user->getAuthIdentifier();

        if ($guard === 'alumno') {
            $legajo = Legajo::find($id);
            if (! $legajo) {
                return false;
            }
            $legajo->pwrd = $nuevaPlain;

            return $legajo->save();
        }

        $profesor = Profesor::find($id);
        if (! $profesor) {
            return false;
        }
        $profesor->pwrd = $nuevaPlain;

        return $profesor->save();
    }
}
