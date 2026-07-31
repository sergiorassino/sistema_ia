<?php

namespace App\Support\Listados;

/**
 * Modelos preconfigurados del módulo «Listados de Estudiantes con Formato».
 */
final class ListadoEstudiantesFormatoCatalog
{
    public const MODELO_CUADRICULADO = 'cuadriculado';

    public const MODELO_RENGLON = 'renglon';

    public const MODELO_CALENDARIO = 'calendario';

    /** @return list<string> */
    public static function keys(): array
    {
        return [
            self::MODELO_CUADRICULADO,
            self::MODELO_RENGLON,
            self::MODELO_CALENDARIO,
        ];
    }

    public static function normalize(?string $modelo): string
    {
        $modelo = trim((string) $modelo);

        return in_array($modelo, self::keys(), true)
            ? $modelo
            : self::MODELO_CUADRICULADO;
    }

    public static function etiqueta(string $modelo): string
    {
        return match (self::normalize($modelo)) {
            self::MODELO_CUADRICULADO => 'Listado con Cuadriculado',
            self::MODELO_RENGLON => 'Listado con Renglón',
            self::MODELO_CALENDARIO => 'Listado con Calendario',
            default => 'Listado con Cuadriculado',
        };
    }

    public static function requiereMes(string $modelo): bool
    {
        return self::normalize($modelo) === self::MODELO_CALENDARIO;
    }

    /**
     * @return list<array{key: string, label: string, descripcion: string}>
     */
    public static function paraUi(): array
    {
        return [
            [
                'key' => self::MODELO_CUADRICULADO,
                'label' => self::etiqueta(self::MODELO_CUADRICULADO),
                'descripcion' => 'Apellido y nombre por estudiante, con cuadros vacíos para uso eventual.',
            ],
            [
                'key' => self::MODELO_RENGLON,
                'label' => self::etiqueta(self::MODELO_RENGLON),
                'descripcion' => 'Apellido y nombre por estudiante, con un renglón vacío para uso eventual.',
            ],
            [
                'key' => self::MODELO_CALENDARIO,
                'label' => self::etiqueta(self::MODELO_CALENDARIO),
                'descripcion' => 'Apellido y nombre con grilla mensual de días (sábados y domingos en gris).',
            ],
        ];
    }
}
