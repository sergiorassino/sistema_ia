<?php

namespace App\Support\Seguimiento;

use App\Models\Curso;
use App\Models\Inasistencia;
use App\Models\InasistenciaValor;
use App\Models\Matricula;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Toma de asistencia por curso y fecha: inasistencias de clase y de educación física.
 */
final class TomaAsistenciaClase
{
    public const CAMPO_CLASE = 'clase';

    public const CAMPO_ED_FIS = 'edfis';

    public const JUST_CLASE = 'just_clase';

    public const JUST_ED_FIS = 'just_edfis';

    /** ID legacy de educación física en {@see Inasistencia::$tipo}. */
    public const TIPO_LEGACY_EDUCACION_FISICA = 5;

    public static function claveJustDelCampo(string $campo): ?string
    {
        return match ($campo) {
            self::CAMPO_CLASE => self::JUST_CLASE,
            self::CAMPO_ED_FIS => self::JUST_ED_FIS,
            default => null,
        };
    }

    public static function campoDesdeClaveAsistencia(string $clave): ?string
    {
        return match ($clave) {
            self::CAMPO_CLASE, self::JUST_CLASE => self::CAMPO_CLASE,
            self::CAMPO_ED_FIS, self::JUST_ED_FIS => self::CAMPO_ED_FIS,
            default => null,
        };
    }

    /** Normaliza a `J` o `I` (default injustificada). */
    public static function normalizarJust(mixed $just): string
    {
        return strtoupper(trim((string) ($just ?? ''))) === 'J' ? 'J' : 'I';
    }

    /**
     * Fila vacía de asistencia (presente en ambos rubros).
     *
     * @return array{clase: string, edfis: string, just_clase: string, just_edfis: string}
     */
    public static function filaAsistenciaVacia(): array
    {
        return [
            self::CAMPO_CLASE => '',
            self::CAMPO_ED_FIS => '',
            self::JUST_CLASE => 'I',
            self::JUST_ED_FIS => 'I',
        ];
    }

    /** @return Collection<int, InasistenciaValor> */
    public static function tiposClase(): Collection
    {
        $idsEdFis = static::idsEducacionFisica();

        return InasistenciaValor::query()
            ->orderBy('concepto')
            ->get(['id', 'concepto', 'cantidad'])
            ->filter(function (InasistenciaValor $v) use ($idsEdFis) {
                $id = (int) $v->id;

                return $id !== self::TIPO_LEGACY_EDUCACION_FISICA
                    && ! $idsEdFis->contains((string) $id);
            })
            ->values();
    }

    /** @return Collection<int, InasistenciaValor> */
    public static function tiposEducacionFisica(): Collection
    {
        $idsEdFis = static::idsEducacionFisica();

        return InasistenciaValor::query()
            ->orderBy('concepto')
            ->get(['id', 'concepto', 'cantidad'])
            ->filter(function (InasistenciaValor $v) use ($idsEdFis) {
                $id = (int) $v->id;

                return $id === self::TIPO_LEGACY_EDUCACION_FISICA
                    || $idsEdFis->contains((string) $id);
            })
            ->values();
    }

    /** @return Collection<int, string> */
    public static function idsEducacionFisica(): Collection
    {
        return InasistenciaValor::idsEducacionFisica();
    }

    public static function tipoEsEducacionFisica(int $idTipo): bool
    {
        if ($idTipo === self::TIPO_LEGACY_EDUCACION_FISICA) {
            return true;
        }

        return static::idsEducacionFisica()->contains((string) $idTipo);
    }

    public static function tipoPermitidoEnCampo(int $idTipo, string $campo): bool
    {
        $esEdFis = static::tipoEsEducacionFisica($idTipo);

        return match ($campo) {
            self::CAMPO_CLASE => ! $esEdFis,
            self::CAMPO_ED_FIS => $esEdFis,
            default => false,
        };
    }

