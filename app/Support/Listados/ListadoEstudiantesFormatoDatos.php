<?php

namespace App\Support\Listados;

use App\Models\Ento;
use App\Support\Alumnos\FotoCarnetLegajo;
use App\Support\NivelSistema;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Alumnos regulares por curso para listados con formato.
 */
final class ListadoEstudiantesFormatoDatos
{
    /**
     * @param  list<int>  $cursoIds
     * @return list<array{cursoLabel: string, curso: string, seccion: string, alumnos: Collection<int, object>}>
     */
    public static function bloquesPorCursos(array $cursoIds): array
    {
        if ($cursoIds === []) {
            return [];
        }

        $ctx = schoolCtx();
        $cursosPermitidos = ListadoCursoConsulta::cursosPermitidosEnContexto();
        if ($cursosPermitidos->isEmpty()) {
            return [];
        }

        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES,
        );

        $query = DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->whereIn('matricula.idCursos', $cursoIds)
            ->whereIn('matricula.idCondiciones', $idsCondiciones)
            ->where('matricula.idTerlec', $ctx->idTerlec)
            ->whereNull('matricula.fechaBaja');

        ListadoCursoConsulta::aplicarFiltroMatriculaNivel($query);

        $columnas = [
            'matricula.idCursos as __id_curso',
            'legajos.apellido',
            'legajos.nombre',
            'legajos.nombremad',
            'legajos.nombrepad',
        ];
        if (FotoCarnetLegajo::columnaDisponible()) {
            $columnas[] = 'legajos.'.FotoCarnetLegajo::COLUMNA;
        }
        if (Schema::hasColumn('legajos', 'dni')) {
            $columnas[] = 'legajos.dni';
        }

        $filas = $query
            ->orderBy('matricula.idCursos')
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('legajos.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('legajos.nombre'))
            ->get($columnas);

        $porCurso = $filas->groupBy(fn ($r) => (int) $r->__id_curso);

        $bloques = [];
        foreach ($cursosPermitidos as $curso) {
            $id = (int) $curso->Id;
            if (! in_array($id, $cursoIds, true)) {
                continue;
            }
            $partes = ListadoFamiliasConsulta::cursoYSeccion($curso);
            $bloques[] = [
                'cursoLabel' => $curso->nombreParaListado(),
                'curso' => $partes['curso'],
                'seccion' => $partes['seccion'],
                'alumnos' => $porCurso->get($id, collect()),
            ];
        }

        return $bloques;
    }

    /**
     * @param  list<int>  $cursoIds
     */
    public static function contextoPdf(array $cursoIds): array
    {
        return [
            'bloques' => self::bloquesPorCursos($cursoIds),
            'nivelNombre' => SchoolAlcancePedagogico::etiquetaNivelParaInformes(),
            'ano' => schoolCtx()->terlecAno(),
            'pdfHeader' => self::resolverPdfHeader(),
        ];
    }

    /**
     * @return array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}
     */
    private static function resolverPdfHeader(): array
    {
        $header = schoolPdfHeaderData();
        if (trim((string) ($header['insti'] ?? '')) !== '') {
            return $header;
        }

        $ento = Ento::query()
            ->where('idNivel', '<', NivelSistema::ADMINISTRACION)
            ->orderBy('idNivel')
            ->first(['insti', 'direccion', 'localidad', 'cue', 'ee', 'logo_path']);

        if ($ento === null) {
            return $header;
        }

        $logoFile = null;
        $logoPath = trim((string) ($ento->logo_path ?? ''));
        if ($logoPath !== '') {
            $abs = Storage::disk('public')->path($logoPath);
            if (is_string($abs) && $abs !== '' && is_file($abs)) {
                $logoFile = $abs;
            }
        }

        return [
            'insti' => trim((string) ($ento->insti ?? '')),
            'direccion' => trim((string) ($ento->direccion ?? '')),
            'localidad' => trim((string) ($ento->localidad ?? '')),
            'cue' => trim((string) ($ento->cue ?? '')),
            'ee' => trim((string) ($ento->ee ?? '')),
            'logo_file' => $logoFile,
        ];
    }
}
