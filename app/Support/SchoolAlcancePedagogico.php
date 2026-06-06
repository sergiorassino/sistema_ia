<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Alcance por nivel pedagógico en sesión de secretaría.
 *
 * Niveles 1–4: un solo nivel (el del login).
 * Administración (5): todos los niveles pedagógicos (sin selector en sidebar).
 */
final class SchoolAlcancePedagogico
{
    public static function abarcaTodosLosNivelesPedagogicos(): bool
    {
        return schoolEsAdministracion();
    }

    /** Etiqueta de nivel en PDF/listados cuando el alcance es transversal. */
    public static function etiquetaNivelParaInformes(): string
    {
        if (self::abarcaTodosLosNivelesPedagogicos()) {
            return 'Todos los niveles';
        }

        return schoolCtx()->nivelNombre();
    }

    /** Nivel único para filtrar, o null si aplica a Inicial + Primario + Secundario. */
    public static function idNivelFiltroUnico(): ?int
    {
        if (self::abarcaTodosLosNivelesPedagogicos()) {
            return null;
        }

        $id = (int) (schoolCtx()->idNivel ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Nivel de sesión para legajos de docentes (`profesores.nivel`).
     * Siempre el nivel del login (1–5); en Administración devuelve 5, no null.
     */
    public static function idNivelLegajosDocente(): ?int
    {
        $id = (int) (schoolCtx()->idNivel ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public static function aplicarFiltroColumnaNivel($query, string $column = 'idNivel'): void
    {
        $id = self::idNivelFiltroUnico();
        if ($id !== null) {
            $query->where($column, $id);

            return;
        }

        $query->where($column, '<', NivelSistema::ADMINISTRACION);
    }
}
