<?php

namespace App\Support\CalificacionesInicial\Sfq;

/**
 * Catálogo SFQ — carga de informes pedagógicos y Bellas Artes (nivel inicial).
 *
 * Los campos ic01–ic06 almacenan cadenas de dígitos (1 = Totalmente, 2 = Parcialmente, 3 = No está presente),
 * uno por indicador en orden de `indicadores.id` filtrado por sala (`cursos.c`) y etapa.
 */
final class CalificacionesInicialSfqCatalogo
{
    public const IMPLEMENTACION = 'sfq';

    public const MAX_OBS_CARACTERES = 4000;

    public const AREA_PEDAGOGICO = 'pedagogico';

    public const AREA_BELLAS_ARTES = 'bellas_artes';

    public const INFORME_DIAGNOSTICO = 'diagnostico';

    public const INFORME_ETAPA1 = 'etapa1';

    public const INFORME_ETAPA2 = 'etapa2';

    public const INFORME_BELLAS_ARTES = 'bellas_artes';

    /** @var list<string> */
    public const TIPOS_INFORME = [
        self::INFORME_DIAGNOSTICO,
        self::INFORME_ETAPA1,
        self::INFORME_ETAPA2,
        self::INFORME_BELLAS_ARTES,
    ];

    /** Etapas en tabla `indicadores` para informe Bellas Artes (legacy ScriptCase). */
    public const ETAPA_INDICADORES_BA_ADAPTACION = 11;

    public const ETAPA_INDICADORES_BA_PRIMERA = 12;

    public const ETAPA_INDICADORES_BA_SEGUNDA = 13;

    /** @var list<string> */
    public const CAMPOS_IC = ['ic01', 'ic02', 'ic03', 'ic04', 'ic05', 'ic06'];

    /**
     * Orden de columnas en la grilla de carga (observaciones tras Inf. Pedag. 2.º Etapa).
     *
     * @var list<string>
     */
    public const COLUMNAS_GRILLA_CARGA = ['ic01', 'ic02', 'ic03', 'observaciones', 'ic04', 'ic05', 'ic06'];

    public static function esColumnaObservacionesGrilla(string $columna): bool
    {
        return $columna === 'observaciones';
    }

    /** @var list<string> */
    public const CAMPOS_IC_PEDAGOGICO = ['ic01', 'ic02', 'ic03'];

    /** @var list<string> */
    public const CAMPOS_IC_BELLAS_ARTES = ['ic04', 'ic05', 'ic06'];

    public static function esCampoIcPedagogico(string $campo): bool
    {
        return in_array($campo, self::CAMPOS_IC_PEDAGOGICO, true);
    }

    public static function esCampoIcBellasArtes(string $campo): bool
    {
        return in_array($campo, self::CAMPOS_IC_BELLAS_ARTES, true);
    }

    /** @var list<string> */
    public const CAMPOS_OBS_PEDAG = ['obs01', 'obs02', 'obs03'];

    /** @var list<string> */
    public const CAMPOS_OBS_BA = ['baObs01', 'baObs02', 'baObs03'];

    /** @var array<string, string> */
    public const ETIQUETAS_COLUMNA = [
        'ic01' => 'Inf. Pedag. Adaptación',
        'ic02' => 'Inf. Pedag. 1.º Etapa',
        'ic03' => 'Inf. Pedag. 2.º Etapa',
        'observaciones' => 'Inf. Pedag. / Bellas Artes: Observaciones',
        'ic04' => 'Bellas Artes Adaptación',
        'ic05' => 'Bellas Artes 1.º Etapa',
        'ic06' => 'Bellas Artes 2.º Etapa',
    ];

    /** Encabezados de grilla (dos líneas por columna de carga). */
    /** @var array<string, list<string>> */
    public const ENCABEZADOS_COLUMNA = [
        'ic01' => ['Inf. Pedag.', 'Adaptación'],
        'ic02' => ['Inf. Pedag.', '1.º Etapa'],
        'ic03' => ['Inf. Pedag.', '2.º Etapa'],
        'ic04' => ['Bellas Artes', 'Adaptación'],
        'ic05' => ['Bellas Artes', '1.º Etapa'],
        'ic06' => ['Bellas Artes', '2.º Etapa'],
        'observaciones' => ['Inf. Pedag.', 'Bellas Artes', 'Observaciones'],
    ];

    /** @return list<string> */
    public static function encabezadoColumna(string $clave): array
    {
        return self::ENCABEZADOS_COLUMNA[$clave] ?? [self::ETIQUETAS_COLUMNA[$clave] ?? $clave];
    }

    /** Línea individual más ancha entre todos los encabezados ic/obs. */
    public static function lineaEncabezadoMasAncha(): string
    {
        $masAncha = '';
        foreach (self::ENCABEZADOS_COLUMNA as $lineas) {
            foreach ($lineas as $linea) {
                if (mb_strlen($linea) > mb_strlen($masAncha)) {
                    $masAncha = $linea;
                }
            }
        }

        return $masAncha !== '' ? $masAncha : 'Observaciones';
    }

