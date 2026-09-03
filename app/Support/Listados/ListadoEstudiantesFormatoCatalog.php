<?php

namespace App\Support\Listados;

use App\Support\Alumnos\FotoCarnetLegajo;

/**
 * Modelos preconfigurados del módulo «Listados de Estudiantes con Formato».
 */
final class ListadoEstudiantesFormatoCatalog
{
    public const MODELO_CUADRICULADO = 'cuadriculado';

    public const MODELO_RENGLON = 'renglon';

    public const MODELO_CALENDARIO = 'calendario';

    public const MODELO_REGISTRO_FIRMAS = 'registro_firmas';

    public const MODELO_FOTOS = 'fotos';

    /** @return list<string> */
    public static function keys(): array
    {
        return [
            self::MODELO_CUADRICULADO,
            self::MODELO_RENGLON,
            self::MODELO_CALENDARIO,
            self::MODELO_REGISTRO_FIRMAS,
            self::MODELO_FOTOS,
        ];
    }

    public static function known(?string $modelo): string
    {
        $modelo = trim((string) $modelo);

        return in_array($modelo, self::keys(), true)
            ? $modelo
            : self::MODELO_CUADRICULADO;
    }

    /**
     * Visible y generable solo si el colegio tiene foto carnet en solapas del legajo
     * ({@see \App\Support\Alumnos\FotoCarnetLegajo::habilitadaEnSolapasLegajo()}).
     */
    public static function modeloFotosDisponible(?bool $forzar = null): bool
    {
        return $forzar ?? FotoCarnetLegajo::habilitadaEnSolapasLegajo();
    }

    /** @return list<string> */
    public static function keysPermitidos(?bool $fotosHabilitadas = null): array
    {
        $keys = [
            self::MODELO_CUADRICULADO,
            self::MODELO_RENGLON,
            self::MODELO_CALENDARIO,
            self::MODELO_REGISTRO_FIRMAS,
        ];
        if (self::modeloFotosDisponible($fotosHabilitadas)) {
            $keys[] = self::MODELO_FOTOS;
        }

        return $keys;
    }

    public static function normalize(?string $modelo, ?bool $fotosHabilitadas = null): string
    {
        $modelo = self::known($modelo);

        return in_array($modelo, self::keysPermitidos($fotosHabilitadas), true)
            ? $modelo
            : self::MODELO_CUADRICULADO;
    }

    public static function etiqueta(string $modelo): string
    {
        return match (self::known($modelo)) {
            self::MODELO_CUADRICULADO => 'Listado con Cuadriculado',
            self::MODELO_RENGLON => 'Listado con Renglón',
            self::MODELO_CALENDARIO => 'Listado con Calendario',
            self::MODELO_REGISTRO_FIRMAS => 'Listado para Registro de Firmas',
            self::MODELO_FOTOS => 'Listado de Fotos',
            default => 'Listado con Cuadriculado',
        };
    }

    public static function requiereMes(string $modelo): bool
    {
        return self::known($modelo) === self::MODELO_CALENDARIO;
    }

    public static function requiereTamanoFoto(string $modelo): bool
    {
        return self::known($modelo) === self::MODELO_FOTOS;
    }

    /**
     * @return list<array{key: string, label: string, descripcion: string}>
     */
    public static function paraUi(?bool $fotosHabilitadas = null): array
    {
        $permitidos = array_flip(self::keysPermitidos($fotosHabilitadas));
        $items = [
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
            [
                'key' => self::MODELO_REGISTRO_FIRMAS,
                'label' => self::etiqueta(self::MODELO_REGISTRO_FIRMAS),
                'descripcion' => 'Espacio para firma y aclaración por estudiante, con nombres de madre y padre.',
            ],
            [
                'key' => self::MODELO_FOTOS,
                'label' => self::etiqueta(self::MODELO_FOTOS),
                'descripcion' => 'Foto carnet de cada estudiante, con apellido y nombre, curso y sección, y año lectivo.',
            ],
        ];

        return array_values(array_filter(
            $items,
            fn (array $item) => isset($permitidos[$item['key']]),
        ));
    }
}
