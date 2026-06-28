<?php

namespace App\Support\CalificacionesPrimario\Epq;

/**
 * Campos y reglas del módulo de calificaciones primario EPQ (Escuelas Pías Quimilí).
 */
final class CalificacionesEpqCatalogo
{
    public const IMPLEMENTACION = 'epq';

    /** @var list<string> */
    public const CAMPOS_NOTA = ['ic01', 'ic02', 'ic03', 'ic04', 'ic05', 'ic06', 'ic07'];

    /** Columnas destacadas (promedio final y calificación definitiva). */
    public const CAMPO_PROM_FINAL = 'ic04';

    public const CAMPO_CALIF_DEF = 'ic07';

    /** @var list<string> */
    public const CAMPOS_INFO_NUMERICOS = [
        'md01', 'md02', 'md03', 'md04',
        'md05', 'md06', 'md07', 'md08',
        'md09', 'md10', 'md11', 'md12',
        'md13', 'md14', 'md15', 'md16',
    ];

    /** @var list<string> */
    public const CAMPOS_HABILIDADES_INTELECTUALES = [
        'md17', 'md18', 'md19', 'md20', 'md21', 'md22', 'md23', 'md24', 'md25',
    ];

    /** @var list<string> */
    public const CAMPOS_HABILIDADES_SOCIALES = [
        'md26', 'md27', 'md28', 'md29', 'md30', 'md31', 'md32', 'md33', 'md34', 'md35', 'md36', 'md37',
    ];

    /** @return list<string> */
    public static function camposInfoAdicional(): array
    {
        return array_merge(
            self::CAMPOS_INFO_NUMERICOS,
            self::CAMPOS_HABILIDADES_INTELECTUALES,
            self::CAMPOS_HABILIDADES_SOCIALES,
        );
    }

    public static function etiquetaCampoNota(string $campo): string
    {
        return match ($campo) {
            'ic01' => '1º Trim',
            'ic02' => '2º Trim',
            'ic03' => '3º Trim',
            'ic04' => 'Prom. Final',
            'ic05' => 'Per. Dic.',
            'ic06' => 'Per. Feb.',
            'ic07' => 'Calif. Definit.',
            default => $campo,
        };
    }

    public static function esCampoDestacado(string $campo): bool
    {
        return in_array($campo, [self::CAMPO_PROM_FINAL, self::CAMPO_CALIF_DEF], true);
    }
}
