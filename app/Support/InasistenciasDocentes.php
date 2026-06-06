<?php

namespace App\Support;

use App\Models\InasDocente;
use App\Models\InasDocenteDetalle;
use App\Models\Profesor;
use App\Models\TipoInaDoc;
use App\Support\InasistenciasDocentes\CalculoFaltasDescuento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Inasistencias docentes (_miPhp/25demayo/public/inasistenciasDocentes).
 */
final class InasistenciasDocentes
{
    public const PERMISO_ORDEN = 23;

    /** @var array<int, array{label: string, titulo: string, meses: array{0: int, 1: int}}> */
    public const BIMESTRES = [
        1 => ['label' => 'En/Fe', 'titulo' => 'Enero-Febrero', 'meses' => [1, 2]],
        2 => ['label' => 'Ma/Ab', 'titulo' => 'Marzo-Abril', 'meses' => [3, 4]],
        3 => ['label' => 'Ma/Ju', 'titulo' => 'Mayo-Junio', 'meses' => [5, 6]],
        4 => ['label' => 'Ju/Ag', 'titulo' => 'Julio-Agosto', 'meses' => [7, 8]],
        5 => ['label' => 'Se/Oc', 'titulo' => 'Septiembre-Octubre', 'meses' => [9, 10]],
        6 => ['label' => 'No/Di', 'titulo' => 'Noviembre-Diciembre', 'meses' => [11, 12]],
    ];

    public static function moduloDisponible(): bool
    {
        return Schema::hasTable('inasdocentes') && Schema::hasTable('tipoinadoc');
    }

    public static function tieneDetalle(): bool
    {
        return Schema::hasTable('inasdocentes_detalle');
    }

    public static function tieneCargos(): bool
    {
        return Schema::hasTable('cargosxprofesor') && Schema::hasTable('cargos');
    }

    public static function anoLectivo(): int
    {
        return (int) (schoolCtx()->terlecAno() ?? now()->year);
    }

