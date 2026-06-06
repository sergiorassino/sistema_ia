<?php

namespace App\Support\Certificados;

use App\Models\CertEstuTram;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Constancia de certificado de estudios en trámite — listado, certestutram y URL del PDF.
 */
final class CertificadoEstudiosTramite
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
     *     curso: string,
     *     plan: string
     * }|null
     */
    public static function alumnoMatriculado(int $idLegajos, int $idNivel, int $idTerlec): ?array
    {
        $base = CertificadoAlumnoRegular::alumnoMatriculado($idLegajos, $idNivel, $idTerlec);
        if ($base === null) {
            return null;
        }

        $plan = self::planDesdeMatricula($idLegajos, $idNivel, $idTerlec);

        return [
            'idLegajos' => $base['idLegajos'],
            'apellido' => $base['apellido'],
            'nombre' => $base['nombre'],
            'dni' => $base['dni'],
            'curso' => $base['curso'],
            'plan' => $plan,
        ];
    }

    /**
     * @return array{
     *     mateAdeud: string,
     *     idiomaCursado: string,
     *     preAnte: string,
     *     fechaEmision: string
     * }
     */
    public static function valoresPorDefecto(): array
    {
        return [
            'mateAdeud' => '',
            'idiomaCursado' => '',
            'preAnte' => '',
            'fechaEmision' => now()->format('Y-m-d'),
        ];
    }

    /**
     * @return array{
     *     mateAdeud: string,
     *     idiomaCursado: string,
     *     preAnte: string,
     *     fechaEmision: string
     * }|null
     */
    public static function datosGuardados(int $idLegajos): ?array
    {
        if ($idLegajos < 1) {
            return null;
        }

        $row = CertEstuTram::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'mateAdeud' => trim((string) ($row->mateAdeud ?? '')),
            'idiomaCursado' => trim((string) ($row->idiomaCursado ?? '')),
            'preAnte' => trim((string) ($row->preAnte ?? '')),
            'fechaEmision' => $row->fechaEmision?->format('Y-m-d') ?? now()->format('Y-m-d'),
        ];
    }

    /**
     * @param  array{
     *     mateAdeud: string,
     *     idiomaCursado: string,
     *     preAnte: string,
     *     fechaEmision: string
     * }  $datos
     */
    public static function guardar(int $idLegajos, array $datos): bool
    {
        if ($idLegajos < 1) {
            return false;
        }

        $existente = CertEstuTram::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->first();

        $payload = [
            'mateAdeud' => $datos['mateAdeud'] !== '' ? $datos['mateAdeud'] : null,
            'idiomaCursado' => $datos['idiomaCursado'],
            'preAnte' => $datos['preAnte'],
            'fechaEmision' => $datos['fechaEmision'],
        ];

        if ($existente !== null) {
            $existente->fill($payload);
            $existente->save();

            return true;
        }

        CertEstuTram::query()->create(array_merge(['idLegajos' => $idLegajos], $payload));

        return true;
    }

    /**
     * @param  array{
     *     mateAdeud: string,
     *     idiomaCursado: string,
     *     preAnte: string,
     *     fechaEmision: string
     * }  $datos
     */
    public static function pdfPost(int $idLegajos, array $datos): array
    {
        return \App\Support\Pdf\PdfPost::datos(route('certificados.estudiosTramite.pdf'), [
            'idLegajos' => $idLegajos,
            'mateAdeud' => $datos['mateAdeud'],
            'idiomaCursado' => $datos['idiomaCursado'],
            'preAnte' => $datos['preAnte'],
            'fechaEmision' => $datos['fechaEmision'],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function reglasFormulario(): array
    {
        return [
            'mateAdeud' => ['nullable', 'string', 'max:5000'],
            'idiomaCursado' => ['required', 'string', 'max:100'],
            'preAnte' => ['required', 'string', 'max:300'],
            'fechaEmision' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajesValidacion(): array
    {
        return [
            'idiomaCursado.required' => 'Indique el idioma cursado.',
            'preAnte.required' => 'Indique ante qué autoridades se presenta la constancia.',
            'fechaEmision.required' => 'La fecha de emisión es obligatoria.',
            'fechaEmision.date' => 'Fecha de emisión inválida.',
        ];
    }

    private static function planDesdeMatricula(int $idLegajos, int $idNivel, int $idTerlec): string
    {
        $plan = \Illuminate\Support\Facades\DB::table('matricula as m')
            ->join('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('planes as pl', 'pl.id', '=', 'cp.idPlan')
            ->where('m.idLegajos', $idLegajos)
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->whereNull('m.fechaBaja')
            ->orderByDesc('m.id')
            ->value('pl.plan');

        return trim((string) ($plan ?? ''));
    }
}
