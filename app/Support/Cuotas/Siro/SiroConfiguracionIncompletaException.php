<?php

namespace App\Support\Cuotas\Siro;

/**
 * Faltan parámetros SIRO del nivel en {@see \App\Models\Ento} (sin fallbacks).
 */
final class SiroConfiguracionIncompletaException extends \RuntimeException
{
    /**
     * @param  list<string>  $faltantes  Etiquetas de campos faltantes
     */
    public function __construct(
        public readonly array $faltantes,
        public readonly ?int $idNivel = null,
    ) {
        $lista = implode(', ', $faltantes);
        $nivel = $idNivel !== null && $idNivel > 0 ? " (nivel {$idNivel})" : '';

        parent::__construct(
            'Configuración SIRO incompleta'.$nivel.'. Falta: '.$lista
            .'. Complete Parámetros del sistema → SIRO — medios de pago por nivel.'
        );
    }
}
