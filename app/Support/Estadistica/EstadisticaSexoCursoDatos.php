<?php

namespace App\Support\Estadistica;

use App\Models\Curso;
use App\Models\Sexo;
use App\Support\Navegacion\MenuSecretariaPerfil;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Conteo de alumnos regulares por curso y sexo del ciclo y nivel de sesión.
 *
 * Las columnas de sexo son las filas de `sexos` (nombre en `sexos.sexo`), en orden de id.
 */
final class EstadisticaSexoCursoDatos
{
    public const CLAVE_SIN_DATO = 'sd';

    public static function asegurarAcceso(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ESTADISTICA_SEXO_CURSO), 403);
        abort_unless(! MenuSecretariaPerfil::ocultarGruposPedagogicos(), 403);
        abort_unless((int) (schoolCtx()->idNivel ?? 0) > 0 && (int) (schoolCtx()->idTerlec ?? 0) > 0, 403);
    }

    /**
     * @return array{
     *     ano: string,
     *     nivel: string,
     *     fecha: string,
     *     columnas: list<array{clave: string, etiqueta: string}>,
     *     filas: list<array{curso: string, conteos: array<string, int>, total: int}>,
     *     totales: array<string, int>,
     *     total_general: int,
     *     catalogo_vacio: bool
     * }
     */
    public static function armar(): array
    {
        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        $etiquetas = Sexo::etiquetasPorId();
        $columnas = [];
        foreach ($etiquetas as $id => $nombre) {
            $etiqueta = trim((string) $nombre);
            if ($etiqueta === '') {
                continue;
            }
            $columnas[] = [
                'clave' => self::claveSexo((int) $id),
                'etiqueta' => $etiqueta,
            ];
        }

        $nombrePorClave = [];
        foreach ($columnas as $columna) {
            $nombrePorClave[$columna['clave']] = $columna['etiqueta'];
        }

        $conteosPorCurso = [];
        $sinDatoPorCurso = [];

        if ($idNivel > 0 && $idTerlec > 0) {
            $agrupado = DB::table('matricula as m')
                ->join('legajos as l', 'l.id', '=', 'm.idLegajos')
                ->where('m.idTerlec', $idTerlec)
                ->where('m.idNivel', $idNivel)
                ->where('m.idCondiciones', 1)
                ->where('m.idCursos', '>', 0)
                ->where(function ($q): void {
                    $q->whereNull('m.fechaBaja')
                        ->orWhere('m.fechaBaja', '0000-00-00')
                        ->orWhere('m.fechaBaja', '');
                })
                ->groupBy('m.idCursos', 'l.sexo')
                ->selectRaw('m.idCursos as id_curso, l.sexo as sexo, COUNT(*) as cantidad')
                ->get();

            foreach ($agrupado as $fila) {
                $idCurso = (int) $fila->id_curso;
                $cantidad = (int) $fila->cantidad;
                if ($idCurso < 1 || $cantidad < 1) {
                    continue;
                }

                $clave = self::claveParaValorAlmacenado($fila->sexo, $etiquetas);
                if ($clave === null || ! isset($nombrePorClave[$clave])) {
                    $sinDatoPorCurso[$idCurso] = ($sinDatoPorCurso[$idCurso] ?? 0) + $cantidad;

                    continue;
                }

                $conteosPorCurso[$idCurso][$clave] = ($conteosPorCurso[$idCurso][$clave] ?? 0) + $cantidad;
            }
        }

        $totalSinDato = array_sum($sinDatoPorCurso);
        if ($totalSinDato > 0) {
            $columnas[] = [
                'clave' => self::CLAVE_SIN_DATO,
                'etiqueta' => 'Sin dato',
            ];
        }

        $cursos = Curso::ordenarColeccion(
            DB::table('cursos')
                ->where('idNivel', $idNivel)
                ->where('idTerlec', $idTerlec)
                ->get(['Id', 'cursec', 'orden', 'c', 'idNivel'])
        );

        $filas = [];
        $totales = [];
        foreach ($columnas as $columna) {
            $totales[$columna['clave']] = 0;
        }

        foreach ($cursos as $curso) {
            $idCurso = (int) (data_get($curso, 'Id') ?? data_get($curso, 'id') ?? 0);
            $conteos = [];
            $totalFila = 0;
            foreach ($columnas as $columna) {
                $clave = $columna['clave'];
                $valor = $clave === self::CLAVE_SIN_DATO
                    ? (int) ($sinDatoPorCurso[$idCurso] ?? 0)
                    : (int) ($conteosPorCurso[$idCurso][$clave] ?? 0);
                $conteos[$clave] = $valor;
                $totales[$clave] += $valor;
                $totalFila += $valor;
            }

            $nombre = trim((string) data_get($curso, 'cursec'));
            $filas[] = [
                'curso' => $nombre !== '' ? $nombre : 'Curso',
                'conteos' => $conteos,
                'total' => $totalFila,
            ];
        }

        $ano = $ctx->terlecAno();

        return [
            'ano' => $ano !== null ? (string) $ano : '',
            'nivel' => $ctx->nivelNombre(),
            'fecha' => now()->format('d/m/Y'),
            'columnas' => $columnas,
            'filas' => $filas,
            'totales' => $totales,
            'total_general' => array_sum(array_column($filas, 'total')),
            'catalogo_vacio' => $etiquetas === [],
        ];
    }

    public static function claveSexo(int $id): string
    {
        return 's'.$id;
    }

    /**
     * @param  array<int, string>  $etiquetasPorId
     */
    private static function claveParaValorAlmacenado(mixed $valor, array $etiquetasPorId): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            $id = (int) $valor;
            if ($id > 0 && isset($etiquetasPorId[$id])) {
                return self::claveSexo($id);
            }

            return null;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        foreach ($etiquetasPorId as $id => $nombre) {
            if (strcasecmp(trim((string) $nombre), $texto) === 0) {
                return self::claveSexo((int) $id);
            }
        }

        return null;
    }
}
