<?php

namespace App\Support\EmailsMasivos;

use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\Listados\ListadoCursoConsulta;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Support\Facades\DB;

final class DestinatariosEmailsMasivos
{
    /** @return list<int> */
    public static function idsCondicionesRegulares(): array
    {
        return ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::REGULARES);
    }

    /**
     * Cursos del ciclo activo según alcance de sesión (Admin = todos los pedagógicos).
     *
     * @return list<array{id:int,label:string,idNivel:int}>
     */
    public static function cursosDelContexto(int $idTerlec): array
    {
        return ListadoCursoConsulta::cursosPermitidosEnContexto()
            ->filter(fn ($c) => (int) $c->idTerlec === $idTerlec)
            ->map(fn ($c) => [
                'id' => (int) $c->Id,
                'label' => ListadoCursoConsulta::etiquetaCursoConNivel($c),
                'idNivel' => (int) $c->idNivel,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,label:string,dni:?string,idCurso:int,idNivel:int,nivelLabel:string,cursoLabel:string}>
     */
    public static function buscarAlumnosRegulares(int $idTerlec, string $termino, int $limit = 80): array
    {
        $t = trim($termino);
        if ($t === '') {
            return [];
        }

        $limit = max(1, min(100, $limit));

        $query = self::queryMatriculaRegular($idTerlec)
            ->leftJoin('niveles as n', 'n.id', '=', 'm.idNivel')
            ->leftJoin('cursos as c', 'c.Id', '=', 'm.idCursos');

        self::aplicarFiltroBusquedaLegajo($query, $t);

        $rows = $query
            ->select([
                'l.id',
                'l.apellido',
                'l.nombre',
                'l.dni',
                'm.idCursos',
                'm.idNivel',
                'n.nivel as nivelNombre',
                'n.abrev as nivelAbrev',
                'c.cursec',
            ])
            ->orderBy('m.idNivel')
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'))
            ->orderBy('m.idCursos')
            ->limit($limit * 3)
            ->get();

        $vistos = [];
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->id;
            if (isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $out[] = self::mapFilaAlumno($r);
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array{id:int,label:string,dni:?string,idCurso:int,idNivel:int,nivelLabel:string,cursoLabel:string}>
     */
    public static function alumnosRegularesPorCurso(int $idTerlec, int $idCurso): array
    {
        return self::queryMatriculaRegular($idTerlec)
            ->leftJoin('niveles as n', 'n.id', '=', 'm.idNivel')
            ->leftJoin('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->where('m.idCursos', $idCurso)
            ->select([
                'l.id',
                'l.apellido',
                'l.nombre',
                'l.dni',
                'm.idCursos',
                'm.idNivel',
                'n.nivel as nivelNombre',
                'n.abrev as nivelAbrev',
                'c.cursec',
            ])
            ->distinct()
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'))
            ->get()
            ->map(fn ($r) => self::mapFilaAlumno($r))
            ->all();
    }

    /**
     * @return array{idCurso:int,idNivel:int}|null
     */
    public static function matriculaRegularDeLegajo(int $idTerlec, int $idLegajo): ?array
    {
        $row = self::queryMatriculaRegular($idTerlec)
            ->where('m.idLegajos', $idLegajo)
            ->select(['m.idCursos', 'm.idNivel'])
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'idCurso' => (int) $row->idCursos,
            'idNivel' => (int) $row->idNivel,
        ];
    }

    /**
     * @param  list<array{idLegajo:int,idCurso:int,idNivel?:int,label:string,cursoLabel:string,marcado:bool}>  $lineasMarcadas
     * @return list<array{
     *     email:string,
     *     tipo:string,
     *     idLegajo:int,
     *     idCurso:int,
     *     idNivel:int,
     *     alumnoLabel:string,
     *     cursoLabel:string
     * }>
     */
    public static function resolverDestinatariosEnvio(
        array $lineasMarcadas,
        string $modoContacto,
        bool $incluirMadre,
        bool $incluirPadre,
        bool $incluirTutor,
    ): array {
        if ($lineasMarcadas === []) {
            return [];
        }

        $idsLegajo = [];
        foreach ($lineasMarcadas as $linea) {
            if (empty($linea['marcado'])) {
                continue;
            }
            $idsLegajo[] = (int) $linea['idLegajo'];
        }
        $idsLegajo = array_values(array_unique(array_filter($idsLegajo)));

        if ($idsLegajo === []) {
            return [];
        }

        $legajos = DB::table('legajos')
            ->whereIn('id', $idsLegajo)
            ->get(['id', 'apellido', 'nombre', 'emailmad', 'emailpad', 'emailtut']);

        $legajosPorId = $legajos->keyBy('id');
        $out = [];

        foreach ($lineasMarcadas as $linea) {
            if (empty($linea['marcado'])) {
                continue;
            }
            $idLegajo = (int) $linea['idLegajo'];
            $legajo = $legajosPorId->get($idLegajo);
            if ($legajo === null) {
                continue;
            }

            $emails = self::emailsParaLegajo(
                $legajo,
                $modoContacto,
                $incluirMadre,
                $incluirPadre,
                $incluirTutor,
            );

            foreach ($emails as $item) {
                $out[] = [
                    'email' => $item['email'],
                    'tipo' => $item['tipo'],
                    'idLegajo' => $idLegajo,
                    'idCurso' => (int) $linea['idCurso'],
                    'idNivel' => (int) ($linea['idNivel'] ?? 0),
                    'alumnoLabel' => (string) $linea['label'],
                    'cursoLabel' => (string) ($linea['cursoLabel'] ?? ''),
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<array{email:string,tipo:string}>
     */
    private static function emailsParaLegajo(
        object $legajo,
        string $modoContacto,
        bool $incluirMadre,
        bool $incluirPadre,
        bool $incluirTutor,
    ): array {
        $map = [
            'madre' => self::normalizarEmail($legajo->emailmad ?? null),
            'padre' => self::normalizarEmail($legajo->emailpad ?? null),
            'tutor' => self::normalizarEmail($legajo->emailtut ?? null),
        ];

        if ($modoContacto === 'prioridad') {
            foreach (['madre', 'padre', 'tutor'] as $tipo) {
                if ($map[$tipo] !== null) {
                    return [['email' => $map[$tipo], 'tipo' => $tipo]];
                }
            }

            return [];
        }

        $out = [];
        if ($incluirMadre && $map['madre'] !== null) {
            $out[] = ['email' => $map['madre'], 'tipo' => 'madre'];
        }
        if ($incluirPadre && $map['padre'] !== null) {
            $out[] = ['email' => $map['padre'], 'tipo' => 'padre'];
        }
        if ($incluirTutor && $map['tutor'] !== null) {
            $out[] = ['email' => $map['tutor'], 'tipo' => 'tutor'];
        }

        return $out;
    }

    public static function normalizarEmail(mixed $email): ?string
    {
        $e = mb_strtoupper(trim((string) ($email ?? '')));
        if ($e === '' || ! filter_var($e, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $e;
    }

    /**
     * @return list<string>
     */
    public static function parseAttached(string $attached): array
    {
        $attached = trim($attached);
        if ($attached === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('|', $attached))));
    }

    private static function queryMatriculaRegular(int $idTerlec)
    {
        $query = DB::table('matricula as m')
            ->join('legajos as l', 'l.id', '=', 'm.idLegajos')
            ->where('m.idTerlec', $idTerlec)
            ->whereIn('m.idCondiciones', self::idsCondicionesRegulares())
            ->where(function ($w) {
                $w->whereNull('m.fechaBaja')
                    ->orWhere('m.fechaBaja', '0000-00-00')
                    ->orWhere('m.fechaBaja', '');
            })
            ->whereNotNull('m.idLegajos');

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'm.idNivel');

        return $query;
    }

    /**
     * Misma lógica que {@see \App\Models\Legajo::scopeBuscar()} sobre alias `l`.
     */
    private static function aplicarFiltroBusquedaLegajo($query, string $termino): void
    {
        $termino = trim($termino);
        if ($termino === '') {
            return;
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $termino) . '%';
        $palabras = preg_split('/\s+/u', $termino, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $query->where(function ($q) use ($like, $palabras) {
            $q->where('l.apellido', 'like', $like)
                ->orWhere('l.nombre', 'like', $like)
                ->orWhere('l.dni', 'like', $like)
                ->orWhereRaw("CONCAT(l.apellido, ' ', l.nombre) LIKE ?", [$like])
                ->orWhereRaw("CONCAT(l.apellido, ', ', l.nombre) LIKE ?", [$like]);

            if (count($palabras) >= 2) {
                $apellido = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $palabras[0]) . '%';
                $nombre = '%' . str_replace(['%', '_'], ['\\%', '\\_'], implode(' ', array_slice($palabras, 1))) . '%';

                $q->orWhere(function ($sub) use ($apellido, $nombre) {
                    $sub->where('l.apellido', 'like', $apellido)
                        ->where('l.nombre', 'like', $nombre);
                });

                $q->orWhere(function ($sub) use ($apellido, $nombre) {
                    $sub->where('l.nombre', 'like', $apellido)
                        ->where('l.apellido', 'like', $nombre);
                });
            }
        });
    }

    /**
     * @return array{id:int,label:string,dni:?string,idCurso:int,idNivel:int,nivelLabel:string,cursoLabel:string}
     */
    private static function mapFilaAlumno(object $r): array
    {
        $nivel = trim((string) ($r->nivelAbrev ?? ''));
        if ($nivel === '') {
            $nivel = trim((string) ($r->nivelNombre ?? ''));
        }
        $curso = trim((string) ($r->cursec ?? ''));

        return [
            'id' => (int) $r->id,
            'label' => trim((string) $r->apellido . ', ' . (string) $r->nombre),
            'dni' => $r->dni !== null ? (string) $r->dni : null,
            'idCurso' => (int) $r->idCursos,
            'idNivel' => (int) ($r->idNivel ?? 0),
            'nivelLabel' => $nivel,
            'cursoLabel' => $curso,
        ];
    }
}