    /** @return Collection<int, Curso> */
    public static function cursosDelContexto(): Collection
    {
        return Curso::query()
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'idTurnoClase', 'c', 's']);
    }

    public static function cursoDelContexto(int $idCurso): ?Curso
    {
        if ($idCurso < 1) {
            return null;
        }

        return Curso::query()
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->where('Id', $idCurso)
            ->first(['Id', 'cursec', 'orden', 'c', 's']);
    }

    /**
     * Alumnos del curso (condiciones 1, 2, 3 y 4).
     *
     * @return Collection<int, object{
     *     id: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string|null,
     *     idCondiciones: int,
     *     condicion: string|null
     * }>
     */
    public static function alumnosDelCurso(int $idCurso): Collection
    {
        if ($idCurso < 1) {
            return collect();
        }

        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::TODOS
        );

        return DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->leftJoin('condiciones', 'condiciones.id', '=', 'matricula.idCondiciones')
            ->where('matricula.idNivel', schoolCtx()->idNivel)
            ->where('matricula.idTerlec', schoolCtx()->idTerlec)
            ->where('matricula.idCursos', $idCurso)
            ->whereIn('matricula.idCondiciones', $idsCondiciones)
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('legajos.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('legajos.nombre'))
            ->get([
                'matricula.id',
                'matricula.idCondiciones',
                'legajos.apellido',
                'legajos.nombre',
                'legajos.dni',
                'condiciones.condicion',
            ])
            ->map(fn ($r) => (object) [
                'id' => (int) $r->id,
                'apellido' => trim((string) ($r->apellido ?? '')),
                'nombre' => trim((string) ($r->nombre ?? '')),
                'dni' => $r->dni !== null ? trim((string) $r->dni) : null,
                'idCondiciones' => (int) ($r->idCondiciones ?? 0),
                'condicion' => $r->condicion !== null ? trim((string) $r->condicion) : null,
            ]);
    }

    public static function matriculaDelCurso(int $idMatricula, int $idCurso): ?Matricula
    {
        if ($idMatricula < 1 || $idCurso < 1) {
            return null;
        }

        return Matricula::query()
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->where('idCursos', $idCurso)
            ->whereIn('idCondiciones', ListadoCursoCondicionFiltro::idCondicionesParaQuery(
                ListadoCursoCondicionFiltro::TODOS
            ))
            ->find($idMatricula);
    }

    /**
     * Estado de selects por matrícula para una fecha.
     *
     * @param  Collection<int, object{id: int}>  $alumnos
     * @return array<int, array{clase: string, edfis: string, just_clase: string, just_edfis: string}>
     */
    public static function estadoAsistenciaDesdeBd(Collection $alumnos, string $fecha): array
    {
        $ids = $alumnos->pluck('id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values();
        $estado = [];

        foreach ($ids as $idMatricula) {
            $estado[$idMatricula] = static::filaAsistenciaVacia();
        }

        if ($ids->isEmpty() || $fecha === '') {
            return $estado;
        }

        $registros = Inasistencia::query()
            ->whereIn('idMatricula', $ids->all())
            ->whereDate('fecha', $fecha)
            ->orderBy('id')
            ->get(['id', 'idMatricula', 'tipo', 'just']);

        foreach ($registros as $i) {
            $idMatricula = (int) $i->idMatricula;
            if (! isset($estado[$idMatricula])) {
                continue;
            }

            $tipo = (int) $i->tipo;
            if ($tipo < 1) {
                continue;
            }

            $campo = static::tipoEsEducacionFisica($tipo) ? self::CAMPO_ED_FIS : self::CAMPO_CLASE;
            $justKey = static::claveJustDelCampo($campo);
            $estado[$idMatricula][$campo] = (string) $tipo;
            if ($justKey !== null) {
                $estado[$idMatricula][$justKey] = static::normalizarJust($i->just);
            }
        }

        return $estado;
    }

    /**
     * Presente (valor vacío): elimina registros del rubro en esa fecha.
     * Ausente: crea o actualiza un único registro por rubro (clase / ed. física).
     *
     * @return 'deleted'|'inserted'|'updated'|'unchanged'
     */
    public static function sincronizarCelda(
        int $idMatricula,
        int $idCurso,
        string $fecha,
        string $campo,
        string $idTipoRaw,
        string $just = 'I',
    ): string {
        static::matriculaDelCurso($idMatricula, $idCurso) ?? abort(404);

        $idTipo = trim($idTipoRaw) !== '' ? (int) $idTipoRaw : 0;
        $justNorm = static::normalizarJust($just);

        if ($idTipo <= 0) {
            $borrados = static::eliminarRegistrosRubro($idMatricula, $fecha, $campo);

            return $borrados > 0 ? 'deleted' : 'unchanged';
        }

        if (! static::tipoPermitidoEnCampo($idTipo, $campo)) {
            throw new \InvalidArgumentException('Tipo de inasistencia no válido para este rubro.');
        }

        /** @var InasistenciaValor|null $valor */
        $valor = InasistenciaValor::query()->find($idTipo);
        if ($valor === null) {
            throw new \InvalidArgumentException('Tipo de inasistencia inexistente.');
        }

        $payload = static::payloadDesdeValor($idMatricula, $fecha, $idTipo, $valor, $justNorm);
        $existente = static::registroRubro($idMatricula, $fecha, $campo);

        if ($existente === null) {
            Inasistencia::create($payload);

            return 'inserted';
        }

        if ((int) $existente->tipo === $idTipo
            && static::payloadCoincide($payload, $existente)) {
            return 'unchanged';
        }

        $existente->update([
            'tipo' => $payload['tipo'],
            'cantidad' => $payload['cantidad'],
            'just' => $payload['just'],
            'obs' => $payload['obs'],
        ]);

        static::eliminarDuplicadosRubro($idMatricula, $fecha, $campo, (int) $existente->id);

        return 'updated';
    }

    /**
     * @return array{idMatricula: int, fecha: string, tipo: string, cantidad: float|null, just: string, obs: null}
     */
    private static function payloadDesdeValor(
        int $idMatricula,
        string $fecha,
        int $idTipo,
        InasistenciaValor $valor,
        string $just = 'I',
    ): array {
        $cantidad = $valor->cantidad !== null ? round((float) $valor->cantidad, 2) : null;

        return [
            'idMatricula' => $idMatricula,
            'fecha' => $fecha,
            'tipo' => (string) $idTipo,
            'cantidad' => $cantidad,
            'just' => static::normalizarJust($just),
            'obs' => null,
        ];
    }

    private static function registroRubro(int $idMatricula, string $fecha, string $campo): ?Inasistencia
    {
        $registros = static::registrosRubro($idMatricula, $fecha, $campo);

        return $registros->first();
    }

    /** @return Collection<int, Inasistencia> */
    private static function registrosRubro(int $idMatricula, string $fecha, string $campo): Collection
    {
        return Inasistencia::query()
            ->where('idMatricula', $idMatricula)
            ->whereDate('fecha', $fecha)
            ->orderBy('id')
            ->get()
            ->filter(function (Inasistencia $i) use ($campo) {
                $tipo = (int) $i->tipo;

                return static::tipoPermitidoEnCampo($tipo, $campo);
            })
            ->values();
    }

    private static function eliminarRegistrosRubro(int $idMatricula, string $fecha, string $campo): int
    {
        $ids = static::registrosRubro($idMatricula, $fecha, $campo)->pluck('id')->all();
        if ($ids === []) {
            return 0;
        }

        return Inasistencia::query()->whereIn('id', $ids)->delete();
    }

    private static function eliminarDuplicadosRubro(int $idMatricula, string $fecha, string $campo, int $conservarId): void
    {
        $ids = static::registrosRubro($idMatricula, $fecha, $campo)
            ->pluck('id')
            ->filter(fn ($id) => (int) $id !== $conservarId)
            ->all();

        if ($ids !== []) {
            Inasistencia::query()->whereIn('id', $ids)->delete();
        }
    }

    /**
     * @param  array{idMatricula: int, fecha: string, tipo: string, cantidad: float|null, just: string, obs: null}  $payload
     */
    private static function payloadCoincide(array $payload, Inasistencia $existente): bool
    {
        $cantExistente = $existente->cantidad !== null ? round((float) $existente->cantidad, 2) : null;
        $cantPayload = $payload['cantidad'] !== null ? round((float) $payload['cantidad'], 2) : null;

        if ($cantExistente === null && $cantPayload !== null && abs($cantPayload) > 0.009) {
            return false;
        }

        if ($cantExistente !== null && $cantPayload !== null && abs($cantExistente - $cantPayload) > 0.009) {
            return false;
        }

        if ($cantExistente !== null && $cantPayload === null) {
            return false;
        }

        return strtoupper(trim((string) ($existente->just ?? ''))) === strtoupper(trim($payload['just']));
    }

    /**
     * Totales del día según el tipo elegido en cada columna.
     *
     * @param  array<int, array{clase: string, edfis: string, just_clase?: string, just_edfis?: string}>  $asistencia
     * @return array{
     *     presentes_clase: int,
     *     presentes_ed_fis: int,
     *     presentes_contraturno: int,
     *     ausentes: int,
     *     llegadas_tarde: int,
     *     retiros: int,
     *     contraturno: int,
     *     educacion_fisica: int
     * }
     */
    public static function contarResumen(array $asistencia): array
    {
        $conceptos = static::mapaConceptosPorId();
        $total = count($asistencia);
        $resumen = [
            'presentes_clase' => $total,
            'presentes_ed_fis' => $total,
            'presentes_contraturno' => $total,
            'ausentes' => 0,
            'llegadas_tarde' => 0,
            'retiros' => 0,
            'contraturno' => 0,
            'educacion_fisica' => 0,
        ];

        foreach ($asistencia as $fila) {
            $tipoEdFis = trim((string) ($fila[self::CAMPO_ED_FIS] ?? ''));
            if ($tipoEdFis !== '') {
                $resumen['educacion_fisica']++;
            }

            $tipoClase = trim((string) ($fila[self::CAMPO_CLASE] ?? ''));
            if ($tipoClase === '') {
                continue;
            }

            $concepto = $conceptos[(int) $tipoClase] ?? '';

            if (InasistenciaValor::conceptoEsLlegadaTarde($concepto)) {
                $resumen['llegadas_tarde']++;
            } elseif (InasistenciaValor::conceptoEsRetiro($concepto)) {
                $resumen['retiros']++;
            } elseif (InasistenciaValor::conceptoEsContraturno($concepto)) {
                $resumen['contraturno']++;
            } else {
                $resumen['ausentes']++;
            }
        }

        // Presentes a clase: incluye llegadas tarde y contraturno; excluye ausentes y retiros anticipados.
        $resumen['presentes_clase'] = max(0, $total - $resumen['ausentes'] - $resumen['retiros']);
        $resumen['presentes_ed_fis'] = max(0, $total - $resumen['educacion_fisica']);
        $resumen['presentes_contraturno'] = max(0, $total - $resumen['contraturno']);

        return $resumen;
    }

    /** @return array<int, string> id tipo => concepto */
    private static function mapaConceptosPorId(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        foreach (InasistenciaValor::query()->get(['id', 'concepto']) as $v) {
            $cache[(int) $v->id] = trim((string) ($v->concepto ?? ''));
        }

        return $cache;
    }
}