    /**
     * Ancho mínimo base para columnas ic/obs; en pantalla ancha crecen con flex.
     */
    public static function anchoColumnaIconoCss(): string
    {
        return '6.25rem';
    }

    /** @var array<string, string> */
    public const OPCIONES_NOTA = [
        '' => 'Seleccione',
        '1' => 'Totalmente',
        '2' => 'Parcialmente',
        '3' => 'No está presente',
    ];

    public static function esCampoIc(string $campo): bool
    {
        return in_array($campo, self::CAMPOS_IC, true);
    }

    /** @return array{etapa: int, area: string, campoObs: string}|null */
    public static function metaCampoIc(string $campo): ?array
    {
        return match ($campo) {
            'ic01' => ['etapa' => 1, 'area' => self::AREA_PEDAGOGICO, 'campoObs' => 'obs01'],
            'ic02' => ['etapa' => 2, 'area' => self::AREA_PEDAGOGICO, 'campoObs' => 'obs02'],
            'ic03' => ['etapa' => 3, 'area' => self::AREA_PEDAGOGICO, 'campoObs' => 'obs03'],
            'ic04' => ['etapa' => 1, 'area' => self::AREA_BELLAS_ARTES, 'campoObs' => 'baObs01'],
            'ic05' => ['etapa' => 2, 'area' => self::AREA_BELLAS_ARTES, 'campoObs' => 'baObs02'],
            'ic06' => ['etapa' => 3, 'area' => self::AREA_BELLAS_ARTES, 'campoObs' => 'baObs03'],
            default => null,
        };
    }

    public static function etiquetaEtapa(int $etapa): string
    {
        return match ($etapa) {
            2 => 'Primera Etapa',
            3 => 'Segunda Etapa',
            default => 'Adaptación',
        };
    }

    public static function etiquetaEtapaInforme(int $etapa): string
    {
        return match ($etapa) {
            2 => 'Primera Etapa',
            3 => 'Segunda Etapa',
            default => 'Período de Adaptación',
        };
    }

    public static function notaLegible(string $digito): string
    {
        return self::OPCIONES_NOTA[$digito] ?? '';
    }

    public static function digitoValido(string $digito): bool
    {
        return $digito === '' || array_key_exists($digito, self::OPCIONES_NOTA);
    }

    public static function esTipoInformeValido(string $tipo): bool
    {
        return in_array($tipo, self::TIPOS_INFORME, true);
    }

    /**
     * @return array{
     *     variante: string,
     *     etiqueta: string,
     *     etapaPedagogica?: int,
     *     campoIc?: string,
     *     campoObs?: string
     * }|null
     */
    public static function metaTipoInforme(string $tipo): ?array
    {
        return match ($tipo) {
            self::INFORME_DIAGNOSTICO => [
                'variante' => 'pedagogico',
                'etiqueta' => 'Inf. Diagnóstico',
                'etapaPedagogica' => 1,
                'campoIc' => 'ic01',
                'campoObs' => 'obs01',
            ],
            self::INFORME_ETAPA1 => [
                'variante' => 'pedagogico',
                'etiqueta' => 'Inf. 1º Etapa',
                'etapaPedagogica' => 2,
                'campoIc' => 'ic02',
                'campoObs' => 'obs02',
            ],
            self::INFORME_ETAPA2 => [
                'variante' => 'pedagogico',
                'etiqueta' => 'Inf. 2º Etapa',
                'etapaPedagogica' => 3,
                'campoIc' => 'ic03',
                'campoObs' => 'obs03',
            ],
            self::INFORME_BELLAS_ARTES => [
                'variante' => 'bellas_artes',
                'etiqueta' => 'Bellas Artes',
            ],
            default => null,
        };
    }

    /**
     * @return list<array{titulo: string, etapaIndicadores: int, campoIc: string, campoObs: string}>
     */
    public static function seccionesInformeBellasArtes(): array
    {
        return [
            [
                'titulo' => 'PERÍODO DE ADAPTACIÓN',
                'etapaIndicadores' => self::ETAPA_INDICADORES_BA_ADAPTACION,
                'campoIc' => 'ic04',
                'campoObs' => 'baObs01',
            ],
            [
                'titulo' => 'PRIMERA ETAPA',
                'etapaIndicadores' => self::ETAPA_INDICADORES_BA_PRIMERA,
                'campoIc' => 'ic05',
                'campoObs' => 'baObs02',
            ],
            [
                'titulo' => 'SEGUNDA ETAPA',
                'etapaIndicadores' => self::ETAPA_INDICADORES_BA_SEGUNDA,
                'campoIc' => 'ic06',
                'campoObs' => 'baObs03',
            ],
        ];
    }
}
