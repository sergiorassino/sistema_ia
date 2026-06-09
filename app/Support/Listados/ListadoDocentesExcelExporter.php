<?php

namespace App\Support\Listados;

use App\Models\CampoProfesor;
use App\Models\EstadoCivil;
use App\Models\ProfesorTipo;
use App\Models\Sexo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exporta docentes del nivel activo a Excel: un único listado por orden alfabético.
 */
final class ListadoDocentesExcelExporter
{
    /** @var array<string, true> */
    private array $nombresHojaUsados = [];

    /**
     * @return array{spreadsheet: Spreadsheet, filename: string}
     */
    public function build(?int $anoLectivo, ListadoDocentesExcelExportSpec $spec): array
    {
        $this->nombresHojaUsados = [];

        if ($spec->campoKeys === null) {
            $campos = CampoProfesor::aplicarVisibilidadListadoPdf(
                ListadoDocentesPdfFieldCatalog::keysOrdenadosExportPorSolapas()
            );
        } else {
            $campos = $spec->campoKeys;
        }

        $columnasMeta = ListadoDocentesPdfFieldCatalog::columnsForPdf($campos);

        $todosLosRoles = ListadoDocentesConsulta::rolesDisponibles();

        if ($spec->roleIds !== null) {
            $idsPermitidos = array_flip($spec->roleIds);
            $roles = $todosLosRoles
                ->filter(fn (ProfesorTipo $r) => isset($idsPermitidos[(int) $r->id]))
                ->values();
        } else {
            $roles = $todosLosRoles;
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        if ($roles->isEmpty() || $columnasMeta === []) {
            $hoja = $spreadsheet->createSheet();
            $hoja->setTitle($this->nombreHojaUnico('Sin datos'));
            $hoja->setCellValue('A1', 'No hay roles o columnas configuradas para exportar.');

            return [
                'spreadsheet' => $spreadsheet,
                'filename' => $this->nombreArchivo($anoLectivo),
            ];
        }

        $roleIds = $roles->pluck('id')->map(fn ($id) => (int) $id)->all();
        $docentes = $this->consultarDocentes($roleIds, $campos);
        $this->poblarHojaListado($spreadsheet, $docentes, $columnasMeta);

        $spreadsheet->setActiveSheetIndex(0);

        return [
            'spreadsheet' => $spreadsheet,
            'filename' => $this->nombreArchivo($anoLectivo),
        ];
    }

    /**
     * @param  list<int>  $roleIds
     * @param  list<string>  $campos
     * @return Collection<int, object>
     */
    private function consultarDocentes(array $roleIds, array $campos): Collection
    {
        $idNivel = ListadoDocentesConsulta::idNivelLegajos();
        if ($idNivel < 1 || $roleIds === []) {
            return collect();
        }

        $select = array_merge(
            ['profesores.id as __id_profesor', 'profesores.IdTipoProf as __id_tipo_prof'],
            ListadoDocentesPdfFieldCatalog::selectExpressions($campos)
        );

        $query = DB::table('profesores')
            ->where('profesores.nivel', $idNivel)
            ->where(function ($w) use ($roleIds) {
                $w->whereIn('profesores.IdTipoProf', $roleIds);
                if (ListadoDocentesConsulta::incluyeSinRolEnRoles($roleIds)) {
                    $w->orWhereNull('profesores.IdTipoProf');
                }
            })
            ->orderBy('profesores.apellido')
            ->orderBy('profesores.nombre');

        if (ListadoDocentesPdfFieldCatalog::needsProfesorTipoJoin($campos)) {
            $query->leftJoin('profesortipo', 'profesortipo.id', '=', 'profesores.IdTipoProf');
        }

        return $query->select($select)->get();
    }

    public function nombreArchivo(?int $anoLectivo): string
    {
        $ano = $anoLectivo ?? (int) date('Y');

        return 'Docentes'.$ano.'.xlsx';
    }

    /**
     * @param  Collection<int, object>  $docentes
     * @param  list<array{key: string, label: string, alias: string}>  $columnasMeta
     */
    private function poblarHojaListado(
        Spreadsheet $spreadsheet,
        Collection $docentes,
        array $columnasMeta
    ): void {
        $hoja = $spreadsheet->createSheet();
        $this->asignarTituloHoja($hoja, 'Docentes');

        $col = 1;
        $hoja->setCellValue([$col, 1], 'Nº');
        $col++;
        foreach ($columnasMeta as $meta) {
            $hoja->setCellValue([$col, 1], $meta['label']);
            $col++;
        }

        $fila = 2;
        $num = 1;
        foreach ($docentes as $docente) {
            $col = 1;
            $hoja->setCellValue([$col, $fila], $num);
            $col++;
            foreach ($columnasMeta as $meta) {
                $valor = $this->valorCeldaDocente($docente, $meta);
                $hoja->setCellValue([$col, $fila], $valor);
                $col++;
            }
            $fila++;
            $num++;
        }

        $this->estilizarEncabezado($hoja, count($columnasMeta) + 1);
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

    /**
     * @param  array{key: string, label: string, alias: string}  $meta
     */
    private function valorCeldaDocente(object $docente, array $meta): string|int|float
    {
        if ($meta['key'] === ListadoDocentesPdfFieldCatalog::KEY_APELLIDO_NOMBRE) {
            return ListadoDocentesPdfFieldCatalog::valorApellidoNombre($docente, false);
        }

        if ($meta['key'] === 'profesores.IdTipoProf') {
            $tipo = trim((string) ($docente->profesortipo_tipo ?? ''));

            return $tipo !== '' ? $tipo : '';
        }

        return $this->formatearValorCelda($docente->{$meta['alias']} ?? null, $meta['key']);
    }

    private function formatearValorCelda(mixed $valor, string $catalogKey): string|int|float
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if ($catalogKey === 'profesores.sexo') {
            return Sexo::etiquetaParaValorAlmacenado($valor);
        }

        if ($catalogKey === 'profesores.estacivi') {
            return EstadoCivil::etiquetaParaValorAlmacenado($valor);
        }

        if ($this->esCampoFecha($catalogKey) && $this->pareceFecha($valor)) {
            try {
                return Carbon::parse((string) $valor)->format('d/m/Y');
            } catch (\Throwable) {
                return (string) $valor;
            }
        }

        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        if (is_int($valor) || is_float($valor)) {
            return $valor;
        }

        return $this->sanitizarTextoCelda((string) $valor);
    }

    private function asignarTituloHoja(Worksheet $hoja, string $nombreRol): void
    {
        $candidatos = [
            $this->nombreHojaUnico($nombreRol),
            $this->nombreHojaUnico('Rol'),
        ];

        foreach ($candidatos as $titulo) {
            try {
                $hoja->setTitle($titulo);

                return;
            } catch (\Throwable) {
                continue;
            }
        }
    }

    private function sanitizarTextoCelda(string $texto): string
    {
        $limpio = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texto);

        return $limpio ?? '';
    }

