<?php

namespace App\Support\EmailsMasivos;

use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Facades\DB;

final class DestinatariosEmailsMasivos
{
    /** @return list<int> */
    public static function idsCondicionesRegulares(): array
    {
        return ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::REGULARES);
    }

    /**
     * @return list<array{id:int,label:string,dni:?string,idCurso:int}>
     */
    public static function buscarAlumnosRegulares(int $idNivel, int $idTerlec, string $termino, int $limit = 50): array
    {
        $t = trim($termino);
        if ($t === '') {
            return [];
        }

        $prefix = addcslashes($t, '%_\\') . '%';

        return self::queryMatriculaRegular($idNivel, $idTerlec)
            ->where(function ($w) use ($prefix, $t) {
                $w->where('l.apellido', 'like', $prefix)
                    ->orWhere('l.nombre', 'like', $prefix);
                if (ctype_digit($t)) {
                    $w->orWhere('l.dni', (int) $t);
                }
            })
            ->select(['l.id', 'l.apellido', 'l.nombre', 'l.dni', 'm.idCursos'])
            ->distinct()
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(fn ($r) => self::mapFilaAlumno($r))
            ->all();
    }

    /**
     * @return list<array{id:int,label:string,dni:?string,idCurso:int}>
     */
    public static function alumnosRegularesPorCurso(int $idNivel, int $idTerlec, int $idCurso): array
    {
        return self::queryMatriculaRegular($idNivel, $idTerlec)
            ->where('m.idCursos', $idCurso)
            ->select(['l.id', 'l.apellido', 'l.nombre', 'l.dni', 'm.idCursos'])
            ->distinct()
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->get()
            ->map(fn ($r) => self::mapFilaAlumno($r))
            ->all();
    }

    /**
     * @return array{idCurso:int}|null
     */
    public static function matriculaRegularDeLegajo(int $idNivel, int $idTerlec, int $idLegajo): ?array
    {
        $row = self::queryMatriculaRegular($idNivel, $idTerlec)
            ->where('m.idLegajos', $idLegajo)
            ->select(['m.idCursos'])
            ->first();

        if ($row === null) {
            return null;
        }

        return ['idCurso' => (int) $row->idCursos];
    }

    /**
     * @param  list<array{idLegajo:int,idCurso:int,label:string,cursoLabel:string,marcado:bool}>  $lineasMarcadas
     * @return list<array{
     *     email:string,
     *     tipo:string,
     *     idLegajo:int,
     *     idCurso:int,
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

    private static function queryMatriculaRegular(int $idNivel, int $idTerlec)
    {
        return DB::table('matricula as m')
            ->join('legajos as l', 'l.id', '=', 'm.idLegajos')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->whereIn('m.idCondiciones', self::idsCondicionesRegulares())
            ->whereNull('m.fechaBaja')
            ->whereNotNull('m.idLegajos');
    }

    /**
     * @return array{id:int,label:string,dni:?string,idCurso:int}
     */
    private static function mapFilaAlumno(object $r): array
    {
        return [
            'id' => (int) $r->id,
            'label' => trim((string) $r->apellido . ', ' . (string) $r->nombre),
            'dni' => $r->dni !== null ? (string) $r->dni : null,
            'idCurso' => (int) $r->idCursos,
        ];
    }
}
