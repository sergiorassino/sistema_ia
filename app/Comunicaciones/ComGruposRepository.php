<?php

namespace App\Comunicaciones;

use App\Models\ComGrupo;
use App\Models\ComGrupoMiembro;
use App\Push\DestinatariosRepository;
use App\Support\Comunicaciones\ComCanalRolCatalog;
use App\Support\Database\PersistenciaColumnas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ComGruposRepository
{
    public static function tablasDisponibles(): bool
    {
        return Schema::hasTable('com_grupos') && Schema::hasTable('com_grupos_miembros');
    }

    public static function mensajeTablasFaltantes(): string
    {
        return 'Faltan las tablas de grupos de comunicación (com_grupos / com_grupos_miembros). '
            .'Ejecute el SQL de creación o la migración correspondiente.';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ComGrupo>
     */
    public static function queryDelDueno(int $idProfesor, int $idNivel)
    {
        return ComGrupo::query()
            ->where('id_profesor', $idProfesor)
            ->where('id_nivel', $idNivel);
    }

    public static function scopedFind(int $idGrupo, int $idProfesor, int $idNivel): ?ComGrupo
    {
        return self::queryDelDueno($idProfesor, $idNivel)->whereKey($idGrupo)->first();
    }

    public static function scopedOrFail(int $idGrupo, int $idProfesor, int $idNivel): ComGrupo
    {
        $grupo = self::scopedFind($idGrupo, $idProfesor, $idNivel);
        abort_unless($grupo !== null, 404, 'Grupo no encontrado.');

        return $grupo;
    }

    /**
     * @return list<array{id:int,label:string,miembros:int}>
     */
    public static function paraSelector(int $idProfesor, int $idNivel): array
    {
        if (! self::tablasDisponibles()) {
            return [];
        }

        $rows = self::queryDelDueno($idProfesor, $idNivel)
            ->withCount('miembros')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $out = [];
        foreach ($rows as $g) {
            $out[] = [
                'id'       => (int) $g->id,
                'label'    => (string) $g->nombre,
                'miembros' => (int) ($g->miembros_count ?? 0),
            ];
        }

        return $out;
    }

    /**
     * IDs de legajo del grupo que siguen matriculados en el nivel/ciclo.
     *
     * @return list<int>
     */
    public static function idsLegajosMatriculadosDelGrupo(
        int $idGrupo,
        int $idProfesor,
        int $idNivel,
        int $idTerlec
    ): array {
        if (! self::tablasDisponibles()) {
            return [];
        }
        $grupo = self::scopedFind($idGrupo, $idProfesor, $idNivel);
        if ($grupo === null) {
            return [];
        }

        $ids = $grupo->miembros()
            ->where('tipo_miembro', ComGrupoMiembro::TIPO_LEGAJO)
            ->whereNotNull('id_legajo')
            ->pluck('id_legajo')
            ->map(static fn ($id) => (int) $id)
            ->all();

        return self::filtrarLegajosMatriculados($ids, $idNivel, $idTerlec);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function filtrarLegajosMatriculados(array $ids, int $idNivel, int $idTerlec): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $matriculados = array_map('intval', DestinatariosRepository::alumnosDelColegio($idNivel, $idTerlec));
        $set = array_flip($matriculados);

        return array_values(array_filter($ids, static fn (int $id) => isset($set[$id])));
    }

    /**
     * IDs de profesores del grupo que siguen en el nivel.
     *
     * @return list<int>
     */
    public static function idsProfesoresVigentesDelGrupo(
        int $idGrupo,
        int $idProfesorDueno,
        int $idNivel
    ): array {
        if (! self::tablasDisponibles()) {
            return [];
        }
        $grupo = self::scopedFind($idGrupo, $idProfesorDueno, $idNivel);
        if ($grupo === null) {
            return [];
        }

        $ids = $grupo->miembros()
            ->where('tipo_miembro', ComGrupoMiembro::TIPO_PROFESOR)
            ->whereNotNull('id_profesor')
            ->pluck('id_profesor')
            ->map(static fn ($id) => (int) $id)
            ->all();

        return ComunicacionesRepository::filtrarIdsProfesoresDelNivel($ids, $idNivel);
    }

    /**
     * Expande grupos a destinatarios vigentes (matrícula / personal del nivel).
     *
     * @param  list<int>  $idsGrupos
     * @return array{legajos: list<int>, profesores: list<int>}
     */
    public static function expandirParaEnvio(
        array $idsGrupos,
        int $idProfesor,
        int $idNivel,
        int $idTerlec
    ): array {
        $legajos = [];
        $profesores = [];
        foreach (array_unique(array_map('intval', $idsGrupos)) as $idGrupo) {
            if ($idGrupo <= 0) {
                continue;
            }
            foreach (self::idsLegajosMatriculadosDelGrupo($idGrupo, $idProfesor, $idNivel, $idTerlec) as $id) {
                $legajos[$id] = true;
            }
            foreach (self::idsProfesoresVigentesDelGrupo($idGrupo, $idProfesor, $idNivel) as $id) {
                $profesores[$id] = true;
            }
        }

        return [
            'legajos'    => array_keys($legajos),
            'profesores' => array_keys($profesores),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string> id_profesor => clave canal (`tipo:{id}`)
     */
    public static function clavesCanalPorIdsProfesores(array $ids, int $idNivel): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('profesores as p')
            ->leftJoin('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')
            ->where('p.nivel', $idNivel)
            ->whereIn('p.id', $ids)
            ->get(['p.id', 'p.IdTipoProf', 'pt.tipo']);

        $out = [];
        foreach ($rows as $r) {
            $idTipo = (int) ($r->IdTipoProf ?? 0);
            $clave = ComCanalRolCatalog::claveDeIdTipoProf($idTipo);
            if ($clave === null) {
                continue;
            }
            $out[(int) $r->id] = $clave;
        }

        return $out;
    }

    /**
     * @return list<array{tipo:string,id:int,label:string,dni:?string,rol_label:?string}>
     */
    public static function miembrosParaEdicion(ComGrupo $grupo): array
    {
        $miembros = $grupo->miembros()->orderBy('id')->get();
        if ($miembros->isEmpty()) {
            return [];
        }

        $idsLegajo = [];
        $idsProf = [];
        foreach ($miembros as $m) {
            if ($m->tipo_miembro === ComGrupoMiembro::TIPO_LEGAJO) {
                $idsLegajo[] = (int) $m->id_legajo;
            } elseif ($m->tipo_miembro === ComGrupoMiembro::TIPO_PROFESOR) {
                $idsProf[] = (int) $m->id_profesor;
            }
        }

        $nombresAlu = DestinatariosRepository::nombresPorUserKeys(array_map('strval', array_filter($idsLegajo)));
        $profes = self::nombresProfesoresConRolPorIds($idsProf);

        $out = [];
        foreach ($miembros as $m) {
            if ($m->tipo_miembro === ComGrupoMiembro::TIPO_LEGAJO) {
                $id = (int) $m->id_legajo;
                if ($id <= 0) {
                    continue;
                }
                $label = $nombresAlu[(string) $id] ?? trim((string) ($m->nombre_snapshot ?? ''));
                $out[] = [
                    'tipo'      => ComGrupoMiembro::TIPO_LEGAJO,
                    'id'        => $id,
                    'label'     => $label !== '' ? $label : ('Legajo '.$id),
                    'dni'       => null,
                    'rol_label' => 'Estudiante',
                ];

                continue;
            }

            $id = (int) $m->id_profesor;
            if ($id <= 0) {
                continue;
            }
            $info = $profes[$id] ?? null;
            $label = $info['label'] ?? trim((string) ($m->nombre_snapshot ?? ''));
            $out[] = [
                'tipo'      => ComGrupoMiembro::TIPO_PROFESOR,
                'id'        => $id,
                'label'     => $label !== '' ? $label : ('Usuario '.$id),
                'dni'       => null,
                'rol_label' => $info['rol_label'] ?? 'Personal',
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public static function nombresProfesoresPorIds(array $ids): array
    {
        $conRol = self::nombresProfesoresConRolPorIds($ids);
        $out = [];
        foreach ($conRol as $id => $info) {
            $out[$id] = $info['label'];
        }

        return $out;
    }

    /**
     * Nombres de integrantes para la grilla (página actual), con lookup en lote.
     *
     * @param  iterable<int, ComGrupo>  $grupos
     * @return array<int, list<string>>
     */
    public static function etiquetasMiembrosPorGrupos(iterable $grupos): array
    {
        $idsLegajo = [];
        $idsProf = [];
        foreach ($grupos as $grupo) {
            foreach ($grupo->miembros as $m) {
                if ($m->tipo_miembro === ComGrupoMiembro::TIPO_LEGAJO) {
                    $id = (int) $m->id_legajo;
                    if ($id > 0) {
                        $idsLegajo[] = $id;
                    }
                } elseif ($m->tipo_miembro === ComGrupoMiembro::TIPO_PROFESOR) {
                    $id = (int) $m->id_profesor;
                    if ($id > 0) {
                        $idsProf[] = $id;
                    }
                }
            }
        }

        $nombresAlu = DestinatariosRepository::nombresPorUserKeys(array_map('strval', array_values(array_unique($idsLegajo))));
        $nombresProf = self::nombresProfesoresPorIds($idsProf);

        $out = [];
        foreach ($grupos as $grupo) {
            $nombres = [];
            foreach ($grupo->miembros as $m) {
                $snap = trim((string) ($m->nombre_snapshot ?? ''));
                if ($m->tipo_miembro === ComGrupoMiembro::TIPO_LEGAJO) {
                    $id = (int) $m->id_legajo;
                    $label = $nombresAlu[(string) $id] ?? $snap;
                } else {
                    $id = (int) $m->id_profesor;
                    $label = $nombresProf[$id] ?? $snap;
                }
                $label = trim((string) $label);
                if ($label !== '') {
                    $nombres[] = $label;
                }
            }
            natcasesort($nombres);
            $out[(int) $grupo->id] = array_values($nombres);
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{label:string,rol_label:string}>
     */
    public static function nombresProfesoresConRolPorIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('profesores as p')
            ->leftJoin('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')
            ->whereIn('p.id', $ids)
            ->get(['p.id', 'p.apellido', 'p.nombre', 'pt.tipo']);

        $out = [];
        foreach ($rows as $r) {
            $rol = trim((string) ($r->tipo ?? ''));
            $out[(int) $r->id] = [
                'label'     => trim((string) $r->apellido.', '.(string) $r->nombre),
                'rol_label' => $rol !== '' ? $rol : 'Personal',
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{tipo?:string,id:int,label?:string}>  $miembros
     * @return array{ok:bool,grupo:?ComGrupo,error:?string}
     */
    public static function guardar(
        ?int $idGrupo,
        int $idProfesor,
        int $idNivel,
        int $idTerlec,
        string $nombre,
        array $miembros
    ): array {
        if (! self::tablasDisponibles()) {
            return ['ok' => false, 'grupo' => null, 'error' => self::mensajeTablasFaltantes()];
        }

        $nombre = trim($nombre);
        $legajos = [];
        $profes = [];
        foreach ($miembros as $m) {
            $id = (int) ($m['id'] ?? 0);
            $tipo = (string) ($m['tipo'] ?? '');
            $label = trim((string) ($m['label'] ?? ''));
            if ($id <= 0) {
                continue;
            }
            if ($tipo === ComGrupoMiembro::TIPO_LEGAJO) {
                $legajos[$id] = $label;
            } elseif ($tipo === ComGrupoMiembro::TIPO_PROFESOR) {
                $profes[$id] = $label;
            }
        }

        if ($nombre === '' || ($legajos === [] && $profes === [])) {
            return ['ok' => false, 'grupo' => null, 'error' => 'Indique el nombre y al menos un integrante.'];
        }

        $duplicado = self::queryDelDueno($idProfesor, $idNivel)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->when($idGrupo !== null, static fn ($q) => $q->where('id', '!=', $idGrupo))
            ->exists();
        if ($duplicado) {
            return ['ok' => false, 'grupo' => null, 'error' => 'Ya tiene un grupo con ese nombre en este nivel.'];
        }

        if ($legajos !== []) {
            $idsValidos = self::filtrarLegajosMatriculados(array_keys($legajos), $idNivel, $idTerlec);
            $legajos = array_intersect_key($legajos, array_flip($idsValidos));
        }
        if ($profes !== []) {
            $idsValidos = ComunicacionesRepository::filtrarIdsProfesoresDelNivel(array_keys($profes), $idNivel);
            $idsValidos = array_values(array_diff($idsValidos, [$idProfesor]));
            $profes = array_intersect_key($profes, array_flip($idsValidos));
        }

        if ($legajos === [] && $profes === []) {
            return ['ok' => false, 'grupo' => null, 'error' => 'No quedaron integrantes válidos en este nivel.'];
        }

        $labelsAlu = DestinatariosRepository::nombresPorUserKeys(array_map('strval', array_keys($legajos)));
        $labelsProf = self::nombresProfesoresPorIds(array_keys($profes));

        $payloadGrupo = [
            'nombre'            => $nombre,
            'id_profesor'       => $idProfesor,
            'id_nivel'          => $idNivel,
            'tipo_destinatario' => ComGrupo::TIPO_MIXTO,
        ];
        $preparado = PersistenciaColumnas::prepararPayload('com_grupos', $payloadGrupo);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            return [
                'ok'    => false,
                'grupo' => null,
                'error' => PersistenciaColumnas::mensajeColumnasInexistentes(
                    'com_grupos',
                    $preparado['columnas_con_valor_sin_columna']
                ),
            ];
        }

        $cantidadEsperada = count($legajos) + count($profes);

        try {
            $grupo = DB::transaction(function () use (
                $idGrupo,
                $idProfesor,
                $idNivel,
                $preparado,
                $legajos,
                $profes,
                $labelsAlu,
                $labelsProf
            ) {
                if ($idGrupo !== null) {
                    $grupo = self::scopedOrFail($idGrupo, $idProfesor, $idNivel);
                    $grupo->fill($preparado['payload']);
                    $grupo->updated_at = now();
                    $grupo->save();
                    $grupo->miembros()->delete();
                } else {
                    $grupo = new ComGrupo($preparado['payload']);
                    $grupo->created_at = now();
                    $grupo->updated_at = now();
                    $grupo->save();
                }

                $filas = [];
                foreach ($legajos as $id => $labelForm) {
                    $nombreSnap = $labelsAlu[(string) $id] ?? $labelForm;
                    $filas[] = [
                        'id_grupo'        => (int) $grupo->id,
                        'tipo_miembro'    => ComGrupoMiembro::TIPO_LEGAJO,
                        'id_legajo'       => $id,
                        'id_profesor'     => null,
                        'nombre_snapshot' => $nombreSnap !== '' ? mb_substr($nombreSnap, 0, 150) : null,
                    ];
                }
                foreach ($profes as $id => $labelForm) {
                    $nombreSnap = $labelsProf[(int) $id] ?? $labelForm;
                    $filas[] = [
                        'id_grupo'        => (int) $grupo->id,
                        'tipo_miembro'    => ComGrupoMiembro::TIPO_PROFESOR,
                        'id_legajo'       => null,
                        'id_profesor'     => $id,
                        'nombre_snapshot' => $nombreSnap !== '' ? mb_substr($nombreSnap, 0, 150) : null,
                    ];
                }
                ComGrupoMiembro::query()->insert($filas);

                return $grupo->fresh();
            });
        } catch (QueryException $e) {
            $msg = PersistenciaColumnas::mensajeDesdeQueryException($e);

            return ['ok' => false, 'grupo' => null, 'error' => $msg ?? 'No se pudo guardar el grupo.'];
        }

        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            'com_grupos',
            ['id' => (int) $grupo->id],
            [
                'nombre'            => $nombre,
                'id_profesor'       => $idProfesor,
                'id_nivel'          => $idNivel,
                'tipo_destinatario' => ComGrupo::TIPO_MIXTO,
            ]
        );
        if ($noPersistidas !== []) {
            return [
                'ok'    => false,
                'grupo' => null,
                'error' => PersistenciaColumnas::mensajeColumnasNoPersistidas('com_grupos', $noPersistidas),
            ];
        }

        if ($grupo->miembros()->count() !== $cantidadEsperada) {
            return ['ok' => false, 'grupo' => null, 'error' => 'El grupo no guardó todos los integrantes. Vuelva a intentar.'];
        }

        return ['ok' => true, 'grupo' => $grupo, 'error' => null];
    }

    public static function eliminar(int $idGrupo, int $idProfesor, int $idNivel): array
    {
        if (! self::tablasDisponibles()) {
            return ['ok' => false, 'error' => self::mensajeTablasFaltantes()];
        }

        $grupo = self::scopedOrFail($idGrupo, $idProfesor, $idNivel);

        try {
            $grupo->delete();
        } catch (QueryException $e) {
            $msg = PersistenciaColumnas::mensajeDesdeQueryException($e);

            return ['ok' => false, 'error' => $msg ?? 'No se pudo eliminar el grupo.'];
        }

        $sigue = self::queryDelDueno($idProfesor, $idNivel)->whereKey($idGrupo)->exists();
        if ($sigue) {
            return ['ok' => false, 'error' => 'El grupo no se eliminó. Verifique el esquema de la base de datos.'];
        }

        return ['ok' => true, 'error' => null];
    }
}
