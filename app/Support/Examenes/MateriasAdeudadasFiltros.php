<?php

namespace App\Support\Examenes;

use Illuminate\Validation\Rule;

final class MateriasAdeudadasFiltros
{
    public const AGRUPAR_ESTUDIANTE = 'estudiante';

    public const AGRUPAR_MATERIA_CURSO = 'materia_curso';

    /** @var list<string> */
    public const CONDICIONES = ['PR', 'EQ', 'TM'];

    public const INSCRI_SI = 'si';

    public const INSCRI_NO = 'no';

    public static function normalizeAgrupar(?string $value): string
    {
        return $value === self::AGRUPAR_MATERIA_CURSO
            ? self::AGRUPAR_MATERIA_CURSO
            : self::AGRUPAR_ESTUDIANTE;
    }

    public static function normalizeCondicion(?string $value): ?string
    {
        $v = strtoupper(trim((string) $value));

        return in_array($v, self::CONDICIONES, true) ? $v : null;
    }

    public static function normalizeInscri(?string $value): ?string
    {
        $v = strtolower(trim((string) $value));

        return in_array($v, [self::INSCRI_SI, self::INSCRI_NO], true) ? $v : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function reglasValidacionPdf(): array
    {
        return [
            'agrupar' => ['required', 'string', Rule::in([self::AGRUPAR_ESTUDIANTE, self::AGRUPAR_MATERIA_CURSO])],
            'condicion' => ['nullable', 'string', Rule::in(self::CONDICIONES)],
            'inscri' => ['nullable', 'string', Rule::in([self::INSCRI_SI, self::INSCRI_NO])],
        ];
    }

    public static function etiquetaInscri(int $inscri): string
    {
        return match ($inscri) {
            1 => 'Sí',
            2 => 'No',
            default => '—',
        };
    }

    /** Texto del encabezado «Alumnos condición» en actas volantes de examen (previas). */
    public static function tituloCondicionActa(?string $condicion): string
    {
        $cond = strtoupper(trim((string) $condicion));

        return match ($cond) {
            'PR' => 'Previa',
            'EQ' => 'Equivalencias',
            'TM' => 'Regular (Tercer Materia)',
            default => $cond !== '' ? $cond : 'Examen',
        };
    }
}
