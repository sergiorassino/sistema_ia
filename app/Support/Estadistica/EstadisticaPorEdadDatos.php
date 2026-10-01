<?php

namespace App\Support\Estadistica;

use App\Models\Curso;
use App\Support\NivelSistema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Estadística de alumnos regulares por edad cumplida, por curso del nivel de sesión.
 */
final class EstadisticaPorEdadDatos
{
    public const MENOS_DE_3 = 'lt3';

    public const DE_20_A_25 = '20_25';

    public const MAS_DE_25 = 'gt25';

    public const SIN_FECHA = 'sin_fecha';

    public const TOTAL = 'total';

    /**
     * Filas fijas de la planilla (más el total, que se agrega al armar).
     *
     * @return list<array{clave: string, etiqueta: string}>
     */
    public static function filasDefinicion(): array
    {
        $filas = [
            ['clave' => self::MENOS_DE_3, 'etiqueta' => 'Menos de 3 años'],
        ];

        for ($edad = 3; $edad <= 19; $edad++) {
            $filas[] = ['clave' => (string) $edad, 'etiqueta' => $edad.' años'];
        }

        $filas[] = ['clave' => self::DE_20_A_25, 'etiqueta' => '20 a 25 años'];
        $filas[] = ['clave' => self::MAS_DE_25, 'etiqueta' => 'Más de 25 años'];
        $filas[] = ['clave' => self::SIN_FECHA, 'etiqueta' => 'Sin fecha de nacimiento'];

        return $filas;
    }

    public static function parseFecha(mixed $valor): ?Carbon
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        try {
            $fecha = Carbon::parse($texto)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $anio = (int) $fecha->format('Y');
        if ($anio < 1990 || $anio > 2100) {
            return null;
        }

        return $fecha;
    }

    /**
     * Clave de fila según la edad cumplida a la fecha de cálculo.
     */
    public static function clasificar(?string $fechnaci, Carbon $fechaCalculo): string
    {
        $edad = self::edadCumplida($fechnaci, $fechaCalculo);
        if ($edad === null) {
            return self::SIN_FECHA;
        }
        if ($edad < 3) {
            return self::MENOS_DE_3;
        }
        if ($edad <= 19) {
            return (string) $edad;
        }
        if ($edad <= 25) {
            return self::DE_20_A_25;
        }

        return self::MAS_DE_25;
    }

    /**
     * @return array{
     *   ano: int,
     *   fecha: string,
     *   fechaTexto: string,
     *   nivel: string,
     *   header: array<string, mixed>,
     *   cursos: list<array{id: int, etiqueta: string, total: int}>,
     *   filas: list<array{clave: string, etiqueta: string, celdas: array<int, int>, total: int}>,
     *   totalGeneral: int
     * }|null
     */
    public static function build(Carbon $fechaCalculo): ?array
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        $idTerlec = (int) (schoolCtx()->idTerlec ?? 0);
        if (! NivelSistema::esNivelPedagogico($idNivel) || $idTerlec <= 0) {
            return null;
        }

        $corte = $fechaCalculo->copy()->endOfDay()->format('Y-m-d H:i:s');
        $cursos = self::cursosDelNivel($idNivel, $idTerlec);
        $conteo = [];
        foreach (self::filasDefinicion() as $fila) {
            foreach ($cursos as $curso) {
                $conteo[$fila['clave']][$curso['id']] = 0;
            }
        }

        $idsCurso = array_fill_keys(array_column($cursos, 'id'), true);

        $alumnos = DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->join('cursos', 'cursos.Id', '=', 'matricula.idCursos')
            ->where('matricula.idTerlec', $idTerlec)
            ->where('matricula.idNivel', $idNivel)
            ->where('cursos.idTerlec', $idTerlec)
            ->where('cursos.idNivel', $idNivel)
            ->where('matricula.idCondiciones', 1)
            ->where(function ($q) use ($corte) {
                $q->whereNull('matricula.fechaMatricula')
                    ->orWhere('matricula.fechaMatricula', '0000-00-00')
                    ->orWhere('matricula.fechaMatricula', '<=', $corte);
            })
            ->where(function ($q) use ($corte) {
                $q->whereNull('matricula.fechaBaja')
                    ->orWhere('matricula.fechaBaja', '0000-00-00')
                    ->orWhere('matricula.fechaBaja', '')
                    ->orWhere('matricula.fechaBaja', '>', $corte);
            })
            ->select(['matricula.idCursos', 'legajos.fechnaci'])
            ->get();

