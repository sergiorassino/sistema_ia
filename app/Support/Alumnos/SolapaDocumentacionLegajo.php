<?php

namespace App\Support\Alumnos;

/**
 * Solapa virtual DOCUMENTACIÓN del ABM de legajos.
 * No se guarda en `solapas_legajo`: aparece si hay tipos activos para subir.
 */
final class SolapaDocumentacionLegajo
{
    public const SLUG = 'documentacion';

    public const ETIQUETA = 'DOCUMENTACIÓN';

    public const ANCLA_SLUG = 'foto_carnet';

    public static function visible(): bool
    {
        return DocumentosEstudianteAutogestion::habilitadoConTipos();
    }

    /**
     * Inserta la solapa a la derecha de Foto Carnet. Si esa solapa no está, queda al final.
     *
     * @param  array<string, string>  $tabs
     * @return array<string, string>
     */
    public static function insertar(array $tabs, bool $visible): array
    {
        unset($tabs[self::SLUG]);
        if (! $visible) {
            return $tabs;
        }

        $ancla = self::slugAncla($tabs);
        if ($ancla === null) {
            $tabs[self::SLUG] = self::ETIQUETA;

            return $tabs;
        }

        $ordenadas = [];
        foreach ($tabs as $slug => $nombre) {
            $ordenadas[$slug] = $nombre;
            if ($slug === $ancla) {
                $ordenadas[self::SLUG] = self::ETIQUETA;
            }
        }

        return $ordenadas;
    }

    /**
     * @param  array<string, string>  $tabs
     */
    private static function slugAncla(array $tabs): ?string
    {
        if (array_key_exists(self::ANCLA_SLUG, $tabs)) {
            return self::ANCLA_SLUG;
        }

        foreach ($tabs as $slug => $nombre) {
            $norm = mb_strtolower(trim((string) $nombre));
            if ($norm === 'foto carnet' || $norm === 'foto-carnet') {
                return (string) $slug;
            }
        }

        return null;
    }
}
