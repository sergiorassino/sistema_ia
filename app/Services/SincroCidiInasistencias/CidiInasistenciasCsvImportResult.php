<?php

namespace App\Services\SincroCidiInasistencias;

/**
 * Resultado estructurado de una importación de inasistencias desde CIDI/GE.
 */
final class CidiInasistenciasCsvImportResult
{
    /**
     * @param  list<array{line: int, code: string, message: string, detail?: string}>  $issues
     */
    public function __construct(
        public readonly int $totalDataRows,
        public readonly int $insertedRows,
        public readonly int $updatedRows,
        public readonly int $skippedRows,
        public readonly int $skippedPresenteRows,
        public readonly int $skippedSinCambioRows,
        public readonly bool $committed,
        public readonly array $issues = [],
        public readonly bool $issuesTruncated = false,
    ) {}

    public function filasModificadas(): int
    {
        return $this->insertedRows + $this->updatedRows;
    }

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }

    public function successMessage(): string
    {
        if (! $this->committed) {
            return 'No se guardó ningún cambio en la base de datos.';
        }

        if ($this->filasModificadas() === 0) {
            $msg = 'El archivo se procesó sin cambios en la base de datos.';
            if ($this->skippedSinCambioRows > 0) {
                $msg .= " {$this->skippedSinCambioRows} fila(s) ya coincidían con CIDI.";
            }

            return $msg;
        }

        $partes = [];
        if ($this->insertedRows > 0) {
            $partes[] = "{$this->insertedRows} nueva(s)";
        }
        if ($this->updatedRows > 0) {
            $partes[] = "{$this->updatedRows} actualizada(s)";
        }

        $msg = 'Importación completada: '.implode(', ', $partes).'.';
        $msg .= " Se leyeron {$this->totalDataRows} fila(s) con novedad (sin contar presentes).";

        if ($this->skippedPresenteRows > 0) {
            $msg .= " Se omitieron {$this->skippedPresenteRows} fila(s) de tipo PRESENTE.";
        }

        if ($this->skippedSinCambioRows > 0) {
            $msg .= " {$this->skippedSinCambioRows} fila(s) ya estaban al día.";
        }

        if ($this->skippedRows > 0 || $this->hasIssues()) {
            $msg .= ' Revise el detalle de advertencias y errores abajo.';
        }

        return $msg;
    }
}