        foreach ($alumnos as $alumno) {
            $idCurso = (int) ($alumno->idCursos ?? 0);
            if (! isset($idsCurso[$idCurso])) {
                continue;
            }
            $clave = self::clasificar(
                $alumno->fechnaci !== null ? (string) $alumno->fechnaci : null,
                $fechaCalculo,
            );
            $conteo[$clave][$idCurso] = ($conteo[$clave][$idCurso] ?? 0) + 1;
        }

        $filas = [];
        $totalesCurso = array_fill_keys(array_column($cursos, 'id'), 0);
        foreach (self::filasDefinicion() as $def) {
            $celdas = [];
            $totalFila = 0;
            foreach ($cursos as $curso) {
                $valor = (int) ($conteo[$def['clave']][$curso['id']] ?? 0);
                $celdas[$curso['id']] = $valor;
                $totalFila += $valor;
                $totalesCurso[$curso['id']] += $valor;
            }
            $filas[] = [
                'clave' => $def['clave'],
                'etiqueta' => $def['etiqueta'],
                'celdas' => $celdas,
                'total' => $totalFila,
            ];
        }

        $totalGeneral = array_sum($totalesCurso);
        $filas[] = [
            'clave' => self::TOTAL,
            'etiqueta' => 'Total',
            'celdas' => $totalesCurso,
            'total' => $totalGeneral,
        ];

        foreach ($cursos as $i => $curso) {
            $cursos[$i]['total'] = (int) ($totalesCurso[$curso['id']] ?? 0);
        }

        $header = schoolPdfHeaderData();
        $nivel = trim((string) schoolCtx()->nivelNombre());

        return [
            'ano' => (int) schoolCtx()->terlecAno(),
            'fecha' => $fechaCalculo->format('Y-m-d'),
            'fechaTexto' => $fechaCalculo->format('d/m/Y'),
            'nivel' => $nivel !== '' ? $nivel : 'Nivel',
            'header' => $header,
            'cursos' => $cursos,
            'filas' => $filas,
            'totalGeneral' => $totalGeneral,
        ];
    }

    /**
     * @return list<array{id: int, etiqueta: string, total: int}>
     */
    private static function cursosDelNivel(int $idNivel, int $idTerlec): array
    {
        $rows = DB::table('cursos')
            ->where('idTerlec', $idTerlec)
            ->where('idNivel', $idNivel)
            ->get(['Id', 'cursec', 'c', 's', 'orden', 'idNivel']);

        $cursos = [];
        foreach (Curso::ordenarColeccion($rows) as $curso) {
            $id = (int) (data_get($curso, 'Id') ?? data_get($curso, 'id') ?? 0);
            if ($id <= 0) {
                continue;
            }
            $cursos[] = [
                'id' => $id,
                'etiqueta' => self::etiquetaCurso($curso),
                'total' => 0,
            ];
        }

        return $cursos;
    }

    private static function etiquetaCurso(object $curso): string
    {
        $anio = trim((string) (data_get($curso, 'c') ?? ''));
        $seccion = trim((string) (data_get($curso, 's') ?? ''));
        $corta = trim($anio.' '.$seccion);
        if ($corta !== '') {
            return $corta;
        }

        $sec = trim((string) (data_get($curso, 'cursec') ?? ''));

        return $sec !== '' ? $sec : 'Curso';
    }

    private static function edadCumplida(?string $fechnaci, Carbon $fechaCalculo): ?int
    {
        $texto = trim((string) $fechnaci);
        if ($texto === '' || str_starts_with($texto, '0000')) {
            return null;
        }

        try {
            $nacimiento = Carbon::parse($texto)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $referencia = $fechaCalculo->copy()->startOfDay();
        if ($nacimiento->gt($referencia)) {
            return null;
        }

        return $nacimiento->diff($referencia)->y;
    }
}
