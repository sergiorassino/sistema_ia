<?php

namespace App\Support\Certificados;

use App\Models\ConstDocu;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;

/**
 * Constancia de documentos — listado histórico, constdocu y URL del PDF.
 */
final class ConstanciaDocumentos
{
    /**
     * Legajos con al menos una matrícula en el nivel del contexto (cualquier ciclo).
     * El curso mostrado es el de la última matrícula (año más reciente).
     *
     * @return LengthAwarePaginator<int, array{
     *     idLegajos: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string
     * }>
     */
    public static function paginarAlumnos(int $idNivel, ?string $buscar, int $porPagina = 50): LengthAwarePaginator
    {
        if ($idNivel < 1) {
            return self::paginadorVacio();
        }

        $ultimaPorLegajo = DB::table('matricula as m')
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
            ->where('m.idNivel', $idNivel)
            ->select([
                'm.idLegajos',
                DB::raw('MAX(t.ano) as max_ano'),
            ])
            ->groupBy('m.idLegajos');

        $ultimaMatricula = DB::table('matricula as m')
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
            ->joinSub($ultimaPorLegajo, 'ul', function ($join) {
                $join->on('ul.idLegajos', '=', 'm.idLegajos')
                    ->on('ul.max_ano', '=', 't.ano');
            })
            ->where('m.idNivel', $idNivel)
            ->select([
                'm.idLegajos',
                DB::raw('MAX(m.id) as idMatricula'),
            ])
            ->groupBy('m.idLegajos');

        $q = DB::table('legajos as l')
            ->joinSub($ultimaMatricula, 'um', 'um.idLegajos', '=', 'l.id')
            ->join('matricula as m', 'm.id', '=', 'um.idMatricula')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
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
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
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
     * Alumno con matrícula histórica en el nivel (cualquier ciclo lectivo).
     *
     * @return array{
     *     idLegajos: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string,
     *     nroMatricula: string
     * }|null
     */
    public static function alumnoDelNivel(int $idLegajos, int $idNivel): ?array
    {
        if ($idLegajos < 1 || $idNivel < 1) {
            return null;
        }

        $row = DB::table('legajos as l')
            ->where('l.id', $idLegajos)
            ->first(['l.id', 'l.apellido', 'l.nombre', 'l.dni']);

        if ($row === null) {
            return null;
        }

        $ultima = DB::table('matricula as m')
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->where('m.idLegajos', $idLegajos)
            ->where('m.idNivel', $idNivel)
            ->orderByDesc('t.ano')
            ->orderByDesc('m.id')
            ->select([
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
                'm.nroMatricula',
            ])
            ->first();

        if ($ultima === null) {
            return null;
        }

        return [
            'idLegajos' => (int) $row->id,
            'apellido' => trim((string) ($row->apellido ?? '')),
            'nombre' => trim((string) ($row->nombre ?? '')),
            'dni' => trim((string) ($row->dni ?? '')),
            'curso' => self::cursoLabelDesdeFila($ultima),
            'nroMatricula' => trim((string) ($ultima->nroMatricula ?? '')),
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
        return new Paginator([], 0, 50);
    }
}
