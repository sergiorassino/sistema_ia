<?php

namespace App\Support\Auth;

enum RecuperacionContrasenaOrigen: string
{
    case Profesor = 'profesor';
    case Alumno = 'alumno';
}
