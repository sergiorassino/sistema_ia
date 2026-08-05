<?php

namespace App\Support\Alumnos;

use RuntimeException;

/**
 * El estudiante autenticado no tiene matrícula usable en el ciclo de autogestión
 * (p. ej. egresado sin matrícula en idTerlecVerNotas).
 */
class SinMatriculaAutogestionException extends RuntimeException
{
    public const MENSAJE = 'No hay matrícula registrada para este ciclo lectivo. Contacte a secretaría.';

    public function __construct(string $message = self::MENSAJE)
    {
        parent::__construct($message);
    }
}
