<?php

namespace App\Support\Listados;

use App\Models\Curso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exporta el listado «ESTUDIANTES DATOS» (Excel y PDF).
 */
final class EstudiantesDatosExporter
{
    /** @var list<string> */
    public const ENCABEZADOS = [
        'Nº',
        'APELLIDO Y NOMBRES',
        'DNI',
        'CURSO y DIVISIÓN',
        'FECHA NACIMIENTO',
        'DOMICILIO',
        'GRUPO SANGUÍNEO',
        'ADULTO RESP. 1 (MADRE)',
        'ADULTO RESP. 2 (PADRE)',
        'TUTOR',
    ];

    /**
     * @param  list<int>  $matriculaIds
     * @return Collection<int, object>
     */
    public function filas(array $matriculaIds): Collection
    {
        $matriculaIds = EstudiantesDatosConsulta::filtrarMatriculaIdsEnContexto($matriculaIds);

        return $this->filasOrdenadas($matriculaIds);
    }

    /**
     * @param  list<int>  $matriculaIds
     * @return array{spreadsheet: Spreadsheet, filename: string}
     */
    public function buildXlsx(array $matriculaIds): array
    {
        $filas = $this->filas($matriculaIds);

        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Estudiantes');

        $col = 1;
        foreach (self::ENCABEZADOS as $encabezado) {
            $hoja->setCellValue([$col, 1], $encabezado);
            $col++;
        }

        $fila = 2;
        $numero = 1;
        foreach ($filas as $alumno) {
            $col = 1;
            foreach ($this->filaExport($alumno, $numero) as $valor) {
                $hoja->setCellValue([$col, $fila], $valor);
                $col++;
            }
            $fila++;
            $numero++;
        }

        $this->estilizarEncabezado($hoja, count(self::ENCABEZADOS));

        return [
            'spreadsheet' => $spreadsheet,
            'filename' => EstudiantesDatosConsulta::nombreArchivo(),
        ];
    }

    public function escribirEnSalida(Spreadsheet $spreadsheet): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        (new Xlsx($spreadsheet))->save('php://output');
    }

    /**
     * @return list<string|int>
     */
    public function filaExport(object $alumno, int $numero): array
    {
        return [
            $numero,
            EstudiantesDatosConsulta::formatearApellidoNombre(
                (string) ($alumno->apellido ?? ''),
                (string) ($alumno->nombre ?? ''),
            ),
            trim((string) ($alumno->dni ?? '')),
            trim((string) ($alumno->curso_nombre ?? '')),
            EstudiantesDatosConsulta::formatearFechaNacimiento($alumno->fechnaci ?? null),
            (string) ($alumno->domicilio ?? ''),
            trim((string) ($alumno->grupo_sanguineo ?? '')),
            ...EstudiantesDatosConsulta::valoresResponsablesExport($alumno),
        ];
    }

    /**
     * @param  list<int>  $matriculaIds
     * @return Collection<int, object>
     */
    private function filasOrdenadas(array $matriculaIds): Collection
    {
        if ($matriculaIds === []) {
            return collect();
        }

        $ctx = schoolCtx();
        $cursosPorId = EstudiantesDatosConsulta::cursosEnContexto()->keyBy(fn (Curso $c) => (int) $c->Id);

        $select = [
            'matricula.id as matricula_id',
            'matricula.idCursos as id_curso',
            'legajos.apellido',
            'legajos.nombre',
            'legajos.dni',
            'legajos.fechnaci',
            'legajos.callenum',
            'legajos.barrio',
            'legajos.localidad',
            'legajos.nombremad',
            'legajos.dnimad',
            'legajos.telemad',
            'legajos.nombrepad',
            'legajos.dnipad',
            'legajos.telepad',
            'legajos.nombretut',
            'legajos.dnitut',
            'legajos.teletut',
        ];

        $exprGs = EstudiantesDatosConsulta::expresionSqlGrupoSanguineo();
        if ($exprGs !== null) {
            $select[] = DB::raw($exprGs.' as grupo_sanguineo');
        }

        $idsPermitidos = array_flip($matriculaIds);

        $filas = DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->whereIn('matricula.id', $matriculaIds)
            ->where('matricula.idTerlec', (int) $ctx->idTerlec)
            ->where('matricula.idNivel', (int) $ctx->idNivel)
            ->where('matricula.idCondiciones', 1)
            ->whereNull('matricula.fechaBaja')
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->orderBy('matricula.id')
            ->select($select)
            ->get()
            ->filter(fn (object $row) => isset($idsPermitidos[(int) $row->matricula_id]))
            ->map(function (object $row) use ($cursosPorId) {
                /** @var Curso|null $curso */
                $curso = $cursosPorId->get((int) $row->id_curso);
                $row->curso_nombre = $curso?->nombreParaListado() ?? '';
                $row->domicilio = EstudiantesDatosConsulta::formatearDomicilio(
                    (string) ($row->callenum ?? ''),
                    (string) ($row->barrio ?? ''),
                    (string) ($row->localidad ?? ''),
                );
                if (! property_exists($row, 'grupo_sanguineo')) {
                    $row->grupo_sanguineo = '';
                }

                return $row;
            });

        return EstudiantesDatosConsulta::ordenarFilasAlfabeticamente($filas);
    }

    private function estilizarEncabezado(Worksheet $hoja, int $totalColumnas): void
    {
        $ultimaCol = $this->indiceColumnaExcel($totalColumnas);
        $hoja->getStyle('A1:'.$ultimaCol.'1')->getFont()->setBold(true);
        for ($c = 1; $c <= $totalColumnas; $c++) {
            $hoja->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
        $hoja->freezePane('A2');
    }

    private function indiceColumnaExcel(int $numeroColumna): string
    {
        $letras = '';
        $n = $numeroColumna;
        while ($n > 0) {
            $n--;
            $letras = chr(65 + ($n % 26)).$letras;
            $n = intdiv($n, 26);
        }

        return $letras;
    }
}
