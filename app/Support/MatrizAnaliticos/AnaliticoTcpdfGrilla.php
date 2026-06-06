<?php

namespace App\Support\MatrizAnaliticos;

/**
 * Grilla de calificaciones por año (compartida entre frente y reverso del analítico).
 */
trait AnaliticoTcpdfGrilla
{
    private const GRILLA_FUENTE = 'dejavusans';

    private const GRILLA_ALTURA_FILA = 4.0;

    private const GRILLA_W_MATERIA = 60.0;

    private const GRILLA_W_NUM = 10.0;

    private const GRILLA_W_LETRAS = 25.0;

    private const GRILLA_W_COND = 15.0;

    private const GRILLA_W_MES = 10.0;

    private const GRILLA_W_ANO = 10.0;

    private const GRILLA_W_ESCUELA = 60.0;

    protected function grillaConfigurarFill(): void
    {
        $this->SetFillColor(232, 232, 232);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    protected function grillaDibujarBloqueAnio(float $margenIzq, float $anchoUtil, string $titulo, array $filas): void
    {
        if ($titulo === '') {
            return;
        }

        $this->grillaDibujarEncabezadoAno($margenIzq, $anchoUtil, $titulo);
        $this->SetFont(self::GRILLA_FUENTE, '', 7);

        foreach ($filas as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            if ((string) ($fila['modo'] ?? 'vacio') === 'vacio') {
                $this->grillaFilaVacia();

                continue;
            }

            $this->grillaFilaDatos(
                $this->grillaTruncarMateria((string) ($fila['materia'] ?? '')),
                (string) ($fila['calif_num'] ?? '----'),
                (string) ($fila['calif_letras'] ?? ''),
                (string) ($fila['cond'] ?? '----'),
                (string) ($fila['mes'] ?? '----'),
                (string) ($fila['ano'] ?? '----'),
                (string) ($fila['escuapro'] ?? ''),
            );
        }
    }

    protected function grillaDibujarEncabezadoAno(float $margenIzq, float $anchoUtil, string $titulo): void
    {
        $this->SetFont(self::GRILLA_FUENTE, 'B', 8);
        $this->Cell($anchoUtil - 5, 7, $titulo, 0, 0, 'C');
        $this->Ln(6);

        $y = $this->GetY();
        $this->SetFont(self::GRILLA_FUENTE, '', 6);

        $x = $margenIzq;
        $this->SetXY($x, $y);
        $this->Cell(self::GRILLA_W_MATERIA, 7, 'ESPACIO CURRICULAR', 1, 0, 'C', true);

        $xCal = $x + self::GRILLA_W_MATERIA;
        $this->SetXY($xCal, $y);
        $this->Cell(self::GRILLA_W_NUM + self::GRILLA_W_LETRAS, 3.5, 'CALIFICACIÓN', 1, 2, 'C', true);
        $this->Cell(self::GRILLA_W_NUM, 3.5, 'En num', 1, 0, 'C', true);
        $this->Cell(self::GRILLA_W_LETRAS, 3.5, 'En letras', 1, 0, 'C', true);

        $xCond = $xCal + self::GRILLA_W_NUM + self::GRILLA_W_LETRAS;
        $this->SetXY($xCond, $y);
        $this->Cell(self::GRILLA_W_COND, 7, 'COND', 1, 0, 'C', true);
        $this->Cell(self::GRILLA_W_MES, 7, 'MES', 1, 0, 'C', true);
        $this->Cell(self::GRILLA_W_ANO, 7, 'AÑO', 1, 0, 'C', true);
        $this->Cell(self::GRILLA_W_ESCUELA, 7, 'ESTABLECIMIENTO', 1, 1, 'C', true);

        $this->SetY($y + 7);
    }

    protected function grillaFilaVacia(): void
    {
        $this->Cell(self::GRILLA_W_MATERIA, self::GRILLA_ALTURA_FILA, '-------------------------', 1, 0, 'C');
        $this->Cell(self::GRILLA_W_NUM, self::GRILLA_ALTURA_FILA, '----', 1, 0, 'C');
        $this->Cell(self::GRILLA_W_LETRAS, self::GRILLA_ALTURA_FILA, '------------', 1, 0, 'C');
        $this->Cell(self::GRILLA_W_COND, self::GRILLA_ALTURA_FILA, '----', 1, 0, 'C');
        $this->Cell(self::GRILLA_W_MES, self::GRILLA_ALTURA_FILA, '----', 1, 0, 'C');
        $this->Cell(self::GRILLA_W_ANO, self::GRILLA_ALTURA_FILA, '----', 1, 0, 'C');
        $this->Cell(self::GRILLA_W_ESCUELA, self::GRILLA_ALTURA_FILA, '------------------------------', 1, 1, 'C');
    }

    protected function grillaFilaDatos(
        string $materia,
        string $num,
        string $letras,
        string $cond,
        string $mes,
        string $ano,
        string $escuapro,
    ): void {
        $this->Cell(self::GRILLA_W_MATERIA, self::GRILLA_ALTURA_FILA, $materia, 1, 0, 'L');
        $this->Cell(self::GRILLA_W_NUM, self::GRILLA_ALTURA_FILA, $num, 1, 0, 'C');
        $this->Cell(self::GRILLA_W_LETRAS, self::GRILLA_ALTURA_FILA, $letras, 1, 0, 'C');
        $this->Cell(self::GRILLA_W_COND, self::GRILLA_ALTURA_FILA, $cond, 1, 0, 'C');
        $this->Cell(self::GRILLA_W_MES, self::GRILLA_ALTURA_FILA, $mes, 1, 0, 'C');
        $this->Cell(self::GRILLA_W_ANO, self::GRILLA_ALTURA_FILA, $ano, 1, 0, 'C');
        $this->Cell(self::GRILLA_W_ESCUELA, self::GRILLA_ALTURA_FILA, $this->grillaTruncarEscuapro($escuapro), 1, 1, 'L');
    }

    protected function grillaTruncarMateria(string $materia): string
    {
        if ($materia === '') {
            return '-------------------------';
        }

        return mb_strlen($materia) > 40 ? mb_substr($materia, 0, 40) : $materia;
    }

    protected function grillaTruncarEscuapro(string $texto): string
    {
        if ($texto === '' || str_starts_with($texto, '---')) {
            return $texto !== '' ? $texto : '------------------------------';
        }

        $this->SetFont(self::GRILLA_FUENTE, '', 7);
        $max = self::GRILLA_W_ESCUELA - 2;
        if ($this->GetStringWidth($texto, self::GRILLA_FUENTE, '', 7) <= $max) {
            return $texto;
        }

        $len = mb_strlen($texto);
        for ($i = $len; $i > 0; $i--) {
            $candidato = mb_substr($texto, 0, $i).'…';
            if ($this->GetStringWidth($candidato, self::GRILLA_FUENTE, '', 7) <= $max) {
                return $candidato;
            }
        }

        return mb_substr($texto, 0, 1).'…';
    }
}
