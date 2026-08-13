<?php

namespace App\Support;

final class ProfesorLegajoFieldCatalog
{
    /** @var array<string, string> */
    private const LABELS = [
        'cuil' => 'CUIL',
        'sexo' => 'Sexo',
        'email' => 'Email personal',
        'emailInsti' => 'Email institucional',
        'callenum' => 'Calle y número',
        'barrio' => 'Barrio',
        'telefono' => 'Teléfono',
        'celular' => 'Celular',
        'nacion' => 'Nacionalidad',
        'estacivi' => 'Estado civil',
        'legJunta' => 'Legajo Junta',
        'legEscuela' => 'Legajo escuela',
        'fechnaci' => 'Fecha de nacimiento',
        'titulo' => 'Título',
        'numreg' => 'Nº registro',
        'apto' => 'Apto médico',
        'incapac' => 'Incapacidad',
        'art28' => 'Art. 28',
        'fichaIncompat' => 'Ficha incompatibilidad',
        'escalafonD' => 'Escalafón docente',
        'escalafonE' => 'Escalafón estatutario',
        'cargo' => 'Cargo',
        'obs' => 'Observaciones',
        'IdTipoProf' => 'Rol',
    ];

    public static function label(string $columna): string
    {
        if (isset(self::LABELS[$columna])) {
            return self::LABELS[$columna];
        }

        return ucfirst(str_replace('_', ' ', $columna));
    }
}