    public static function queryDocentesIndex(?string $busqueda = null): Builder
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);

        $query = Profesor::query()
            ->with('tipo')
            ->where('IdTipoProf', '>', 1)
            ->where('apellido', '<>', 'ADMINISTRADOR');

        if ($idNivel > 0) {
            $query->where('nivel', $idNivel);
        }

        $term = trim((string) $busqueda);
        if ($term !== '') {
            $query->buscar($term);
        }

        return $query->orderBy('apellido')->orderBy('nombre');
    }

    public static function profesorDelContexto(int $idProfesor): Profesor
    {
        return self::queryDocentesIndex()->whereKey($idProfesor)->firstOrFail();
    }

    /**
     * Legajos en {@see profesores} con el mismo DNI (lectura en vivo), para totales consolidados.
     * Sin DNI válido solo el legajo indicado.
     *
     * @return array<int, int>
     */
    public static function idsProfesoresMismoDni(int $idProfesor): array
    {
        $profesor = Profesor::query()->find($idProfesor);
        if ($profesor === null) {
            return [$idProfesor];
        }

        $dni = (int) ($profesor->dni ?? 0);
        if ($dni <= 0) {
            return [(int) $profesor->id];
        }

        $ids = Profesor::query()
            ->where('dni', $dni)
            ->where('IdTipoProf', '>', 1)
            ->where('apellido', '<>', 'ADMINISTRADOR')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $ids !== [] ? $ids : [(int) $profesor->id];
    }

    /**
     * @return array<int, array{cargo: string, cant: int, nivel?: string}>
     */
    public static function cargosConHorasPorProfesor(int $idProfesor): array
    {
        if (! self::tieneCargos()) {
            return [];
        }

        $ids = self::idsProfesoresMismoDni($idProfesor);
        $conNivel = Schema::hasTable('niveles');

        $query = DB::table('cargosxprofesor as cxp')
            ->join('cargos as c', 'c.id', '=', 'cxp.idCargos')
            ->whereIn('cxp.idProfesores', $ids);

        if ($conNivel) {
            $query->join('niveles as n', 'n.id', '=', 'cxp.idNiveles')
                ->orderBy('n.nivel')
                ->orderBy('c.cargo');

            return $query
                ->get(['c.cargo', 'cxp.cant', 'n.nivel'])
                ->map(fn ($r) => [
                    'cargo' => (string) $r->cargo,
                    'cant' => (int) $r->cant,
                    'nivel' => (string) $r->nivel,
                ])
                ->all();
        }

        return $query
            ->orderBy('c.cargo')
            ->get(['c.cargo', 'cxp.cant'])
            ->map(fn ($r) => ['cargo' => (string) $r->cargo, 'cant' => (int) $r->cant])
            ->all();
    }

    /**
     * @return Collection<int, InasDocente>
     */
    public static function inasistenciasAnoProfesor(int $idProfesor, int $idNivel, ?int $ano = null): Collection
    {
        $ano = $ano ?? self::anoLectivo();
        $ids = self::idsProfesoresMismoDni($idProfesor);

        return InasDocente::query()
            ->with(['tipo', 'nivel'])
            ->whereIn('idProfesores', $ids)
            ->when(
                count($ids) === 1 && Schema::hasColumn('inasdocentes', 'idNivel'),
                fn ($q) => $q->where('idNivel', $idNivel)
            )
            ->whereYear('fecha', $ano)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(function (InasDocente $i) {
                if (self::tieneCargos() && Schema::hasColumn('inasdocentes', 'idCargosXProfesor') && $i->idCargosXProfesor) {
                    $i->setAttribute('nombreCargo', DB::table('cargosxprofesor as cxp')
                        ->join('cargos as c', 'c.id', '=', 'cxp.idCargos')
                        ->where('cxp.id', $i->idCargosXProfesor)
                        ->value('c.cargo'));
                }

                return $i;
            });
    }

    /**
     * Inasistencias del bimestre (todos los legajos con el mismo DNI), con solapamiento de fechas.
     *
     * @return Collection<int, InasDocente>
     */
    public static function inasistenciasBimestrePorProfesor(int $idProfesor, int $bimestre, int $anio): Collection
    {
        $b = self::BIMESTRES[$bimestre] ?? self::BIMESTRES[1];
        $fechaIni = sprintf('%04d-%02d-01', $anio, $b['meses'][0]);
        $fechaFin = Carbon::create($anio, $b['meses'][1], 1)->endOfMonth()->toDateString();
        $ids = self::idsProfesoresMismoDni($idProfesor);

        return InasDocente::query()
            ->with(['tipo', 'nivel'])
            ->whereIn('idProfesores', $ids)
            ->where(function ($q) use ($fechaIni, $fechaFin) {
                $q->whereBetween('fecha', [$fechaIni, $fechaFin])
                    ->orWhereBetween('hasta', [$fechaIni, $fechaFin])
                    ->orWhere(function ($q2) use ($fechaIni, $fechaFin) {
                        $q2->whereNotNull('hasta')
                            ->where('fecha', '<=', $fechaFin)
                            ->where('hasta', '>=', $fechaIni);
                    });
            })
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(function (InasDocente $i) {
                if (self::tieneCargos() && $i->idCargosXProfesor) {
                    $i->setAttribute('nombreCargo', DB::table('cargosxprofesor as cxp')
                        ->join('cargos as c', 'c.id', '=', 'cxp.idCargos')
                        ->where('cxp.id', $i->idCargosXProfesor)
                        ->value('c.cargo'));
                }

                return $i;
            });
    }

    /**
     * @return Collection<int, TipoInaDoc>
     */
    public static function tiposMotivo(): Collection
    {
        return TipoInaDoc::query()->orderBy('ord')->orderBy('motivo')->get();
    }

    /**
     * @return Collection<int, array{id: int, cargo: string}>
     */
    public static function cargosSelectProfesor(int $idProfesor): Collection
    {
        if (! self::tieneCargos()) {
            return collect();
        }

        return DB::table('cargosxprofesor as cxp')
            ->join('cargos as c', 'c.id', '=', 'cxp.idCargos')
            ->where('cxp.idProfesores', $idProfesor)
            ->orderBy('c.cargo')
            ->get(['cxp.id', 'c.cargo'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'cargo' => (string) $r->cargo]);
    }

    /**
     * @return Collection<int, array{value: string, label: string, idMaterias: int, idCursos: int}>
     */
    public static function opcionesMateriaCurso(int $idNivel): Collection
    {
        $idTerlec = (int) (schoolCtx()->idTerlec ?? 0);
        if ($idTerlec <= 0) {
            return collect();
        }

        return DB::table('materias')
            ->join('cursos as c', 'c.Id', '=', 'materias.idCursos')
            ->where('materias.idTerlec', $idTerlec)
            ->where('c.idTerlec', $idTerlec)
            ->where('c.idNivel', $idNivel)
            ->orderBy('c.orden')
            ->orderBy('c.cursec')
            ->orderBy('materias.ord')
            ->orderBy('materias.materia')
            ->get([
                'materias.id as idMaterias',
                'materias.materia',
                'c.Id as idCursos',
                'c.cursec',
            ])
            ->map(function ($r) {
                $label = trim((string) $r->cursec).' — '.trim((string) $r->materia);

                return [
                    'value' => (int) $r->idMaterias.'_'.(int) $r->idCursos,
                    'label' => $label,
                    'idMaterias' => (int) $r->idMaterias,
                    'idCursos' => (int) $r->idCursos,
                ];
            });
    }

    /**
     * @return Collection<int, InasDocenteDetalle>
     */
    public static function detalleDeInasistencia(int $idInasDocentes): Collection
    {
        if (! self::tieneDetalle()) {
            return collect();
        }

        return InasDocenteDetalle::query()
            ->where('idInasDocentes', $idInasDocentes)
            ->orderBy('id')
            ->get();
    }

    public static function registroDelProfesor(int $id, int $idProfesor): InasDocente
    {
        return InasDocente::query()
            ->whereKey($id)
            ->where('idProfesores', $idProfesor)
            ->firstOrFail();
    }

    /**
     * Convierte filas del formulario (materiaCurso + cantidad) a registros de detalle.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<int, array{idMaterias: int, idCursos: int, cantidad: float}>
     */
    public static function normalizarDetalleFilas(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $valor = trim((string) ($fila['materiaCurso'] ?? ''));
            if ($valor === '' || ! str_contains($valor, '_')) {
                continue;
            }
            [$idMat, $idCur] = explode('_', $valor, 2);
            $idMat = (int) $idMat;
            $idCur = (int) $idCur;
            $cant = (float) str_replace(',', '.', trim((string) ($fila['cantidad'] ?? '0')));
            if ($idMat > 0 && $idCur > 0 && $cant > 0) {
                $out[] = ['idMaterias' => $idMat, 'idCursos' => $idCur, 'cantidad' => $cant];
            }
        }

        return $out;
    }

    /**
     * @param  array<int|string, string>  $materiaCurso
     * @param  array<int|string, string|float|int>  $cantidades
     * @return array<int, array{idMaterias: int, idCursos: int, cantidad: float}>
     */
    public static function normalizarDetalleDesdeListas(array $materiaCurso, array $cantidades): array
    {
        $filas = [];
        $ids = array_unique(array_merge(array_keys($materiaCurso), array_keys($cantidades)));
        sort($ids, SORT_NUMERIC);

        foreach ($ids as $id) {
            $filas[] = [
                'materiaCurso' => $materiaCurso[$id] ?? $materiaCurso[(string) $id] ?? '',
                'cantidad' => $cantidades[$id] ?? $cantidades[(string) $id] ?? '0',
            ];
        }

        return self::normalizarDetalleFilas($filas);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function guardarInasistencia(array $datos, Profesor $profesor, ?int $id = null): InasDocente
    {
        $payload = [
            'idProfesores' => (int) $profesor->id,
            'dniProfesor' => (int) ($profesor->dni ?? 0),
            'idNivel' => (int) ($datos['idNivel'] ?? $profesor->nivel),
            'inaLic' => ! empty($datos['inaLic']) ? 1 : 0,
            'idTipoInaDoc' => (int) $datos['idTipoInaDoc'],
            'idCargosXProfesor' => (int) ($datos['idCargosXProfesor'] ?? 0),
            'fecha' => $datos['fecha'] ?? null,
            'hasta' => ! empty($datos['hasta']) ? $datos['hasta'] : null,
            'cantOblig' => (int) ($datos['cantOblig'] ?? 0),
            'cantObligIna' => round((float) ($datos['cantObligIna'] ?? 0), 1),
            'justif' => ! empty($datos['justif']) ? 1 : 0,
            'obs' => trim((string) ($datos['obs'] ?? '')),
        ];

        if (! Schema::hasColumn('inasdocentes', 'dniProfesor')) {
            unset($payload['dniProfesor']);
        }
        if (! Schema::hasColumn('inasdocentes', 'idNivel')) {
            unset($payload['idNivel']);
        }
        if (! Schema::hasColumn('inasdocentes', 'idCargosXProfesor')) {
            unset($payload['idCargosXProfesor']);
        }

        if ($id) {
            $reg = self::registroDelProfesor($id, (int) $profesor->id);
            $reg->update($payload);
        } else {
            $reg = InasDocente::create($payload);
        }

        if (self::tieneDetalle()) {
            $idInas = (int) $reg->getKey();
            $detalle = $datos['detalle'] ?? [];
            if ($detalle === [] && isset($datos['detalleMateriaCurso'], $datos['detalleCantidad'])
                && is_array($datos['detalleMateriaCurso']) && is_array($datos['detalleCantidad'])) {
                $detalle = self::normalizarDetalleDesdeListas($datos['detalleMateriaCurso'], $datos['detalleCantidad']);
            }
            if ($detalle === [] && ! empty($datos['detalleFilas']) && is_array($datos['detalleFilas'])) {
                $detalle = self::normalizarDetalleFilas($datos['detalleFilas']);
            }

            InasDocenteDetalle::query()->where('idInasDocentes', $idInas)->delete();
            foreach ($detalle as $fila) {
                $idMat = (int) ($fila['idMaterias'] ?? 0);
                $idCur = (int) ($fila['idCursos'] ?? 0);
                $cant = round((float) ($fila['cantidad'] ?? 0), 2);
                if ($idMat > 0 && $idCur > 0 && $cant > 0) {
                    DB::table('inasdocentes_detalle')->insert([
                        'idInasDocentes' => $idInas,
                        'idMaterias' => $idMat,
                        'idCursos' => $idCur,
                        'cantidad' => $cant,
                    ]);
                }
            }
        }

        return $reg;
    }

    public static function eliminarInasistencia(int $id, int $idProfesor): void
    {
        $reg = self::registroDelProfesor($id, $idProfesor);
        if (self::tieneDetalle()) {
            InasDocenteDetalle::query()->where('idInasDocentes', $reg->id)->delete();
        }
        $reg->delete();
    }

    public static function formatearCantidad(float|int|string|null $valor): string
    {
        return number_format((float) ($valor ?? 0), 1, ',', '.');
    }

    public static function rangoBimestre(int $bimestre, int $anio): array
    {
        $b = self::BIMESTRES[$bimestre] ?? self::BIMESTRES[1];
        $desde = Carbon::create($anio, $b['meses'][0], 1)->startOfDay();
        $hasta = Carbon::create($anio, $b['meses'][1], 1)->endOfMonth()->endOfDay();

        return ['desde' => $desde, 'hasta' => $hasta, 'titulo' => $b['titulo'].' '.$anio];
    }
}
