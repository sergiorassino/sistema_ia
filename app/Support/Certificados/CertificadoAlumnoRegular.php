<?php

namespace App\Support\Certificados;

use App\Models\CertAluReg;
use App\Models\Terlec;
use App\Support\CalificacionesSecundario\CierreAnualSecundario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Certificado de alumno/a regular — listado, persistencia en certalureg y URL del PDF.
 */
final class CertificadoAlumnoRegular
{
    public const TIPO_LABORAL = 'laboral';

    public const TIPO_ESCOLAR = 'escolar';

    public const INI_FIN_INICIO = 1;

    public const INI_FIN_FIN = 2;

    /** @return list<string> */
    public static function tiposValidos(): array
    {
        return [self::TIPO_LABORAL, self::TIPO_ESCOLAR];
    }

    public static function esTipoValido(string $tipo): bool
    {
        return in_array($tipo, self::tiposValidos(), true);
    }

    public static function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_LABORAL => 'Certificado de Alumno Regular (LABORAL)',
            self::TIPO_ESCOLAR => 'Certificado de Alumno Regular (ESCOLAR)',
            default => '',
        };
    }

    /**
     * Matrículas activas del ciclo lectivo y nivel del contexto.
     *
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
        if ($idNivel < 1 || $idTerlec < 1) {
            return self::paginadorVacio();
        }

        $q = DB::table('matricula as m')
            ->join('legajos as l', 'l.id', '=', 'm.idLegajos')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->whereIn('m.idCondiciones', CierreAnualSecundario::idsCondicionesMatricula())
            ->whereNull('m.fechaBaja')
            ->select([
                'l.id as idLegajos',
                'l.apellido',
                'l.nombre',
                'l.dni',
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
            ])
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'))
            ->orderBy('l.id');

        $termino = self::normalizarBusqueda($buscar);
        if ($termino !== '') {
            $like = '%'.$termino.'%';
            $q->where(function ($w) use ($like) {
                $w->where('l.apellido', 'like', $like)
                    ->orWhere('l.nombre', 'like', $like)
                    ->orWhere('l.dni', 'like', $like);
            });
        }

        return $q->paginate(max(10, min(100, $porPagina)))
            ->through(static function (object $r): array {
                return [
                    'idLegajos' => (int) $r->idLegajos,
                    'apellido' => trim((string) ($r->apellido ?? '')),
                    'nombre' => trim((string) ($r->nombre ?? '')),
                    'dni' => trim((string) ($r->dni ?? '')),
                    'curso' => self::cursoLabelDesdeFila($r),
                ];
            });
    }

    /**
     * @return array{
     *     idLegajos: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string,
     *     anoLectivo: int
     * }|null
     */
    public static function alumnoMatriculado(int $idLegajos, int $idNivel, int $idTerlec): ?array
    {
        if ($idLegajos < 1 || $idNivel < 1 || $idTerlec < 1) {
            return null;
        }

        $row = DB::table('matricula as m')
            ->join('legajos as l', 'l.id', '=', 'm.idLegajos')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->where('m.idLegajos', $idLegajos)
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->whereIn('m.idCondiciones', CierreAnualSecundario::idsCondicionesMatricula())
            ->whereNull('m.fechaBaja')
            ->orderByDesc('m.id')
            ->select([
                'l.id as idLegajos',
                'l.apellido',
                'l.nombre',
                'l.dni',
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        $ano = (int) (Terlec::query()->where('id', $idTerlec)->value('ano') ?? 0);

        return [
            'idLegajos' => (int) $row->idLegajos,
            'apellido' => trim((string) ($row->apellido ?? '')),
            'nombre' => trim((string) ($row->nombre ?? '')),
            'dni' => trim((string) ($row->dni ?? '')),
            'curso' => self::cursoLabelDesdeFila($row),
            'anoLectivo' => $ano,
        ];
    }

    /**
     * @return array{
     *     iniFin: int,
     *     fechIniFin: string,
     *     prePor: string,
     *     prePorDni: string,
     *     preAnte: string,
     *     fechaEmision: string
     * }
     */
    public static function valoresPorDefecto(): array
    {
        $hoy = now()->format('Y-m-d');

        return [
            'iniFin' => self::INI_FIN_INICIO,
            'fechIniFin' => $hoy,
            'prePor' => '',
            'prePorDni' => '',
            'preAnte' => '',
            'fechaEmision' => $hoy,
        ];
    }

    /**
     * @return array{
     *     iniFin: int,
     *     fechIniFin: string,
     *     prePor: string,
     *     prePorDni: string,
     *     preAnte: string,
     *     fechaEmision: string
     * }|null
     */
    public static function ultimoGuardado(int $idLegajos): ?array
    {
        if ($idLegajos < 1) {
            return null;
        }

        $row = CertAluReg::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'iniFin' => (int) ($row->iniFin ?? self::INI_FIN_INICIO),
            'fechIniFin' => $row->fechIniFin?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'prePor' => trim((string) ($row->prePor ?? '')),
            'prePorDni' => trim((string) ($row->prePorDni ?? '')),
            'preAnte' => trim((string) ($row->preAnte ?? '')),
            'fechaEmision' => $row->fechaEmision?->format('Y-m-d') ?? now()->format('Y-m-d'),
        ];
    }

    /**
     * @param  array{
     *     iniFin: int,
     *     fechIniFin: string,
     *     prePor: string,
     *     prePorDni: string,
     *     preAnte: string,
     *     fechaEmision: string
     * }  $datos
     */
    public static function guardar(int $idLegajos, array $datos): bool
    {
        if ($idLegajos < 1) {
            return false;
        }

        CertAluReg::query()->create([
            'idLegajos' => $idLegajos,
            'iniFin' => (int) $datos['iniFin'],
            'fechIniFin' => $datos['fechIniFin'],
            'prePor' => $datos['prePor'] !== '' ? $datos['prePor'] : null,
            'prePorDni' => $datos['prePorDni'] !== '' ? $datos['prePorDni'] : null,
            'preAnte' => $datos['preAnte'] !== '' ? $datos['preAnte'] : null,
            'fechaEmision' => $datos['fechaEmision'],
        ]);

        return true;
    }

    /**
     * Completa columnas de `certalureg` cuando el modelo escolar no las pide en el formulario.
     *
     * @param  array<string, mixed>  $datos
     * @return array{
     *     iniFin: int,
     *     fechIniFin: string,
     *     prePor: string,
     *     prePorDni: string,
     *     preAnte: string,
     *     fechaEmision: string
     * }
     */
    public static function completarParaGuardar(array $datos, string $tipo): array
    {
        if ($tipo === self::TIPO_ESCOLAR) {
            $fechaEmision = (string) ($datos['fechaEmision'] ?? now()->format('Y-m-d'));

            return [
                'iniFin' => self::INI_FIN_INICIO,
                'fechIniFin' => $fechaEmision,
                'prePor' => '',
                'prePorDni' => '',
                'preAnte' => trim((string) ($datos['preAnte'] ?? '')),
                'fechaEmision' => $fechaEmision,
            ];
        }

        return [
            'iniFin' => (int) ($datos['iniFin'] ?? self::INI_FIN_INICIO),
            'fechIniFin' => (string) ($datos['fechIniFin'] ?? now()->format('Y-m-d')),
            'prePor' => trim((string) ($datos['prePor'] ?? '')),
            'prePorDni' => trim((string) ($datos['prePorDni'] ?? '')),
            'preAnte' => trim((string) ($datos['preAnte'] ?? '')),
            'fechaEmision' => (string) ($datos['fechaEmision'] ?? now()->format('Y-m-d')),
        ];
    }

    /**
     * @param  array{
     *     iniFin?: int,
     *     fechIniFin?: string,
     *     prePor?: string,
     *     prePorDni?: string,
     *     preAnte: string,
     *     fechaEmision: string
     * }  $datos
     * @return array{action: string, fields: array<string, mixed>}
     */
    public static function pdfPost(int $idLegajos, array $datos, string $tipo = self::TIPO_LABORAL): array
    {
        $fields = [
            'idLegajos' => $idLegajos,
            'tipo' => $tipo,
            'preAnte' => $datos['preAnte'],
            'fechaEmision' => $datos['fechaEmision'],
        ];

        if ($tipo !== self::TIPO_ESCOLAR) {
            $fields['iniFin'] = (int) ($datos['iniFin'] ?? self::INI_FIN_INICIO);
            $fields['fechIniFin'] = $datos['fechIniFin'] ?? now()->format('Y-m-d');
            $fields['prePor'] = $datos['prePor'] ?? '';
            $fields['prePorDni'] = $datos['prePorDni'] ?? '';
        }

        return \App\Support\Pdf\PdfPost::datos(route('certificados.alumnoRegular.pdf'), $fields);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function reglasFormulario(string $tipo = self::TIPO_LABORAL): array
    {
        $comunes = [
            'preAnte' => ['required', 'string', 'max:300'],
            'fechaEmision' => ['required', 'date'],
        ];

        if ($tipo === self::TIPO_ESCOLAR) {
            return $comunes;
        }

        return array_merge([
            'iniFin' => ['required', 'integer', 'in:'.self::INI_FIN_INICIO.','.self::INI_FIN_FIN],
            'fechIniFin' => ['required', 'date'],
            'prePor' => ['required', 'string', 'max:300'],
            'prePorDni' => ['required', 'string', 'max:10'],
        ], $comunes);
    }

    /**
     * @return array<string, string>
     */
    public static function mensajesValidacion(): array
    {
        return [
            'iniFin.required' => 'Indique si el certificado es de inicio o fin de año.',
            'iniFin.in' => 'Tipo de certificado no válido.',
            'fechIniFin.required' => 'La fecha de inicio o fin es obligatoria.',
            'fechIniFin.date' => 'Fecha de inicio o fin inválida.',
            'prePor.required' => 'Indique quién presenta el certificado.',
            'prePorDni.required' => 'El DNI de quien presenta es obligatorio.',
            'preAnte.required' => 'Indique ante quién se presenta el certificado.',
            'fechaEmision.required' => 'La fecha de emisión es obligatoria.',
            'fechaEmision.date' => 'Fecha de emisión inválida.',
        ];
    }

    public static function normalizarBusqueda(?string $buscar): string
    {
        $t = trim((string) $buscar);
        if ($t === '') {
            return '';
        }

        return mb_strtolower($t, 'UTF-8');
    }

    private static function cursoLabelDesdeFila(object $r): string
    {
        $sec = trim((string) ($r->cursec ?? ''));
        if ($sec !== '') {
            return $sec;
        }

        $nombrePlan = trim((string) ($r->curPlanCurso ?? ''));
        $extras = collect([$r->turnoClaseNombre ?? '', $r->c ?? '', $r->s ?? ''])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();

        if ($nombrePlan !== '') {
            return $extras->isNotEmpty()
                ? $nombrePlan.' · '.$extras->implode(' · ')
                : $nombrePlan;
        }

        if ($extras->isNotEmpty()) {
            return $extras->implode(' · ');
        }

        return '';
    }

    /** @return LengthAwarePaginator<int, never> */
    private static function paginadorVacio(): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);
    }
}
