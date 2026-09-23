<?php

namespace App\Support\Cuotas;

use App\Models\Curso;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Estadística de pago por curso y cuota (matrícula y marzo–diciembre).
 *
 * Una agregación de cuotasgeneradas reemplaza las subconsultas correlacionadas del PDF legacy.
 * El universo de cada mes es el primer vencimiento ya alcanzado a la fecha de cálculo.
 */
final class EstadisticaPagoPorCursoDatos
{
    public const MES_MATRICULA = 15;

    /** @var list<int> */
    public const MESES = [15, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    /** @var array<int, string> */
    public const ETIQUETAS_MES = [
        15 => 'Matr.',
        3 => 'Marz.',
        4 => 'Abri.',
        5 => 'Mayo.',
        6 => 'Juni.',
        7 => 'Juli.',
        8 => 'Agos.',
        9 => 'Seti.',
        10 => 'Octu.',
        11 => 'Novi.',
        12 => 'Dici.',
    ];

    /**
     * @return list<array{clave: string, etiqueta: string, tipo: string}>
     */
    public static function filasInforme(): array
    {
        return [
            ['clave' => 'cant', 'etiqueta' => 'Cantidad de Estudiantes del Curso', 'tipo' => 'int'],
            ['clave' => 'cant_pag', 'etiqueta' => 'Cantidad de Estudiantes que Pagaron', 'tipo' => 'int'],
            ['clave' => 'esperado', 'etiqueta' => 'Importe Esperado', 'tipo' => 'money'],
            ['clave' => 'abonado', 'etiqueta' => 'Importe Abonado', 'tipo' => 'money'],
            ['clave' => 'cant_bon', 'etiqueta' => 'Cantidad de Est. con pago bonificado', 'tipo' => 'int'],
            ['clave' => 'cant_sin_bon', 'etiqueta' => 'Cantidad de Est. sin pago bonificado', 'tipo' => 'int'],
            ['clave' => 'adeudado', 'etiqueta' => 'Total Adeudado', 'tipo' => 'money'],
            ['clave' => 'porcentaje', 'etiqueta' => 'Porcentaje de Deuda', 'tipo' => 'pct'],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{fecha: Carbon, idNivel: int}|null
     */
    public static function normalizarFiltros(array $query): ?array
    {
        $fechaRaw = trim((string) ($query['fecha'] ?? ''));
        if ($fechaRaw === '') {
            return null;
        }

        try {
            $fecha = Carbon::parse($fechaRaw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $idNivel = (int) ($query['nivel'] ?? 0);
        $permitidos = collect(self::nivelesParaSelector())->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($idNivel > 0 && ! in_array($idNivel, $permitidos, true)) {
            $idNivel = 0;
        }

        return [
            'fecha' => $fecha,
            'idNivel' => $idNivel,
        ];
    }

    /**
     * @return list<array{id: int, nombre: string, abrev: string}>
     */
    public static function nivelesParaSelector(): array
    {
        return ListadoEstudiantesPorCuotaDatos::nivelesParaSelector();
    }

    /**
     * @return array{
     *     ano: int,
     *     fechaTexto: string,
     *     header: array<string, mixed>,
     *     cursos: list<array{nombre: string, meses: array<int, array<string, float|int>>}>,
     *     totales: array<int, array<string, float|int>>
     * }|null
     */
    public static function build(Carbon $fecha, int $idNivel): ?array
    {
        $idTerlec = (int) schoolCtx()->idTerlec;
        if ($idTerlec < 1) {
            return null;
        }

        $cursos = self::cursosDelCiclo($idNivel);
        $ids = $cursos->map(fn (Curso $curso) => (int) $curso->getKey())->all();
        $crudos = self::consultarAgregados($idTerlec, $ids, $fecha);

        $filas = [];
        $totales = [];
        foreach (self::MESES as $mes) {
            $totales[$mes] = self::celdaVacia();
        }

        foreach ($cursos as $curso) {
            $idCurso = (int) $curso->getKey();
            $meses = [];
            foreach (self::MESES as $mes) {
                $clave = $idCurso.'-'.$mes;
                $celda = self::armarCelda($crudos[$clave] ?? []);
                $meses[$mes] = $celda;
                $totales[$mes] = self::sumarCeldas([$totales[$mes], $celda]);
            }

            $filas[] = [
                'nombre' => $curso->nombreParaListado(),
                'meses' => $meses,
            ];
        }

        $idEncabezado = $idNivel > 0
            ? $idNivel
            : (int) ($cursos->first()?->idNivel ?? 0);

        return [
            'ano' => (int) schoolCtx()->terlecAno(),
            'fechaTexto' => $fecha->format('d/m/Y'),
            'header' => self::encabezado($idEncabezado),
            'cursos' => $filas,
            'totales' => $totales,
        ];
    }

    public static function porcentajeDeuda(float $adeudado, float $esperado): float
    {
        if ($esperado <= 0) {
            return 0.0;
        }

        return round($adeudado * 100 / $esperado, 2);
    }

    /**
     * @param  array<string, mixed>  $crudo
     * @return array<string, float|int>
     */
    public static function armarCelda(array $crudo): array
    {
        $importe = round((float) ($crudo['importe'] ?? 0), 2);
        $pagado = round((float) ($crudo['pagado'] ?? 0), 2);
        $interes = round((float) ($crudo['interes'] ?? 0), 2);
        $adeudado = round((float) ($crudo['adeudado'] ?? 0), 2);

        return [
            'cant' => (int) ($crudo['cant'] ?? 0),
            'cant_pag' => (int) ($crudo['cant_pag'] ?? 0),
            'esperado' => $importe,
            'abonado' => round($pagado - $interes, 2),
            'cant_bon' => (int) ($crudo['cant_bon'] ?? 0),
            'cant_sin_bon' => (int) ($crudo['cant_sin_bon'] ?? 0),
            'adeudado' => $adeudado,
            'porcentaje' => self::porcentajeDeuda($adeudado, $importe),
        ];
    }

    /**
     * @param  list<array<string, float|int>>  $celdas
     * @return array<string, float|int>
     */
    public static function sumarCeldas(array $celdas): array
    {
        $total = self::celdaVacia();
        foreach ($celdas as $celda) {
            foreach (['cant', 'cant_pag', 'cant_bon', 'cant_sin_bon'] as $clave) {
                $total[$clave] += (int) ($celda[$clave] ?? 0);
            }
            foreach (['esperado', 'abonado', 'adeudado'] as $clave) {
                $total[$clave] = round((float) $total[$clave] + (float) ($celda[$clave] ?? 0), 2);
            }
        }

        $total['porcentaje'] = self::porcentajeDeuda((float) $total['adeudado'], (float) $total['esperado']);

        return $total;
    }

    /**
     * @return array<string, float|int>
     */
    public static function celdaVacia(): array
    {
        return [
            'cant' => 0,
            'cant_pag' => 0,
            'esperado' => 0.0,
            'abonado' => 0.0,
            'cant_bon' => 0,
            'cant_sin_bon' => 0,
            'adeudado' => 0.0,
            'porcentaje' => 0.0,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Curso>
     */
    private static function cursosDelCiclo(int $idNivel)
    {
        $cursos = GeneracionMasivaCuotasConsulta::cursosEnContexto();
        if ($idNivel > 0) {
            $cursos = $cursos->filter(fn (Curso $curso) => (int) ($curso->idNivel ?? 0) === $idNivel);
        }

        return Curso::ordenarColeccion($cursos)->values();
    }

    /**
     * @param  list<int>  $idsCursos
     * @return array<string, array<string, float|int>>
     */
    private static function consultarAgregados(int $idTerlec, array $idsCursos, Carbon $fecha): array
    {
        if ($idsCursos === []) {
            return [];
        }

        $dia = $fecha->toDateString();
        $hasta = $fecha->copy()->endOfDay()->format('Y-m-d H:i:s');
        $vencida = 'venc1 IS NOT NULL AND venc1 <= ?';
        $cobrada = 'fechaPago IS NOT NULL AND fechaPago <= ?';
        $saldoCero = 'ROUND(COALESCE(faltapa, 0), 2) <= 0';

        $select = [];
        $bindings = [];
        $agregar = function (string $sql, array $params) use (&$select, &$bindings): void {
            $select[] = $sql;
            array_push($bindings, ...$params);
        };

        $agregar("SUM(CASE WHEN {$vencida} THEN 1 ELSE 0 END) AS cant", [$dia]);
        $agregar("SUM(CASE WHEN {$saldoCero} AND {$cobrada} AND {$vencida} THEN 1 ELSE 0 END) AS cant_pag", [$hasta, $dia]);
        $agregar("SUM(CASE WHEN {$vencida} THEN COALESCE(importe, 0) ELSE 0 END) AS importe", [$dia]);
        $agregar("SUM(CASE WHEN {$vencida} AND {$cobrada} THEN COALESCE(pagado, 0) ELSE 0 END) AS pagado", [$dia, $hasta]);
        $agregar("SUM(CASE WHEN {$vencida} AND {$cobrada} THEN COALESCE(interes, 0) ELSE 0 END) AS interes", [$dia, $hasta]);
        $agregar("SUM(CASE WHEN {$saldoCero} AND {$vencida} AND {$cobrada} AND COALESCE(bonificacion, 0) > 0 THEN 1 ELSE 0 END) AS cant_bon", [$dia, $hasta]);
        $agregar("SUM(CASE WHEN {$saldoCero} AND {$vencida} AND {$cobrada} AND COALESCE(bonificacion, 0) <= 0 THEN 1 ELSE 0 END) AS cant_sin_bon", [$dia, $hasta]);
        $agregar("SUM(CASE WHEN {$vencida} THEN COALESCE(faltapa, 0) ELSE 0 END) AS adeudado", [$dia]);

        $filas = DB::table('cuotasgeneradas')
            ->where('idTerlec', $idTerlec)
            ->whereIn('idCursos', $idsCursos)
            ->whereIn('idCuotasmeses', self::MESES)
            ->groupBy('idCursos', 'idCuotasmeses')
            ->selectRaw('idCursos, idCuotasmeses, '.implode(', ', $select), $bindings)
            ->get();

        $map = [];
        foreach ($filas as $fila) {
            $clave = ((int) $fila->idCursos).'-'.((int) $fila->idCuotasmeses);
            $map[$clave] = [
                'cant' => (int) ($fila->cant ?? 0),
                'cant_pag' => (int) ($fila->cant_pag ?? 0),
                'importe' => (float) ($fila->importe ?? 0),
                'pagado' => (float) ($fila->pagado ?? 0),
                'interes' => (float) ($fila->interes ?? 0),
                'cant_bon' => (int) ($fila->cant_bon ?? 0),
                'cant_sin_bon' => (int) ($fila->cant_sin_bon ?? 0),
                'adeudado' => (float) ($fila->adeudado ?? 0),
            ];
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private static function encabezado(int $idNivel): array
    {
        if ($idNivel > 0) {
            return LibroArancelesDatos::headerParaNivel($idNivel);
        }

        foreach (self::nivelesParaSelector() as $nivel) {
            $header = LibroArancelesDatos::headerParaNivel((int) $nivel['id']);
            if (trim((string) ($header['insti'] ?? '')) !== '') {
                return $header;
            }
        }

        return LibroArancelesDatos::headerParaNivel(0);
    }
}
