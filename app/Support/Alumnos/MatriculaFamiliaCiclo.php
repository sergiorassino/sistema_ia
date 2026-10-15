<?php

namespace App\Support\Alumnos;

use App\Models\Ento;
use App\Models\Matricula;
use App\Models\Terlec;
use App\Support\InformeInasistencias;
use Illuminate\Support\Facades\Schema;

/**
 * Matrícula que usan la ficha y la aceptación de documentos en el Menú de Alumnos.
 *
 * El resto del portal sigue en `ento.idTerlecVerNotas`. Si `ento.matriAnioSiguiente`
 * está activo en el nivel de la sesión, estas dos pantallas usan la matrícula del
 * año siguiente (mismo nivel, o la de otro nivel si es la única de ese año).
 */
final class MatriculaFamiliaCiclo
{
    public const COLUMNA = 'matriAnioSiguiente';

    public static function anioSiguienteActivo(?int $idNivel = null): bool
    {
        $idNivel ??= (int) (studentCtx()->idNivel ?? 0);
        if ($idNivel < 1) {
            return false;
        }

        if (! Schema::hasTable('ento') || ! Schema::hasColumn('ento', self::COLUMNA)) {
            return false;
        }

        return (int) Ento::query()->where('idNivel', $idNivel)->value(self::COLUMNA) === 1;
    }

    /**
     * Matrícula de ficha, aceptación y bloqueo de esas pantallas.
     * Con el flag activo y sin matrícula del año siguiente, null (no cae al ciclo abierto).
     */
    public static function matricula(): ?Matricula
    {
        if (! self::anioSiguienteActivo()) {
            return InformeInasistencias::matriculaAutogestion();
        }

        return self::matriculaAnioSiguiente();
    }

    public static function matriculaAnioSiguiente(): ?Matricula
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $anoBase = (int) ($ctx->terlecAno() ?? 0);
        if ($anoBase < 2000) {
            return null;
        }

        $idsTerlec = Terlec::query()
            ->where('ano', $anoBase + 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($idsTerlec === []) {
            return null;
        }

        $filas = Matricula::query()
            ->with(['curso.nivel', 'terlec'])
            ->where('idLegajos', (int) $ctx->idLegajo)
            ->whereIn('idTerlec', $idsTerlec)
            ->where(function ($q): void {
                $q->whereNull('fechaBaja')
                    ->orWhere('fechaBaja', '0000-00-00');
            })
            ->orderByDesc('id')
            ->get();

        if ($filas->isEmpty()) {
            return null;
        }

        $idNivel = (int) $ctx->idNivel;
        $mismoNivel = $filas->first(
            fn (Matricula $matricula) => (int) $matricula->idNivel === $idNivel
        );

        return $mismoNivel ?? $filas->first();
    }

    public static function ano(Matricula $matricula): int
    {
        $matricula->loadMissing('terlec');
        $ano = (int) ($matricula->terlec?->ano ?? 0);

        return $ano >= 2000 ? $ano : 0;
    }

    /**
     * Nivel de los PDF institucionales: el de la matrícula que se está aceptando.
     */
    public static function idNivelDocumentos(): int
    {
        $id = (int) (self::matricula()?->idNivel ?? 0);

        return $id > 0 ? $id : (int) (studentCtx()->idNivel ?? 0);
    }

    public static function mensajeSinMatricula(): string
    {
        $ano = (int) (studentCtx()->terlecAno() ?? 0);
        $siguiente = $ano >= 2000 ? (string) ($ano + 1) : 'el año siguiente';

        return 'No hay matrícula registrada para '.$siguiente.'. Contacte a secretaría.';
    }
}
