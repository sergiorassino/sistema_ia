<?php

namespace App\Support\Cooperadora;

use App\Models\CoopMedioPago;
use Illuminate\Support\Collection;

final class MedioPagoCooperadora
{
    /**
     * @return Collection<int, CoopMedioPago>
     */
    public static function paraSelector(): Collection
    {
        return CoopMedioPago::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    /**
     * @return list<int>
     */
    public static function idsActivos(): array
    {
        return CoopMedioPago::query()
            ->where('activo', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array{id: int, nombre: string}|null
     */
    public static function resolver(?int $id): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        $medio = CoopMedioPago::query()
            ->where('activo', true)
            ->find($id);

        if ($medio === null) {
            return null;
        }

        return [
            'id' => (int) $medio->id,
            'nombre' => trim((string) $medio->nombre),
        ];
    }
}
