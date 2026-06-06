<?php

namespace App\Support\CalificacionesInicial;

use App\Models\Curso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de etapas y columnas de la tabla legacy `indicadores` (nivel inicial).
 *
 * Esquemas soportados:
 * - **columnas_por_etapa:** una fila por materia (`indicador1`, `indicador2`, …) con texto multilínea.
 * - **filas_por_etapa:** varias filas por materia (`etapa`, `ord`, `indicador`).
 */
final class CalificacionesInicialIndicadoresCatalogo
{
    public const ESQUEMA_COLUMNAS = 'columnas_por_etapa';

    public const ESQUEMA_FILAS = 'filas_por_etapa';

    /** Etapas cuando el esquema es por filas (otros colegios). */
    private const ETAPAS_FILAS = [1, 2, 3];

    public static function tablaDisponible(): bool
    {
        return Schema::hasTable('indicadores');
    }

    public static function abortSiTablaInexistente(): void
    {
        abort_unless(
            self::tablaDisponible(),
            503,
            'La tabla indicadores no está disponible en esta base de datos.'
        );

        abort_unless(
            self::tipoEsquema() !== '',
            503,
            'La tabla indicadores no tiene un formato reconocido (se esperan columnas indicador1/indicador2 o filas con etapa y texto).'
        );
    }

    public static function tipoEsquema(): string
    {
        if (! self::tablaDisponible()) {
            return '';
        }

        if (Schema::hasColumn('indicadores', 'indicador1')) {
            return self::ESQUEMA_COLUMNAS;
        }

        if (self::columnaEtapa() !== null) {
            return self::ESQUEMA_FILAS;
        }

        return '';
    }

    public static function esEsquemaColumnas(): bool
    {
        return self::tipoEsquema() === self::ESQUEMA_COLUMNAS;
    }

    /**
     * @return list<int>
     */
    public static function etapasDisponibles(): array
    {
        if (self::esEsquemaColumnas()) {
            $etapas = [];
            for ($n = 1; $n <= 9; $n++) {
                if (Schema::hasColumn('indicadores', 'indicador' . $n)) {
                    $etapas[] = $n;
                }
            }

            return $etapas !== [] ? $etapas : [1, 2];
        }

        return self::ETAPAS_FILAS;
    }

    /** Columna de texto del período N en esquema `indicador1`, `indicador2`, … */
    public static function columnaTextoPorEtapa(int $etapa): ?string
    {
        if (self::esEsquemaColumnas()) {
            $col = 'indicador' . $etapa;

            return Schema::hasColumn('indicadores', $col) ? $col : null;
        }

        return self::columnaTexto();
    }

    public static function columnaTexto(): string
    {
        foreach (['indicador', 'Indicador', 'texto', 'descripcion'] as $col) {
            if (Schema::hasColumn('indicadores', $col)) {
                return $col;
            }
        }

        return 'indicador';
    }

    public static function columnaEtapa(): ?string
    {
        foreach (['etapa', 'Etapa', 'periodo', 'Periodo'] as $col) {
            if (Schema::hasColumn('indicadores', $col)) {
                return $col;
            }
        }

        return null;
    }

    public static function columnaOrd(): string
    {
        foreach (['ord', 'Ord', 'nro', 'Nro', 'orden', 'Orden'] as $col) {
            if (Schema::hasColumn('indicadores', $col)) {
                return $col;
            }
        }

        return 'ord';
    }

    public static function columnaMateria(): string
    {
        foreach (['idMaterias', 'idMateria', 'IdMaterias'] as $col) {
            if (Schema::hasColumn('indicadores', $col)) {
                return $col;
            }
        }

        return 'idMaterias';
    }

    public static function etiquetaEtapa(int $etapa): string
    {
        return match ($etapa) {
            2 => '2.º período',
            3 => '3.º período',
            default => '1.º período',
        };
    }

    /** Etiqueta del formulario (como el sistema anterior). */
    public static function etiquetaEtapaFormulario(int $etapa): string
    {
        return match ($etapa) {
            2 => 'Segunda Etapa',
            3 => 'Tercera Etapa',
            default => 'Primera Etapa',
        };
    }

    /**
     * Cursos del ciclo activo con sus materias, ordenados para el listado del módulo.
     *
     * @return Collection<int, array{curso: Curso, materias: Collection<int, object>}>
     */
    public static function materiasAgrupadasPorCurso(int $idNivel, int $idTerlec): Collection
    {
        $cursos = Curso::query()
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'c', 's', 'orden', 'idTurnoClase']);

        if ($cursos->isEmpty()) {
            return collect();
        }

        $idsCursos = $cursos->pluck('Id')->map(fn ($id) => (int) $id)->all();

        $materias = DB::table('materias')
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->whereIn('idCursos', $idsCursos)
            ->orderBy('idCursos')
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'idCursos', 'ord', 'materia']);

        $porCurso = $materias->groupBy(fn ($m) => (int) $m->idCursos);

        return $cursos->map(function (Curso $curso) use ($porCurso) {
            $idCurso = (int) $curso->Id;
            $lista = $porCurso->get($idCurso, collect());

            return [
                'curso' => $curso,
                'materias' => $lista->values(),
            ];
        })->filter(fn (array $g) => $g['materias']->isNotEmpty())->values();
    }
}
