<?php

namespace App\Support\CalificacionesInicial;

use Illuminate\Support\Facades\DB;

/**
 * Lectura y persistencia de indicadores por materia (nivel inicial).
 */
final class CalificacionesInicialIndicadoresDatos
{
    /**
     * @return object{id: int, idCursos: int, ord: int, materia: string, abrev: ?string, cursoLabel: string}|null
     */
    public static function materiaEnContexto(int $idMateria, int $idNivel, int $idTerlec): ?object
    {
        $row = DB::table('materias as m')
            ->join('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->where('m.id', $idMateria)
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->where('c.idNivel', $idNivel)
            ->where('c.idTerlec', $idTerlec)
            ->first([
                'm.id',
                'm.idCursos',
                'm.ord',
                'm.materia',
                'm.abrev',
                'c.cursec',
                'c.c',
                'c.s',
            ]);

        if ($row === null) {
            return null;
        }

        $curso = new \App\Models\Curso;
        $curso->Id = (int) $row->idCursos;
        $curso->cursec = $row->cursec;
        $curso->c = $row->c;
        $curso->s = $row->s;

        return (object) [
            'id' => (int) $row->id,
            'idCursos' => (int) $row->idCursos,
            'ord' => (int) $row->ord,
            'materia' => (string) $row->materia,
            'abrev' => $row->abrev !== null ? (string) $row->abrev : null,
            'cursoLabel' => $curso->nombreParaListado(),
        ];
    }

    /**
     * Texto completo por etapa (`indicador1`, `indicador2`, …) para el formulario legacy.
     *
     * @return array<int, string>
     */
    public static function textosPorEtapa(int $idMateria): array
    {
        CalificacionesInicialIndicadoresCatalogo::abortSiTablaInexistente();

        $etapas = CalificacionesInicialIndicadoresCatalogo::etapasDisponibles();
        $textos = [];
        foreach ($etapas as $etapa) {
            $textos[$etapa] = '';
        }

        if (CalificacionesInicialIndicadoresCatalogo::esEsquemaColumnas()) {
            $colMateria = CalificacionesInicialIndicadoresCatalogo::columnaMateria();
            $registro = DB::table('indicadores')
                ->where($colMateria, $idMateria)
                ->orderBy('id')
                ->first();

            if ($registro === null) {
                return $textos;
            }

            foreach ($etapas as $etapa) {
                $col = CalificacionesInicialIndicadoresCatalogo::columnaTextoPorEtapa($etapa);
                if ($col !== null) {
                    $textos[$etapa] = (string) ($registro->{$col} ?? '');
                }
            }

            return $textos;
        }

        $filas = self::filasPorEtapaEsquemaFilas($idMateria);
        foreach ($etapas as $etapa) {
            $lineas = [];
            foreach ($filas[$etapa] ?? [] as $fila) {
                $t = trim((string) ($fila['indicador'] ?? ''));
                if ($t !== '') {
                    $lineas[] = $t;
                }
            }
            $textos[$etapa] = implode("\n", $lineas);
        }

        return $textos;
    }

    /**
     * Guarda todas las etapas en un solo registro (insert o update).
     *
     * @param  array<int, string>  $textosPorEtapa
     */
    public static function guardarTextos(int $idMateria, array $textosPorEtapa): void
    {
        CalificacionesInicialIndicadoresCatalogo::abortSiTablaInexistente();

        if (CalificacionesInicialIndicadoresCatalogo::esEsquemaColumnas()) {
            self::guardarTextosEsquemaColumnas($idMateria, $textosPorEtapa);

            return;
        }

        foreach (CalificacionesInicialIndicadoresCatalogo::etapasDisponibles() as $etapa) {
            $texto = (string) ($textosPorEtapa[$etapa] ?? '');
            self::guardarEtapaEsquemaFilas($idMateria, $etapa, self::textoAListasFilas($texto, null));
        }
    }

    /**
     * @param  array<int, string>  $textosPorEtapa
     */
    private static function guardarTextosEsquemaColumnas(int $idMateria, array $textosPorEtapa): void
    {
        $colMateria = CalificacionesInicialIndicadoresCatalogo::columnaMateria();
        $payload = [$colMateria => $idMateria];

        foreach (CalificacionesInicialIndicadoresCatalogo::etapasDisponibles() as $etapa) {
            $col = CalificacionesInicialIndicadoresCatalogo::columnaTextoPorEtapa($etapa);
            if ($col === null) {
                continue;
            }
            $payload[$col] = (string) ($textosPorEtapa[$etapa] ?? '');
        }

        $registro = DB::table('indicadores')
            ->where($colMateria, $idMateria)
            ->orderBy('id')
            ->first();

        if ($registro !== null) {
            unset($payload[$colMateria]);
            DB::table('indicadores')
                ->where('id', (int) $registro->id)
                ->where($colMateria, $idMateria)
                ->update($payload);

            return;
        }

        DB::table('indicadores')->insert($payload);
    }

    /**
     * @return array<int, list<array{id: ?int, ord: int, indicador: string}>>
     */
    public static function filasPorEtapa(int $idMateria): array
    {
        CalificacionesInicialIndicadoresCatalogo::abortSiTablaInexistente();

        if (CalificacionesInicialIndicadoresCatalogo::esEsquemaColumnas()) {
            return self::filasPorEtapaEsquemaColumnas($idMateria);
        }

        return self::filasPorEtapaEsquemaFilas($idMateria);
    }

    /**
     * @return array<int, list<array{id: ?int, ord: int, indicador: string}>>
     */
    private static function filasPorEtapaEsquemaColumnas(int $idMateria): array
    {
        $colMateria = CalificacionesInicialIndicadoresCatalogo::columnaMateria();
        $registro = DB::table('indicadores')
            ->where($colMateria, $idMateria)
            ->orderBy('id')
            ->first();

        $idRegistro = $registro !== null ? (int) $registro->id : null;
        $porEtapa = [];

        foreach (CalificacionesInicialIndicadoresCatalogo::etapasDisponibles() as $etapa) {
            $col = CalificacionesInicialIndicadoresCatalogo::columnaTextoPorEtapa($etapa);
            $texto = $col !== null && $registro !== null
                ? (string) ($registro->{$col} ?? '')
                : '';
            $porEtapa[$etapa] = self::textoAListasFilas($texto, $idRegistro);
        }

        return $porEtapa;
    }

    /**
     * @return array<int, list<array{id: ?int, ord: int, indicador: string}>>
     */
    private static function filasPorEtapaEsquemaFilas(int $idMateria): array
    {
        $colMateria = CalificacionesInicialIndicadoresCatalogo::columnaMateria();
        $colEtapa = CalificacionesInicialIndicadoresCatalogo::columnaEtapa();
        $colOrd = CalificacionesInicialIndicadoresCatalogo::columnaOrd();
        $colTexto = CalificacionesInicialIndicadoresCatalogo::columnaTexto();

        if ($colEtapa === null) {
            return [];
        }

        $etapas = CalificacionesInicialIndicadoresCatalogo::etapasDisponibles();

        $rows = DB::table('indicadores')
            ->where($colMateria, $idMateria)
            ->whereIn($colEtapa, $etapas)
            ->orderBy($colEtapa)
            ->orderBy($colOrd)
            ->orderBy('id')
            ->get(['id', $colEtapa, $colOrd, $colTexto]);

        $porEtapa = [];
        foreach ($etapas as $etapa) {
            $porEtapa[$etapa] = [];
        }

        foreach ($rows as $r) {
            $etapa = (int) $r->{$colEtapa};
            if (! in_array($etapa, $etapas, true)) {
                continue;
            }
            $porEtapa[$etapa][] = [
                'id' => (int) $r->id,
                'ord' => (int) $r->{$colOrd},
                'indicador' => trim((string) ($r->{$colTexto} ?? '')),
            ];
        }

        return $porEtapa;
    }

    /**
     * @return list<array{id: ?int, ord: int, indicador: string}>
     */
    private static function textoAListasFilas(string $texto, ?int $idRegistro): array
    {
        $filas = [];
        $ord = 1;

        foreach (preg_split('/\r\n|\r|\n/', $texto) ?: [] as $linea) {
            $linea = trim((string) $linea);
            if ($linea === '') {
                continue;
            }
            $filas[] = [
                'id' => $idRegistro,
                'ord' => $ord,
                'indicador' => $linea,
            ];
            $ord++;
        }

        return $filas;
    }

    /**
     * @param  list<array{id: ?int, ord: int, indicador: string}>  $filas
     */
    private static function filasATexto(array $filas): string
    {
        $lineas = [];
        foreach ($filas as $fila) {
            $texto = trim((string) ($fila['indicador'] ?? ''));
            if ($texto !== '') {
                $lineas[] = $texto;
            }
        }

        return implode("\n", $lineas);
    }

    /**
     * @param  list<array{id: ?int, ord: int, indicador: string}>  $filas
     */
    public static function guardarEtapa(int $idMateria, int $etapa, array $filas): void
    {
        CalificacionesInicialIndicadoresCatalogo::abortSiTablaInexistente();

        if (! in_array($etapa, CalificacionesInicialIndicadoresCatalogo::etapasDisponibles(), true)) {
            abort(422, 'Etapa no válida.');
        }

        if (CalificacionesInicialIndicadoresCatalogo::esEsquemaColumnas()) {
            self::guardarEtapaEsquemaColumnas($idMateria, $etapa, $filas);

            return;
        }

        self::guardarEtapaEsquemaFilas($idMateria, $etapa, $filas);
    }

    /**
     * @param  list<array{id: ?int, ord: int, indicador: string}>  $filas
     */
    private static function guardarEtapaEsquemaColumnas(int $idMateria, int $etapa, array $filas): void
    {
        $colMateria = CalificacionesInicialIndicadoresCatalogo::columnaMateria();
        $colTexto = CalificacionesInicialIndicadoresCatalogo::columnaTextoPorEtapa($etapa);

        if ($colTexto === null) {
            abort(422, 'Período no configurado en la tabla indicadores.');
        }

        $texto = self::filasATexto($filas);

        $registro = DB::table('indicadores')
            ->where($colMateria, $idMateria)
            ->orderBy('id')
            ->first();

        if ($registro !== null) {
            DB::table('indicadores')
                ->where('id', (int) $registro->id)
                ->where($colMateria, $idMateria)
                ->update([$colTexto => $texto]);

            return;
        }

        $payload = [$colMateria => $idMateria];
        foreach (CalificacionesInicialIndicadoresCatalogo::etapasDisponibles() as $n) {
            $col = CalificacionesInicialIndicadoresCatalogo::columnaTextoPorEtapa($n);
            if ($col !== null) {
                $payload[$col] = '';
            }
        }
        $payload[$colTexto] = $texto;

        DB::table('indicadores')->insert($payload);
    }

    /**
     * @param  list<array{id: ?int, ord: int, indicador: string}>  $filas
     */
    private static function guardarEtapaEsquemaFilas(int $idMateria, int $etapa, array $filas): void
    {
        $colMateria = CalificacionesInicialIndicadoresCatalogo::columnaMateria();
        $colEtapa = CalificacionesInicialIndicadoresCatalogo::columnaEtapa();
        $colOrd = CalificacionesInicialIndicadoresCatalogo::columnaOrd();
        $colTexto = CalificacionesInicialIndicadoresCatalogo::columnaTexto();

        if ($colEtapa === null) {
            abort(503, 'Esquema de indicadores no reconocido.');
        }

        $idsExistentesEtapa = DB::table('indicadores')
            ->where($colMateria, $idMateria)
            ->where($colEtapa, $etapa)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $idsConservados = [];
        $orden = 1;

        foreach ($filas as $fila) {
            $texto = trim((string) ($fila['indicador'] ?? ''));
            $id = isset($fila['id']) && (int) $fila['id'] > 0 ? (int) $fila['id'] : null;

            if ($texto === '') {
                if ($id !== null && in_array($id, $idsExistentesEtapa, true)) {
                    DB::table('indicadores')->where('id', $id)->where($colMateria, $idMateria)->delete();
                }

                continue;
            }

            $payload = [
                $colMateria => $idMateria,
                $colEtapa => $etapa,
                $colOrd => $orden,
                $colTexto => $texto,
            ];

            if ($id !== null && in_array($id, $idsExistentesEtapa, true)) {
                DB::table('indicadores')
                    ->where('id', $id)
                    ->where($colMateria, $idMateria)
                    ->update([
                        $colOrd => $orden,
                        $colTexto => $texto,
                    ]);
                $idsConservados[] = $id;
            } else {
                $nuevoId = DB::table('indicadores')->insertGetId($payload);
                $idsConservados[] = (int) $nuevoId;
            }

            $orden++;
        }

        $idsBorrar = array_diff($idsExistentesEtapa, $idsConservados);
        if ($idsBorrar !== []) {
            DB::table('indicadores')
                ->where($colMateria, $idMateria)
                ->whereIn('id', $idsBorrar)
                ->delete();
        }
    }
}