    private function esCampoFecha(string $catalogKey): bool
    {
        $needle = strtolower($catalogKey);

        return str_contains($needle, 'fech') || str_contains($needle, 'apto') || str_contains($needle, 'escalafon');
    }

    private function pareceFecha(mixed $valor): bool
    {
        if ($valor instanceof \DateTimeInterface) {
            return true;
        }

        $s = trim((string) $valor);

        return $s !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $s) === 1;
    }

    private function nombreHojaUnico(string $base): string
    {
        $limpio = preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', $base) ?? 'Rol';
        $limpio = trim(preg_replace('/\s+/', ' ', $limpio) ?? 'Rol');
        if ($limpio === '') {
            $limpio = 'Rol';
        }
        $limpio = Str::limit($limpio, 28, '');

        $nombre = $limpio;
        $sufijo = 2;
        while (isset($this->nombresHojaUsados[$nombre])) {
            $sufijoStr = ' ('.$sufijo.')';
            $nombre = Str::limit($limpio, 31 - strlen($sufijoStr), '').$sufijoStr;
            $sufijo++;
        }
        $this->nombresHojaUsados[$nombre] = true;

        return $nombre;
    }

    public function escribirEnSalida(Spreadsheet $spreadsheet): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        (new Xlsx($spreadsheet))->save('php://output');
    }
}
