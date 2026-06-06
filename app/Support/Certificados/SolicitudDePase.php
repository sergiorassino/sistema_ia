<?php

namespace App\Support\Certificados;

use App\Models\PaseProvisorio;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Solicitud de pase — listado de legajos de nivel medio, paseprovisorio y URL del PDF.
 */
final class SolicitudDePase
{
    /**
     * @return LengthAwarePaginator<int, array{
     *     idLegajos: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string
     * }>
     */
    public static function paginarAlumnos(?string $buscar, int $porPagina = 50): LengthAwarePaginator
    {
        return PaseParcial::paginarAlumnos($buscar, $porPagina);
    }

    /**
     * @return array{
     *     idLegajos: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string,
     *     idNivel: int
     * }|null
     */
    public static function alumnoElegible(int $idLegajos): ?array
    {
        return PaseParcial::alumnoElegible($idLegajos);
    }

    /**
     * @return array{
     *     fechaEmision: string,
     *     cursosCompletos: string,
     *     mateAdeud: string,
     *     cursar: string,
     *     preAnte: string
     * }
     */
    public static function valoresPorDefecto(): array
    {
        return [
            'fechaEmision' => now()->format('Y-m-d'),
            'cursosCompletos' => '',
            'mateAdeud' => '',
            'cursar' => '',
            'preAnte' => '',
        ];
    }

    /**
     * @return array{
     *     fechaEmision: string,
     *     cursosCompletos: string,
     *     mateAdeud: string,
     *     cursar: string,
     *     preAnte: string
     * }|null
     */
    public static function datosGuardados(int $idLegajos): ?array
    {
        if ($idLegajos < 1) {
            return null;
        }

        $row = PaseProvisorio::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'fechaEmision' => $row->fechaEmision?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'cursosCompletos' => trim((string) ($row->cursosCompletos ?? '')),
            'mateAdeud' => trim((string) ($row->mateAdeud ?? '')),
            'cursar' => trim((string) ($row->cursar ?? '')),
            'preAnte' => trim((string) ($row->preAnte ?? '')),
        ];
    }

    /**
     * @param  array{
     *     fechaEmision: string,
     *     cursosCompletos: string,
     *     mateAdeud: string,
     *     cursar: string,
     *     preAnte: string
     * }  $datos
     */
    public static function guardar(int $idLegajos, array $datos): bool
    {
        if ($idLegajos < 1 || self::alumnoElegible($idLegajos) === null) {
            return false;
        }

        $existente = PaseProvisorio::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->first();

        $payload = [
            'fechaEmision' => $datos['fechaEmision'],
            'cursosCompletos' => $datos['cursosCompletos'] !== '' ? $datos['cursosCompletos'] : null,
            'mateAdeud' => $datos['mateAdeud'] !== '' ? $datos['mateAdeud'] : null,
            'cursar' => $datos['cursar'] !== '' ? $datos['cursar'] : null,
            'preAnte' => $datos['preAnte'] !== '' ? $datos['preAnte'] : null,
        ];

        if ($existente !== null) {
            $existente->fill($payload);
            $existente->save();

            return true;
        }

        PaseProvisorio::query()->create(array_merge(['idLegajos' => $idLegajos], $payload));

        return true;
    }

    /**
     * @param  array{
     *     fechaEmision: string,
     *     cursosCompletos: string,
     *     mateAdeud: string,
     *     cursar: string,
     *     preAnte: string
     * }  $datos
     */
    public static function pdfPost(int $idLegajos, array $datos): array
    {
        return \App\Support\Pdf\PdfPost::datos(route('certificados.solicitudDePase.pdf'), [
            'idLegajos' => $idLegajos,
            'fechaEmision' => $datos['fechaEmision'],
            'cursosCompletos' => $datos['cursosCompletos'],
            'mateAdeud' => $datos['mateAdeud'],
            'cursar' => $datos['cursar'],
            'preAnte' => $datos['preAnte'],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function reglasFormulario(): array
    {
        return [
            'fechaEmision' => ['required', 'date'],
            'cursosCompletos' => ['nullable', 'string', 'max:5000'],
            'mateAdeud' => ['nullable', 'string', 'max:5000'],
            'cursar' => ['nullable', 'string', 'max:5000'],
            'preAnte' => ['required', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajesValidacion(): array
    {
        return [
            'fechaEmision.required' => 'La fecha de emisión es obligatoria.',
            'fechaEmision.date' => 'Fecha de emisión inválida.',
            'preAnte.required' => 'Indique ante qué autoridades se presenta el documento.',
            'preAnte.max' => 'El campo no puede superar 300 caracteres.',
        ];
    }
}
