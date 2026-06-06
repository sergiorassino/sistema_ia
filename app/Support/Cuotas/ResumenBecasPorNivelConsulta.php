<?php

namespace App\Support\Cuotas;

use App\Models\CuotasBeca;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Support\NivelSistema;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resumen de becas otorgadas por tipo y nivel pedagógico (matrículas activas del ciclo).
 */
final class ResumenBecasPorNivelConsulta
{
    /**
     * @return list<array{id: int, nombre: string, porcentaje: float}>
     */
    public static function tiposBecaParaResumen(): array
    {
        return CuotasBeca::query()
            ->where('id', '>', GeneracionCuotaEstudianteService::BECA_CUOTA_ENTERA)
            ->orderBy('porcentaje')
            ->orderBy('nombreBeca')
            ->get(['id', 'nombreBeca', 'porcentaje'])
            ->map(fn (CuotasBeca $beca) => [
                'id' => (int) $beca->id,
                'nombre' => trim((string) ($beca->nombreBeca ?? '')),
                'porcentaje' => (float) ($beca->porcentaje ?? 0),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, nombre: string, abrev: string}>
     */
    public static function nivelesColumnas(): array
    {
        return Nivel::query()
            ->where('id', '<', NivelSistema::ADMINISTRACION)
            ->orderBy('id')
            ->get(['id', 'nivel', 'abrev'])
            ->map(fn (Nivel $nivel) => [
                'id' => (int) $nivel->id,
                'nombre' => trim((string) ($nivel->nivel ?? '')),
                'abrev' => trim((string) ($nivel->abrev ?? '')),
            ])
            ->all();
    }

    /**
     * @return array{
     *     filas: list<array{idBeca: int, nombreBeca: string, porNivel: array<int, int>, total: int}>,
     *     totalesNivel: array<int, int>,
     *     totalGeneral: int,
     *     niveles: list<array{id: int, nombre: string, abrev: string}>
     * }
     */
    public static function resumen(): array
    {
        $niveles = self::nivelesColumnas();
        $tiposBeca = self::tiposBecaParaResumen();
        $idTerlec = (int) schoolCtx()->idTerlec;

        $lookup = [];
        if ($tiposBeca !== []) {
            $conteos = self::consultaMatriculasConBeca($idTerlec)
                ->selectRaw('matricula.idCuotasbecas, matricula.idNivel, COUNT(*) as cantidad')
                ->groupBy('matricula.idCuotasbecas', 'matricula.idNivel')
                ->get();

            foreach ($conteos as $fila) {
                $lookup[(int) $fila->idCuotasbecas][(int) $fila->idNivel] = (int) $fila->cantidad;
            }
        }

        $totalesNivel = [];
        foreach ($niveles as $nivel) {
            $totalesNivel[$nivel['id']] = 0;
        }

        $totalGeneral = 0;
        $filas = [];

        foreach ($tiposBeca as $beca) {
            $porNivel = [];
            $totalFila = 0;

            foreach ($niveles as $nivel) {
                $cantidad = $lookup[$beca['id']][$nivel['id']] ?? 0;
                $porNivel[$nivel['id']] = $cantidad;
                $totalesNivel[$nivel['id']] += $cantidad;
                $totalFila += $cantidad;
            }

            $totalGeneral += $totalFila;

            $filas[] = [
                'idBeca' => $beca['id'],
                'nombreBeca' => $beca['nombre'],
                'porNivel' => $porNivel,
                'total' => $totalFila,
            ];
        }

        return [
            'filas' => $filas,
            'totalesNivel' => $totalesNivel,
            'totalGeneral' => $totalGeneral,
            'niveles' => $niveles,
        ];
    }

    /**
     * @return list<array{
     *     alumno: string,
     *     dni: string,
     *     curso: string,
     *     nivel: string,
     *     idCurso: int,
     *     idNivel: int
     * }>
     */
    public static function detalle(int $idBeca, ?int $idNivel = null): array
    {
        if ($idBeca <= GeneracionCuotaEstudianteService::BECA_CUOTA_ENTERA) {
            return [];
        }

        if (! CuotasBeca::query()->whereKey($idBeca)->exists()) {
            return [];
        }

        if ($idNivel !== null && $idNivel > 0) {
            $nivelValido = collect(self::nivelesColumnas())
                ->contains(fn (array $n) => $n['id'] === $idNivel);
            if (! $nivelValido) {
                return [];
            }
        }

        $idTerlec = (int) schoolCtx()->idTerlec;

        $query = self::consultaMatriculasConBeca($idTerlec)
            ->where('matricula.idCuotasbecas', $idBeca)
            ->with([
                'legajo:id,apellido,nombre,dni',
                'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                'curso.curplan:id,curPlanCurso',
                'curso.turnoClase:id,nombre',
                'nivel:id,nivel',
            ]);

        if ($idNivel !== null && $idNivel > 0) {
            $query->where('matricula.idNivel', $idNivel);
        }

        return $query
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->orderBy('matricula.id')
            ->get()
            ->map(fn (Matricula $mat) => self::filaDetalleDesdeMatricula($mat))
            ->values()
            ->all();
    }

    /**
     * Detalle agrupado por curso (y nivel si el filtro es transversal).
     *
     * @return list<array{
     *     curso: string,
     *     nivel: string,
     *     etiqueta: string,
     *     cantidad: int,
     *     alumnos: list<array{alumno: string, dni: string}>
     * }>
     */
    public static function detallePorCurso(int $idBeca, ?int $idNivel = null): array
    {
        $filas = self::detalle($idBeca, $idNivel);
        if ($filas === []) {
            return [];
        }

        $mostrarNivelEnEtiqueta = $idNivel === null || $idNivel < 1;
        $grupos = [];

        foreach ($filas as $fila) {
            $clave = ($fila['nivel'] ?? '').'|'.($fila['curso'] ?? '');

            if (! isset($grupos[$clave])) {
                $curso = $fila['curso'];
                $nivel = $fila['nivel'];
                $etiqueta = ($mostrarNivelEnEtiqueta && $nivel !== '')
                    ? $nivel.' — '.$curso
                    : ($curso !== '' ? $curso : 'Sin curso');

                $grupos[$clave] = [
                    'curso' => $curso,
                    'nivel' => $nivel,
                    'idNivel' => (int) ($fila['idNivel'] ?? 0),
                    'idCurso' => (int) ($fila['idCurso'] ?? 0),
                    'etiqueta' => $etiqueta,
                    'alumnos' => [],
                ];
            }

            $grupos[$clave]['alumnos'][] = [
                'alumno' => $fila['alumno'],
                'dni' => $fila['dni'],
            ];
        }

        $resultado = array_values($grupos);
        usort($resultado, function (array $a, array $b) use ($mostrarNivelEnEtiqueta): int {
            if ($mostrarNivelEnEtiqueta) {
                $cmpNivel = ($a['idNivel'] ?? 0) <=> ($b['idNivel'] ?? 0);
                if ($cmpNivel !== 0) {
                    return $cmpNivel;
                }
            }

            $cmpCurso = self::compararTexto($a['curso'], $b['curso']);
            if ($cmpCurso !== 0) {
                return $cmpCurso;
            }

            return ($a['idCurso'] ?? 0) <=> ($b['idCurso'] ?? 0);
        });

        foreach ($resultado as &$grupo) {
            usort($grupo['alumnos'], fn (array $a, array $b): int => self::compararTexto($a['alumno'], $b['alumno']));
            $grupo['cantidad'] = count($grupo['alumnos']);
        }
        unset($grupo);

        return $resultado;
    }

    private static function compararTexto(string $a, string $b): int
    {
        return strcmp(mb_strtolower($a, 'UTF-8'), mb_strtolower($b, 'UTF-8'));
    }

    public static function etiquetaBeca(int $idBeca): string
    {
        $nombre = CuotasBeca::query()->whereKey($idBeca)->value('nombreBeca');

        return trim((string) ($nombre ?? ''));
    }

    public static function etiquetaNivel(?int $idNivel): string
    {
        if ($idNivel === null || $idNivel < 1) {
            return 'Todos los niveles';
        }

        $nombre = Nivel::query()->whereKey($idNivel)->value('nivel');

        return trim((string) ($nombre ?? ''));
    }

    /**
     * @return Builder<Matricula>
     */
    private static function consultaMatriculasConBeca(int $idTerlec): Builder
    {
        return Matricula::query()
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->where('matricula.idTerlec', $idTerlec)
            ->where('matricula.idCuotasbecas', '>', GeneracionCuotaEstudianteService::BECA_CUOTA_ENTERA)
            ->where(function ($q) {
                $q->whereNull('matricula.fechaBaja')
                    ->orWhere('matricula.fechaBaja', '0000-00-00')
                    ->orWhere('matricula.fechaBaja', '');
            })
            ->tap(fn (Builder $q) => SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($q, 'matricula.idNivel'))
            ->select('matricula.*');
    }

    /**
     * @return array{alumno: string, dni: string, curso: string, nivel: string, idCurso: int, idNivel: int}
     */
    private static function filaDetalleDesdeMatricula(Matricula $mat): array
    {
        $legajo = $mat->legajo;
        $apellido = mb_strtoupper(trim((string) ($legajo?->apellido ?? '')));
        $nombre = mb_strtoupper(trim((string) ($legajo?->nombre ?? '')));
        $alumno = match (true) {
            $apellido !== '' && $nombre !== '' => $apellido.', '.$nombre,
            $apellido !== '' => $apellido,
            default => $nombre,
        };

        return [
            'alumno' => $alumno,
            'dni' => CuotasFormato::formatearDni($legajo?->dni ?? ''),
            'curso' => mb_strtoupper(trim((string) ($mat->curso?->nombreParaListado() ?? ''))),
            'nivel' => trim((string) ($mat->nivel?->nivel ?? '')),
            'idCurso' => (int) ($mat->idCursos ?? 0),
            'idNivel' => (int) ($mat->idNivel ?? 0),
        ];
    }
}
