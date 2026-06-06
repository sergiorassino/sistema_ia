<?php

namespace App\Services\SincroDesempenos;

/**
 * Resultado estructurado de una importación de desempeños (primario).
 */
final class DesempenosCsvImportResult
{
    /**
     * @param  list<array{line: int, code: string, message: string, detail?: string}>  $issues
     */
    public function __construct(
        public readonly int $totalDataRows,
        public readonly int $updatedRows,
        public readonly int $skippedRows,
        public readonly bool $committed,
        public readonly int $etapa,
        public readonly array $issues = [],
        public readonly bool $issuesTruncated = false,
    ) {}

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }

    public function successMessage(): string
    {
        $etapaLabel = $this->etapa === 1 ? 'Primera etapa' : 'Segunda etapa';

        if (! $this->committed) {
            return "No se guardó ningún cambio en la base de datos ({$etapaLabel}).";
        }

        if ($this->updatedRows === 0) {
            return "El archivo se procesó ({$etapaLabel}), pero no se actualizó ningún registro de matrícula.";
        }

        $msg = "Importación completada ({$etapaLabel}): {$this->updatedRows} matrícula(s) actualizada(s) de {$this->totalDataRows} fila(s) del archivo.";

        if ($this->skippedRows > 0 || $this->hasIssues()) {
            $msg .= ' Revise el detalle de advertencias y errores abajo.';
        }

        return $msg;
    }
}
