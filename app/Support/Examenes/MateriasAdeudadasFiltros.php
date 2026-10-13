<?php

namespace App\Support\Examenes;

use Illuminate\Validation\Rule;

final class MateriasAdeudadasFiltros
{
    public const AGRUPAR_ESTUDIANTE = 'estudiante';

    public const AGRUPAR_MATERIA_CURSO = 'materia_curso';

    /** Solo alumnos con matrícula regular en el ciclo lectivo del contexto. */
    public const ALUMNOS_REGULARES_CICLO = 'regulares';

    /** Todos los legajos con adeudos (incluye egresados / años anteriores). */
    public const ALUMNOS_TODOS = 'todos';

    /** @var list<string> */
    public const CONDICIONES = ['PR', 'RE', 'EQ', 'TM'];

    public const INSCRI_SI = 'si';

    public const INSCRI_NO = 'no';

    /** `matricula.idCondiciones` de un alumno regular en el curso de la materia. */
    public const ID_CONDICION_REGULAR = 1;

    public static function normalizeAgrupar(?string $value): string
    {
        return $value === self::AGRUPAR_MATERIA_CURSO
            ? self::AGRUPAR_MATERIA_CURSO
            : self::AGRUPAR_ESTUDIANTE;
    }

    public static function normalizeAlumnos(?string $value): string
    {
        return $value === self::ALUMNOS_TODOS
            ? self::ALUMNOS_TODOS
            : self::ALUMNOS_REGULARES_CICLO;
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
            'alumnos' => ['nullable', 'string', Rule::in([self::ALUMNOS_REGULARES_CICLO, self::ALUMNOS_TODOS])],
            'condicion' => ['nullable', 'string', Rule::in(self::CONDICIONES)],
            'inscri' => ['nullable', 'string', Rule::in([self::INSCRI_SI, self::INSCRI_NO])],
        ];
    }

    public static function etiquetaAlumnos(string $alumnos): string
    {
        return self::normalizeAlumnos($alumnos) === self::ALUMNOS_TODOS
            ? 'Todos los alumnos (historial)'
            : 'Regulares del ciclo actual';
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
            'RE' => 'Regular',
            'EQ' => 'Equivalencias',
            'TM' => 'Regular (Tercer Materia)',
            default => $cond !== '' ? $cond : 'Examen',
        };
    }

    /**
     * La materia cuenta como previa solo si la matrícula de esa calificación
     * (el curso de la materia) es regular.
     */
    public static function matriculaDelCursoCuentaComoPrevia(int $idCondiciones): bool
    {
        return $idCondiciones === self::ID_CONDICION_REGULAR;
    }

    /**
     * Restringe calificaciones a las cuya matrícula (`idMatricula`) es regular.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarMatriculaRegularDelCurso($query, string $aliasCalif = 'c'): void
    {
        $query->whereExists(function ($sub) use ($aliasCalif) {
            $sub->selectRaw('1')
                ->from('matricula as mat_reg')
                ->whereColumn('mat_reg.id', $aliasCalif.'.idMatricula')
                ->where('mat_reg.idCondiciones', self::ID_CONDICION_REGULAR);
        });
    }

    /** Condición de examen (PR/RE/EQ/LI/TM) → valor persistido en `calificaciones.cond` al aprobar. */
    public static function condCalificacionDesdeExamen(?string $condExamen): string
    {
        $codigo = strtoupper(trim((string) $condExamen));

        return match ($codigo) {
            'PR' => 'Prev.',
            'RE' => 'Regular',
            'EQ' => 'Equiv.',
            'LI' => 'Libre',
            'TM' => 'Regular',
            default => 'Regular',
        };
    }
}
