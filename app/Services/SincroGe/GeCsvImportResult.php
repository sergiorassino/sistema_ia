<?php

namespace App\Services\SincroGe;

/**
 * Resultado estructurado de una importación GE/CIDI.
 */
final class GeCsvImportResult
{
    /**
     * @param  list<array{line: int, code: string, message: string, detail?: string}>  $issues
     */
    public function __construct(
        public readonly int $totalDataRows,
        public readonly int $updatedRows,
        public readonly int $skippedRows,
        public readonly bool $committed,
        public readonly array $issues = [],
        public readonly bool $issuesTruncated = false,
    ) {}

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }

    public function successMessage(): string
    {
        if (! $this->committed) {
            if ($this->hasIssues()) {
                return 'No se guardó ningún cambio. Hay errores en el archivo; revise el detalle abajo.';
            }

            return 'No se guardó ningún cambio en la base de datos.';
        }

        if ($this->updatedRows === 0) {
            return 'El archivo se procesó, pero no se actualizó ninguna calificación.';
        }

        if ($this->skippedRows > 0 || $this->hasIssues()) {
            return "Importación parcial: {$this->updatedRows} registro(s) actualizado(s) de {$this->totalDataRows} fila(s); "
                ."{$this->skippedRows} fila(s) con error no se grabaron. Revise el detalle abajo.";
        }

        return "Importación completada: {$this->updatedRows} registro(s) actualizado(s) de {$this->totalDataRows} fila(s) del archivo.";
    }
}
