<?php

namespace App\Support\Certificados;

use App\Models\ConstDocu;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Constancia de documentos — listado, constdocu y URL del PDF.
 */
final class ConstanciaDocumentos
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
    public static function paginarAlumnos(int $idNivel, int $idTerlec, ?string $buscar, int $porPagina = 50): LengthAwarePaginator
    {
        return CertificadoAlumnoRegular::paginarAlumnos($idNivel, $idTerlec, $buscar, $porPagina);
    }

    /**
     * @return array{
     *     idLegajos: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string
     * }|null
     */
    public static function alumnoMatriculado(int $idLegajos, int $idNivel, int $idTerlec): ?array
    {
        return CertificadoAlumnoRegular::alumnoMatriculado($idLegajos, $idNivel, $idTerlec);
    }

    /**
     * @return array{
     *     certifde: string,
     *     otorpor: string,
     *     fechotor: string,
     *     parnacop: string,
     *     parapre: string,
     *     fechemis: string
     * }
     */
    public static function valoresPorDefecto(): array
    {
        return [
            'certifde' => '',
            'otorpor' => '',
            'fechotor' => '',
            'parnacop' => '',
            'parapre' => '',
            'fechemis' => now()->format('Y-m-d'),
        ];
    }

    /**
     * @return array{
     *     certifde: string,
     *     otorpor: string,
     *     fechotor: string,
     *     parnacop: string,
     *     parapre: string,
     *     fechemis: string
     * }|null
     */
    public static function datosGuardados(int $idLegajos): ?array
    {
        if ($idLegajos < 1) {
            return null;
        }

        $row = ConstDocu::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'certifde' => trim((string) ($row->certifde ?? '')),
            'otorpor' => trim((string) ($row->otorpor ?? '')),
            'fechotor' => $row->fechotor?->format('Y-m-d') ?? '',
            'parnacop' => trim((string) ($row->parnacop ?? '')),
            'parapre' => trim((string) ($row->parapre ?? '')),
            'fechemis' => $row->fechemis?->format('Y-m-d') ?? now()->format('Y-m-d'),
        ];
    }

    /**
     * @param  array{
     *     certifde: string,
     *     otorpor: string,
     *     fechotor: string,
     *     parnacop: string,
     *     parapre: string,
     *     fechemis: string
     * }  $datos
     */
    public static function guardar(int $idLegajos, array $datos): bool
    {
        if ($idLegajos < 1) {
            return false;
        }

        $existente = ConstDocu::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->first();

        $payload = [
            'certifde' => $datos['certifde'],
            'otorpor' => $datos['otorpor'],
            'fechotor' => $datos['fechotor'] !== '' ? $datos['fechotor'] : null,
            'parnacop' => $datos['parnacop'],
            'parapre' => $datos['parapre'],
            'fechemis' => $datos['fechemis'],
        ];

        if ($existente !== null) {
            $existente->fill($payload);
            $existente->save();

            return true;
        }

        ConstDocu::query()->create(array_merge(['idLegajos' => $idLegajos], $payload));

        return true;
    }

    /**
     * @param  array{
     *     certifde: string,
     *     otorpor: string,
     *     fechotor: string,
     *     parnacop: string,
     *     parapre: string,
     *     fechemis: string
     * }  $datos
     */
    public static function pdfPost(int $idLegajos, array $datos): array
    {
        return \App\Support\Pdf\PdfPost::datos(route('certificados.constanciaDocumentos.pdf'), [
            'idLegajos' => $idLegajos,
            'certifde' => $datos['certifde'],
            'otorpor' => $datos['otorpor'],
            'fechotor' => $datos['fechotor'],
            'parnacop' => $datos['parnacop'],
            'parapre' => $datos['parapre'],
            'fechemis' => $datos['fechemis'],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function reglasFormulario(): array
    {
        return [
            'certifde' => ['required', 'string', 'max:300'],
            'otorpor' => ['required', 'string', 'max:300'],
            'fechotor' => ['required', 'date'],
            'parnacop' => ['required', 'string', 'max:300'],
            'parapre' => ['required', 'string', 'max:300'],
            'fechemis' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajesValidacion(): array
    {
        return [
            'certifde.required' => 'Indique el certificado de grado (ej. PRIMARIO, SECUNDARIO).',
            'otorpor.required' => 'Indique quién otorgó el certificado.',
            'fechotor.required' => 'La fecha de otorgamiento del certificado es obligatoria.',
            'fechotor.date' => 'Fecha de otorgamiento inválida.',
            'parnacop.required' => 'Indique quién otorgó la partida de nacimiento.',
            'parapre.required' => 'Indique ante qué autoridades se presenta la constancia.',
            'fechemis.required' => 'La fecha de emisión es obligatoria.',
            'fechemis.date' => 'Fecha de emisión inválida.',
        ];
    }
}
