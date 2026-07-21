<?php

namespace App\Support\Tea;

use App\Models\Matricula;
use App\Models\ReincoRegistro;
use App\Models\ReincoTipo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Registros TEA (reincorporación por inasistencias).
 *
 * Tablas legacy únicas (no varían por ciclo lectivo): `reinco2025` y `reinco2025_tipo`.
 * El alcance por año se filtra vía la matrícula del contexto activo (`idTerlec` / `idNivel`).
 */
final class ReincoTea
{
    public const TABLA_REGISTROS = 'reinco2025';

    public const TABLA_TIPOS = 'reinco2025_tipo';

    public static function anoLectivo(): int
    {
        return (int) schoolCtx()->terlecAno();
    }

    public static function tablasDisponibles(): bool
    {
        return Schema::hasTable(self::TABLA_REGISTROS)
            && Schema::hasTable(self::TABLA_TIPOS);
    }

    /** @return Builder<ReincoRegistro> */
    public static function queryRegistros(): Builder
    {
        abort_unless(
            Schema::hasTable(self::TABLA_REGISTROS),
            404,
            'No hay tabla de registros TEA (reinco2025).'
        );

        return ReincoRegistro::query();
    }

    /** @return Builder<ReincoTipo> */
    public static function queryTipos(): Builder
    {
        abort_unless(
            Schema::hasTable(self::TABLA_TIPOS),
            404,
            'No hay catálogo de situaciones TEA (reinco2025_tipo).'
        );

        return ReincoTipo::query();
    }

    /** @return Collection<int, ReincoTipo> */
    public static function tiposOrdenados(): Collection
    {
        return self::queryTipos()
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'orden', 'tipo']);
    }

    public static function tipoExiste(int $idTipo): bool
    {
        if ($idTipo <= 0) {
            return false;
        }

        return self::queryTipos()->whereKey($idTipo)->exists();
    }

    /** @return Collection<int, ReincoRegistro> */
    public static function registrosDeMatricula(int $idMatricula): Collection
    {
        $registros = self::queryRegistros()
            ->where('idMatricula', $idMatricula)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        return self::adjuntarTipos($registros);
    }

    /**
     * @param  Collection<int, ReincoRegistro>  $registros
     * @return Collection<int, ReincoRegistro>
     */
    public static function adjuntarTipos(Collection $registros): Collection
    {
        if ($registros->isEmpty()) {
            return $registros;
        }

        $tipos = self::tiposOrdenados()->keyBy('id');
        foreach ($registros as $registro) {
            $tipo = $tipos->get((int) $registro->idReinco_tipo);
            if ($tipo instanceof ReincoTipo) {
                $registro->setRelation('tipo', $tipo);
            }
        }

        return $registros;
    }

    public static function matriculaEnContexto(int $idMatricula): Matricula
    {
        /** @var Matricula $matricula */
        $matricula = Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->findOrFail($idMatricula);

        return $matricula;
    }

    public static function registroEnContexto(int $idRegistro): ReincoRegistro
    {
        $registro = self::queryRegistros()->findOrFail($idRegistro);

        $registro = self::adjuntarTipos(collect([$registro]))->first();
        abort_unless($registro instanceof ReincoRegistro, 404);

        $registro->load(['matricula.legajo', 'matricula.curso']);

        $matricula = $registro->matricula;
        if ($matricula === null
            || (int) $matricula->idNivel !== (int) schoolCtx()->idNivel
            || (int) $matricula->idTerlec !== (int) schoolCtx()->idTerlec) {
            abort(404);
        }

        return $registro;
    }
}
