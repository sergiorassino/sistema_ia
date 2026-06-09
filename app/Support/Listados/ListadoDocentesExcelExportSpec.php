<?php

namespace App\Support\Listados;

/**
 * Parámetros de exportación Excel de docentes por rol.
 *
 * Exportación completa: roleIds y campoKeys en null (todos los roles, todos los campos de solapas).
 * Exportación acotada: mismos roles/campos que el PDF de listado.
 */
final class ListadoDocentesExcelExportSpec
{
    /**
     * @param  list<int>|null  $roleIds  null = todos los roles
     * @param  list<string>|null  $campoKeys  null = todas las columnas de solapas del legajo docente
     */
    public function __construct(
        public readonly ?array $roleIds = null,
        public readonly ?array $campoKeys = null,
    ) {}

    public function esExportacionCompleta(): bool
    {
        return $this->roleIds === null && $this->campoKeys === null;
    }
}
