<?php

namespace App\Support\Listados;

/**
 * Parámetros de exportación Excel de estudiantes por curso.
 *
 * Exportación completa: cursoIds y campoKeys en null (todos los cursos, todos los campos de solapas).
 * Exportación acotada: mismos cursos/campos/condición que el PDF de listado.
 */
final class EstudiantesExcelExportSpec
{
    /**
     * @param  list<int>|null  $cursoIds  null = todos los cursos del contexto
     * @param  list<string>|null  $campoKeys  null = todas las columnas de solapas del legajo
     */
    public function __construct(
        public readonly ?array $cursoIds = null,
        public readonly ?array $campoKeys = null,
        public readonly string $filtroCondicion = ListadoCursoCondicionFiltro::REGULARES,
    ) {}

    public function esExportacionCompleta(): bool
    {
        return $this->cursoIds === null && $this->campoKeys === null;
    }
}
