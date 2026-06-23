<?php

namespace App\Comunicaciones;

use App\Models\Legajo;
use App\Models\Profesor;
use App\Support\Comunicaciones\ComCanalRolCatalog;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\ComHilo;
use App\Models\ComMensaje;
use App\Models\ComMensajeDestinatario;
use App\Models\ComPreferencia;

class ComunicacionesRepository
{
    /** `profesortipo` «Sin Rol» — excluido de selectores de comunicados. */
    private const ID_TIPO_SIN_ROL = 1;

    /**
     * Hilos visibles en gestión: mismo terlec y (mismo nivel o comunicación interna docentes cross-nivel).
     */
    public static function hiloGestionProfesorEnContexto(int $idHilo, int $idNivel, int $idTerlec): ?ComHilo
    {
        return ComHilo::query()
            ->where('id', $idHilo)
            ->where('id_terlec', $idTerlec)
            ->where(function ($q) use ($idNivel) {
                $q->where('id_nivel', $idNivel)
                    ->orWhere('scope', 'docentes');
            })
            ->first();
    }

    /**
     * Verifica si un profesor puede ver un hilo (por ser creador o destinatario),
     * acotado al terlec del contexto y al nivel salvo hilos internos docentes cross-nivel.
     */
    public static function profesorPuedeVerHilo(
        int $idHilo,
        int $idProfesor,
        int $idNivel,
        int $idTerlec
    ): bool {
        return DB::table('com_hilos as h')
            ->where('h.id', $idHilo)
            ->where('h.id_terlec', $idTerlec)
            ->where(function ($q) use ($idNivel) {
                $q->where('h.id_nivel', $idNivel)
                    ->orWhere('h.scope', 'docentes');
            })
            ->where(function ($q) use ($idProfesor) {
                $q->where(function ($q2) use ($idProfesor) {
                    $q2->where('h.creado_por_tipo', 'profesor')
                        ->where('h.creado_por_id', $idProfesor);
                })->orWhereExists(function ($sub) use ($idProfesor) {
                    $sub->select(DB::raw(1))
                        ->from('com_mensajes_destinatarios as d')
                        ->whereColumn('d.id_hilo', 'h.id')
                        ->where('d.tipo_destinatario', 'profesor')
                        ->where('d.id_profesor', $idProfesor);
                });
            })
            ->exists();
    }

    /**
     * Verifica si una familia (legajo) puede ver un hilo (creador o destinatario),
     * siempre acotado al nivel/terlec del contexto.
     */
    public static function familiaPuedeVerHilo(
        int $idHilo,
        int $idLegajo,
        int $idNivel,
        int $idTerlec
    ): bool {
        return DB::table('com_hilos as h')
            ->where('h.id', $idHilo)
            ->where('h.id_nivel', $idNivel)
            ->where('h.id_terlec', $idTerlec)
            ->where(function ($q) use ($idLegajo) {
                $q->where(function ($q2) use ($idLegajo) {
                    $q2->where('h.creado_por_tipo', 'familia')
                        ->where('h.creado_por_id', $idLegajo);
                })->orWhereExists(function ($sub) use ($idLegajo) {
                    $sub->select(DB::raw(1))
                        ->from('com_mensajes_destinatarios as d')
                        ->whereColumn('d.id_hilo', 'h.id')
                        ->where('d.tipo_destinatario', 'familia')
                        ->where('d.id_legajo', $idLegajo);
                });
            })
            ->exists();
    }

    /**
     * Buscar profesores del nivel actual (para pantalla de revisión o destinatarios docentes).
     *
     * @param  string|null  $rolNormalizado  Si se indica ('profesor'|'preceptor'|'directivo'), solo ese rol.
     * @return list<array{id:int,label:string,dni:?string}>
     */
    public static function buscarProfesores(
        int $idNivel,
        string $q,
        int $limit = 15,
        ?string $rolNormalizado = null,
        ?bool $soloProfesorAula = null
    ): array {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $query = DB::table('profesores as p')
            ->join('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')
            ->where('p.nivel', $idNivel)
            ->when($soloProfesorAula === true, fn ($w) => $w->where('p.IdTipoProf', ProfesorMenuPortal::ID_TIPO_PROFESOR_AULA))
            ->when($soloProfesorAula === false, fn ($w) => $w->where('p.IdTipoProf', '!=', ProfesorMenuPortal::ID_TIPO_PROFESOR_AULA))
            ->where(function ($w) use ($like) {
                $w->where('p.apellido', 'like', $like)
                    ->orWhere('p.nombre', 'like', $like)
                    ->orWhere('p.dni', 'like', $like);
            })
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->limit($rolNormalizado !== null ? min(80, max($limit * 4, 40)) : $limit)
            ->get(['p.id', 'p.apellido', 'p.nombre', 'p.dni', 'pt.tipo']);

        $out = [];
        foreach ($query as $r) {
            if ($rolNormalizado !== null
                && CanalesPolicy::normalizarRolProfesor((string) $r->tipo) !== $rolNormalizado) {
                continue;
            }
            $out[] = [
                'id' => (int) $r->id,
                'label' => trim((string) $r->apellido . ', ' . (string) $r->nombre),
                'dni' => $r->dni !== null ? (string) $r->dni : null,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Estudiantes matriculados en el nivel y ciclo lectivo (revisión de comunicados).
     *
     * @return list<array{id:int,label:string,dni:?string}>
     */
    public static function buscarEstudiantes(int $idNivel, int $idTerlec, string $q, int $limit = 15): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $rows = DB::table('legajos as l')
            ->join('matricula as m', 'm.idLegajos', '=', 'l.id')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->whereNotNull('m.idLegajos')
            ->where(function ($w) use ($like) {
                $w->where('l.apellido', 'like', $like)
                    ->orWhere('l.nombre', 'like', $like)
                    ->orWhere('l.dni', 'like', $like);
            })
            ->select(['l.id', 'l.apellido', 'l.nombre', 'l.dni'])
            ->distinct()
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->limit(max(1, min(50, $limit)))
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r->id,
                'label' => trim((string) $r->apellido . ', ' . (string) $r->nombre),
                'dni' => $r->dni !== null ? (string) $r->dni : null,
            ];
        }

        return $out;
    }

    /**
     * Búsqueda unificada para Control Cuaderno de Comunicados (profesor/a, personal o estudiante).
     *
     * @return list<array{tipo:string,id:int,label:string,dni:?string}>
     */
    public static function buscarUsuariosRevision(int $idNivel, int $idTerlec, string $q, int $limit = 15): array
    {
        $limit = max(1, min(30, $limit));
        $mitad = (int) ceil($limit / 2);

        $out = [];
        foreach (static::buscarProfesores($idNivel, $q, $mitad) as $p) {
            $out[] = [
                'tipo' => 'profesor',
                'id' => $p['id'],
                'label' => $p['label'],
                'dni' => $p['dni'],
            ];
        }
        $restante = $limit - count($out);
        if ($restante > 0) {
            foreach (static::buscarEstudiantes($idNivel, $idTerlec, $q, $restante) as $e) {
                $out[] = [
                    'tipo' => 'estudiante',
                    'id' => $e['id'],
                    'label' => $e['label'],
                    'dni' => $e['dni'],
                ];
            }
        }

        usort($out, fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return array_slice($out, 0, $limit);
    }

    /**
     * Búsqueda de usuarios para el módulo de auditoría de comunicaciones.
     *
     * @param  'todos'|'estudiante'|'profesor'|'personal'  $categoria
     * @return list<array{tipo:string,id:int,label:string,dni:?string,categoria:string}>
     */
    public static function buscarUsuariosAuditoria(
        int $idNivel,
        int $idTerlec,
        string $q,
        string $categoria = 'todos',
        int $limit = 15
    ): array {
        $limit = max(1, min(30, $limit));
        $categoria = in_array($categoria, ['todos', 'estudiante', 'profesor', 'personal'], true)
            ? $categoria
            : 'todos';

        $out = [];

        if ($categoria === 'estudiante' || $categoria === 'todos') {
            $limEst = $categoria === 'todos' ? (int) ceil($limit / 2) : $limit;
            foreach (static::buscarEstudiantes($idNivel, $idTerlec, $q, $limEst) as $e) {
                $out[] = [
                    'tipo'      => 'estudiante',
                    'categoria' => 'estudiante',
                    'id'        => $e['id'],
                    'label'     => $e['label'],
                    'dni'       => $e['dni'],
                ];
            }
        }

        if ($categoria === 'profesor' || $categoria === 'personal' || $categoria === 'todos') {
            $restante = $limit - count($out);
            if ($restante > 0) {
                $soloAula = $categoria === 'profesor' ? true : ($categoria === 'personal' ? false : null);
                $mitad    = $categoria === 'todos' ? (int) ceil($restante / 2) : $restante;

                if ($categoria === 'todos') {
                    foreach (static::buscarProfesores($idNivel, $q, $mitad, null, true) as $p) {
                        $out[] = [
                            'tipo'      => 'profesor',
                            'categoria' => 'profesor',
                            'id'        => $p['id'],
                            'label'     => $p['label'],
                            'dni'       => $p['dni'],
                        ];
                    }
                    $restante = $limit - count($out);
                    if ($restante > 0) {
                        foreach (static::buscarProfesores($idNivel, $q, $restante, null, false) as $p) {
                            $out[] = [
                                'tipo'      => 'profesor',
                                'categoria' => 'personal',
                                'id'        => $p['id'],
                                'label'     => $p['label'],
                                'dni'       => $p['dni'],
                            ];
                        }
                    }
                } else {
                    $cat = $categoria === 'profesor' ? 'profesor' : 'personal';
                    foreach (static::buscarProfesores($idNivel, $q, $restante, null, $soloAula) as $p) {
                        $out[] = [
                            'tipo'      => 'profesor',
                            'categoria' => $cat,
                            'id'        => $p['id'],
                            'label'     => $p['label'],
                            'dni'       => $p['dni'],
                        ];
                    }
                }
            }
        }

        usort($out, fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return array_slice($out, 0, $limit);
    }

    /**
     * Docentes del nivel para selector con checkboxes (modal de nuevo comunicado).
     *
     * @param  'profesor'|'institucional'  $modoLista  `profesor`: Profesor/a y ATP/DOE;
     *                                              `institucional`: directivos, secretarios, preceptores, bibliotecarios y similares (no «Sin Rol»).
     * @return list<array{id:int,label:string,dni:?string,rol:string,rol_label:string}>
     */
    public static function profesoresDelNivelParaSelector(
        int $idNivel,
        string $modoLista,
        string $filtro = '',
        int $limit = 800
    ): array {
        $limit = max(1, min(2000, $limit));
        $t = mb_strtolower(trim($filtro));

        $rows = DB::table('profesores as p')
            ->join('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')
            ->where('p.nivel', $idNivel)
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->get(['p.id', 'p.apellido', 'p.nombre', 'p.dni', 'p.IdTipoProf', 'pt.tipo']);

        $out = [];
        foreach ($rows as $r) {
            if (! static::docenteCumpleModoSelector($r, $modoLista)) {
                continue;
            }
            $label = trim((string) $r->apellido . ', ' . (string) $r->nombre);
            $dni = $r->dni !== null ? (string) $r->dni : null;
            $rolLabel = trim((string) ($r->tipo ?? ''));
            if ($t !== '') {
                $blob = mb_strtolower($label . ' ' . ($dni ?? '') . ' ' . $rolLabel);
                if (! str_contains($blob, $t)) {
                    continue;
                }
            }
            $out[] = [
                'id' => (int) $r->id,
                'label' => $label,
                'dni' => $dni,
                'rol' => CanalesPolicy::normalizarRolProfesor($rolLabel),
                'rol_label' => $rolLabel !== '' ? $rolLabel : 'Sin rol asignado',
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Deja solo ids de profesores del nivel que cumplen el modo del selector.
     *
     * @param  list<int>  $ids
     * @param  'profesor'|'institucional'  $modoLista
     * @return list<int>
     */
    public static function filtrarIdsProfesoresPorModoSelector(array $ids, int $idNivel, string $modoLista): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('profesores as p')
            ->join('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')
            ->where('p.nivel', $idNivel)
            ->whereIn('p.id', $ids)
            ->get(['p.id', 'p.IdTipoProf', 'pt.tipo']);

        $out = [];
        foreach ($rows as $r) {
            if (static::docenteCumpleModoSelector($r, $modoLista)) {
                $out[] = (int) $r->id;
            }
        }

        return $out;
    }

    /**
     * Deja solo ids de profesores del nivel cuyo tipo normalizado coincide con $rolNorm.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function filtrarIdsProfesoresPorRolNorm(array $ids, int $idNivel, string $rolNorm): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('profesores as p')
            ->join('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')
            ->where('p.nivel', $idNivel)
            ->whereIn('p.id', $ids)
            ->get(['p.id', 'pt.tipo']);

        $out = [];
        foreach ($rows as $r) {
            if (CanalesPolicy::normalizarRolProfesor((string) $r->tipo) === $rolNorm) {
                $out[] = (int) $r->id;
            }
        }

        return $out;
    }

    /**
     * Docentes del nivel con un `IdTipoProf` concreto (modal «Elegir …» por rol).
     *
     * @return list<array{id:int,label:string,dni:?string,rol:string,rol_label:string}>
     */
    public static function profesoresDelNivelParaSelectorPorIdTipoProf(
        int $idNivel,
        int $idTipoProf,
        string $filtro = '',
        int $limit = 800
    ): array {
        if ($idTipoProf <= 0 || $idTipoProf === self::ID_TIPO_SIN_ROL) {
            return [];
        }

        $limit = max(1, min(2000, $limit));
        $t = mb_strtolower(trim($filtro));

        $rows = DB::table('profesores as p')
            ->join('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')
            ->where('p.nivel', $idNivel)
            ->where('p.IdTipoProf', $idTipoProf)
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->get(['p.id', 'p.apellido', 'p.nombre', 'p.dni', 'pt.tipo']);

        $out = [];
        foreach ($rows as $r) {
            $label = trim((string) $r->apellido . ', ' . (string) $r->nombre);
            $dni = $r->dni !== null ? (string) $r->dni : null;
            $rolLabel = trim((string) ($r->tipo ?? ''));
            if ($t !== '') {
                $blob = mb_strtolower($label . ' ' . ($dni ?? '') . ' ' . $rolLabel);
                if (! str_contains($blob, $t)) {
                    continue;
                }
            }
            $out[] = [
                'id'        => (int) $r->id,
                'label'     => $label,
                'dni'       => $dni,
                'rol'       => CanalesPolicy::normalizarRolProfesor($rolLabel),
                'rol_label' => $rolLabel !== '' ? $rolLabel : 'Sin rol asignado',
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function filtrarIdsProfesoresPorIdTipoProf(array $ids, int $idNivel, int $idTipoProf): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === [] || $idTipoProf <= 0) {
            return [];
        }

        return DB::table('profesores as p')
            ->where('p.nivel', $idNivel)
            ->where('p.IdTipoProf', $idTipoProf)
            ->whereIn('p.id', $ids)
            ->pluck('p.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Medios permitidos al iniciar un comunicado hacia varios roles receptores (intersección).
     *
     * @param  list<string>  $rolesReceptor
     * @return list<string>
     */
    public static function mediosPermitidosInicioVariosRoles(string $rolEmisor, array $rolesReceptor): array
    {
        if ($rolesReceptor === []) {
            return [];
        }

        $medios = null;
        foreach ($rolesReceptor as $rolRec) {
            $m = CanalesPolicy::mediosPermitidos($rolEmisor, $rolRec);
            $medios = $medios === null ? $m : array_values(array_intersect($medios, $m));
        }

        return $medios ?? [];
    }

    /**
     * @param  'profesor'|'institucional'  $modoLista
     */
    private static function docenteCumpleModoSelector(object $r, string $modoLista): bool
    {
        if ((int) ($r->IdTipoProf ?? 0) === self::ID_TIPO_SIN_ROL) {
            return false;
        }

        $modoTipo = CanalesPolicy::modoSelectorNuevoComunicadoDocente((string) ($r->tipo ?? ''));

        return $modoTipo !== null && $modoTipo === $modoLista;
    }

    /**
     * Profesores vinculados al hilo (creador docente, remitentes y destinatarios profesor).
     *
     * @return list<int>
     */
    public static function idsProfesoresEnHilo(int $idHilo): array
    {
        $hilo = ComHilo::query()->find($idHilo);
        $ids  = [];
        if ($hilo !== null && $hilo->creado_por_tipo === 'profesor' && $hilo->creado_por_id) {
            $ids[] = (int) $hilo->creado_por_id;
        }

        // Remitente del cuerpo inicial: asegura al emisor en el hilo aunque id_hilo del mensaje esté mal o sea legado.
        if ($hilo !== null) {
            $idIni = (int) ($hilo->cuerpo_inicial_id ?? 0);
            if ($idIni > 0) {
                $ini = ComMensaje::query()->find($idIni);
                if ($ini !== null
                    && $ini->tipo_remitente === 'profesor'
                    && $ini->id_profesor) {
                    $ids[] = (int) $ini->id_profesor;
                }
            }
        }

        $fromMsgs = ComMensaje::query()
            ->where('id_hilo', $idHilo)
            ->where('tipo_remitente', 'profesor')
            ->whereNotNull('id_profesor')
            ->pluck('id_profesor');
        foreach ($fromMsgs as $id) {
            $ids[] = (int) $id;
        }

        $fromDest = ComMensajeDestinatario::query()
            ->where('id_hilo', $idHilo)
            ->where('tipo_destinatario', 'profesor')
            ->whereNotNull('id_profesor')
            ->pluck('id_profesor');
        foreach ($fromDest as $id) {
            $ids[] = (int) $id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @return list<int>
     */
    public static function idsProfesoresDestinoRespuestaDocente(int $idHilo, int $idRemitente): array
    {
        $mine = (int) $idRemitente;

        return array_values(array_filter(
            array_map('intval', static::idsProfesoresEnHilo($idHilo)),
            static fn (int $id) => $id > 0 && $id !== $mine
        ));
    }

    /**
     * Roles normalizados únicos para un conjunto de ids de profesores.
     *
     * No filtra por la columna `nivel` de `profesores`. Usa `LEFT JOIN` a `profesortipo` para no perder
     * filas con `IdTipoProf` nulo (en ese caso este método no devuelve rol para ese id; usar
     * {@see self::rolesDestinatariosRespuestaDocente} en hilos docentes).
     *
     * @param  list<int>  $idsProfesor
     * @return list<string>
     */
    public static function rolesNormalizadosUnicosProfesores(array $idsProfesor): array
    {
        $idsProfesor = array_values(array_unique(array_map('intval', $idsProfesor)));
        if ($idsProfesor === []) {
            return [];
        }

        $rows = DB::table('profesores as p')
            ->leftJoin('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')
            ->whereIn('p.id', $idsProfesor)
            ->pluck('pt.tipo');

        $roles = [];
        foreach ($rows as $tipo) {
            if ($tipo === null || (string) $tipo === '') {
                continue;
            }
            $roles[] = CanalesPolicy::normalizarRolProfesor((string) $tipo);
        }

        return array_values(array_unique($roles));
    }

    /**
     * Claves de canal (`tipo:{id}`) de destinatarios en respuesta de hilo docentes.
     *
     * @return list<string>
     */
    public static function rolesDestinatariosRespuestaDocente(int $idHilo, int $idRemitenteExcluido, ComHilo $hilo): array
    {
        $idsDest = static::idsProfesoresDestinoRespuestaDocente($idHilo, $idRemitenteExcluido);
        $claves  = [];
        foreach ($idsDest as $idProf) {
            $idProf = (int) $idProf;
            if ($idProf <= 0) {
                continue;
            }
            $clave = static::claveDocenteParticipanteParaCanal($idHilo, $hilo, $idProf);
            if ($clave !== '') {
                $claves[] = $clave;
            }
        }

        return array_values(array_unique($claves));
    }

    /**
     * Roles destinatarios de una respuesta en hilo docentes, con respaldo cuando el cálculo por
     * participantes queda vacío (p. ej. datos inconsistentes) pero el usuario es destinatario
     * del primer mensaje y existe un autor docente distinto: se usa solo ese autor como contraparte.
     *
     * @return list<string>
     */
    public static function rolesDestinatariosRespuestaDocenteResuelto(int $idHilo, int $idRemitenteExcluido, ComHilo $hilo): array
    {
        $roles = static::rolesDestinatariosRespuestaDocente($idHilo, $idRemitenteExcluido, $hilo);
        if ($roles !== []) {
            return $roles;
        }

        if (! $hilo->esComunicacionInternaDocentes()) {
            return [];
        }

        $idIni = (int) ($hilo->cuerpo_inicial_id ?? 0);
        if ($idIni <= 0) {
            return [];
        }

        $soyDestinatarioInicial = ComMensajeDestinatario::query()
            ->where('id_mensaje', $idIni)
            ->where('tipo_destinatario', 'profesor')
            ->where('id_profesor', $idRemitenteExcluido)
            ->exists();

        if (! $soyDestinatarioInicial) {
            return [];
        }

        $ini = ComMensaje::query()->find($idIni);
        if ($ini === null || $ini->tipo_remitente !== 'profesor' || ! $ini->id_profesor) {
            return [];
        }

        $idAutor = (int) $ini->id_profesor;
        if ($idAutor <= 0 || $idAutor === $idRemitenteExcluido) {
            return [];
        }

        $clave = static::claveDocenteParticipanteParaCanal($idHilo, $hilo, $idAutor);
        if ($clave === '') {
            $clave = static::resolverClaveRolAlmacenado((string) ($ini->rol_remitente ?? '')) ?? '';
        }

        return $clave !== '' ? [$clave] : [];
    }

    /**
     * Clave de canal del participante docente (`tipo:{id}`).
     */
    private static function claveDocenteParticipanteParaCanal(int $idHilo, ComHilo $hilo, int $idProf): string
    {
        $prof = Profesor::with('tipo')->find($idProf);
        if ($prof !== null) {
            return CanalesPolicy::claveRolDeProfesor($prof);
        }

        if ($hilo->creado_por_tipo === 'profesor' && (int) $hilo->creado_por_id === $idProf) {
            $clave = static::resolverClaveRolAlmacenado((string) ($hilo->creado_por_rol ?? ''));
            if ($clave !== null) {
                return $clave;
            }
        }

        $rolMsg = ComMensaje::query()
            ->where('id_hilo', $idHilo)
            ->where('tipo_remitente', 'profesor')
            ->where('id_profesor', $idProf)
            ->orderByDesc('id')
            ->value('rol_remitente');

        $clave = static::resolverClaveRolAlmacenado($rolMsg !== null ? (string) $rolMsg : null);
        if ($clave !== null) {
            return $clave;
        }

        $canon = static::rolDocenteParticipanteParaCanal($idHilo, $hilo, $idProf);
        if ($canon === '') {
            return '';
        }

        $ids = ComCanalRolCatalog::idsTipoProfConRolCanonicoLegacy($canon);

        return $ids !== [] ? ComCanalRolCatalog::claveTipoProf($ids[0]) : '';
    }

    private static function resolverClaveRolAlmacenado(?string $rolAlmacenado): ?string
    {
        if ($rolAlmacenado === null || trim($rolAlmacenado) === '') {
            return null;
        }

        $parsed = ComCanalRolCatalog::parseClave(trim($rolAlmacenado));
        if ($parsed['id_tipo_prof'] !== null) {
            return ComCanalRolCatalog::claveDeIdTipoProf($parsed['id_tipo_prof']);
        }
        if ($parsed['familia']) {
            return ComCanalRolCatalog::CLAVE_FAMILIA;
        }
        if ($parsed['legacy'] !== null) {
            $ids = ComCanalRolCatalog::idsTipoProfConRolCanonicoLegacy($parsed['legacy']);

            return $ids !== [] ? ComCanalRolCatalog::claveTipoProf($ids[0]) : null;
        }

        return null;
    }

    /**
     * Rol normalizado legacy (directivo|preceptor|profesor) para metadatos antiguos.
     */
    private static function rolDocenteParticipanteParaCanal(int $idHilo, ComHilo $hilo, int $idProf): string
    {
        $row = DB::table('profesores as p')
            ->leftJoin('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')
            ->where('p.id', $idProf)
            ->first(['pt.tipo']);
        $tipo = $row ? (string) ($row->tipo ?? '') : '';
        if ($tipo !== '') {
            return CanalesPolicy::normalizarRolProfesor($tipo);
        }

        if ($hilo->creado_por_tipo === 'profesor' && (int) $hilo->creado_por_id === $idProf) {
            $r = mb_strtolower(trim((string) ($hilo->creado_por_rol ?? '')));
            foreach (['directivo', 'preceptor', 'profesor', 'familia'] as $canon) {
                if ($r === $canon) {
                    return $canon;
                }
            }
            if ($r !== '') {
                return CanalesPolicy::normalizarRolProfesor($r);
            }
            $idIni = (int) ($hilo->cuerpo_inicial_id ?? 0);
            if ($idIni > 0) {
                $rolIni = ComMensaje::query()->where('id', $idIni)->value('rol_remitente');
                $ri     = mb_strtolower(trim((string) ($rolIni ?? '')));
                foreach (['directivo', 'preceptor', 'profesor', 'familia'] as $canon) {
                    if ($ri === $canon) {
                        return $canon;
                    }
                }
                if ($ri !== '') {
                    return CanalesPolicy::normalizarRolProfesor($ri);
                }
            }
        }

        $rolMsg = ComMensaje::query()
            ->where('id_hilo', $idHilo)
            ->where('tipo_remitente', 'profesor')
            ->where('id_profesor', $idProf)
            ->orderByDesc('id')
            ->value('rol_remitente');
        $rm = mb_strtolower(trim((string) ($rolMsg ?? '')));
        foreach (['directivo', 'preceptor', 'profesor', 'familia'] as $canon) {
            if ($rm === $canon) {
                return $canon;
            }
        }
        if ($rm !== '') {
            return CanalesPolicy::normalizarRolProfesor($rm);
        }

        $prof = Profesor::with('tipo')->find($idProf);
        if ($prof !== null) {
            return CanalesPolicy::rolDeProfesor($prof);
        }

        return '';
    }

    /**
     * @param  list<string>  $rolesReceptor
     * @return list<string>
     */
    public static function mediosPermitidosRespuestaVariosRoles(
        string $rolEmisor,
        array $rolesReceptor,
        bool $simetricoInternoDocentes = false
    ): array {
        if ($rolesReceptor === []) {
            return [];
        }

        $medios = null;
        foreach ($rolesReceptor as $rolRec) {
            $m = CanalesPolicy::mediosPermitidos($rolEmisor, $rolRec);
            if ($m === [] && $simetricoInternoDocentes) {
                $m = CanalesPolicy::mediosPermitidos($rolRec, $rolEmisor);
            }
            $medios = $medios === null ? $m : array_values(array_intersect($medios, $m));
        }

        return $medios ?? [];
    }

    /**
     * @param  list<string>  $rolesReceptor
     */
    public static function puedeResponderVariosRoles(
        string $rolEmisor,
        array $rolesReceptor,
        bool $simetricoInternoDocentes = false
    ): bool {
        if ($rolesReceptor === []) {
            return false;
        }
        foreach ($rolesReceptor as $rolRec) {
            $ok = CanalesPolicy::puedeResponder($rolEmisor, $rolRec);
            if (! $ok && $simetricoInternoDocentes) {
                $ok = CanalesPolicy::puedeIniciar($rolRec, $rolEmisor);
            }
            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * Bandeja del profesor: hilos donde es creador o destinatario,
     * con contadores de no_leidos y respondidos.
     *
     * @param  string  $direccion  recibidos|enviados — hilos no iniciados por el profesor vs iniciados por él
     * @return \Illuminate\Support\Collection
     */
    public static function bandejaProfesor(
        int $idProfesor,
        int $idNivel,
        int $idTerlec,
        string $filtro = 'todos',
        string $direccion = 'recibidos',
        bool $soloTerlecActual = true
    ) {
        $direccion = in_array($direccion, ['recibidos', 'enviados'], true) ? $direccion : 'todos';

        $query = DB::table('com_hilos as h')
            ->where(function ($q) use ($idProfesor) {
                $q->where(function ($q2) use ($idProfesor) {
                    $q2->where('h.creado_por_tipo', 'profesor')
                       ->where('h.creado_por_id', $idProfesor);
                })->orWhereExists(function ($sub) use ($idProfesor) {
                    $sub->select(DB::raw(1))
                        ->from('com_mensajes_destinatarios as d2')
                        ->whereColumn('d2.id_hilo', 'h.id')
                        ->where('d2.tipo_destinatario', 'profesor')
                        ->where('d2.id_profesor', $idProfesor);
                });
            })
            ->when($direccion === 'recibidos', function ($q) use ($idProfesor) {
                $q->where(function ($w) use ($idProfesor) {
                    $w->where('h.creado_por_tipo', '!=', 'profesor')
                        ->orWhere('h.creado_por_id', '!=', $idProfesor);
                });
            })
            ->when($direccion === 'enviados', function ($q) use ($idProfesor) {
                $q->where('h.creado_por_tipo', 'profesor')
                    ->where('h.creado_por_id', $idProfesor);
            })
            ->when($soloTerlecActual, fn ($q) => $q->where('h.id_terlec', $idTerlec))
            ->where(function ($q) use ($idNivel) {
                $q->where('h.id_nivel', $idNivel)
                    ->orWhere('h.scope', 'docentes');
            })
            ->leftJoin('com_mensajes_destinatarios as d', 'd.id_hilo', '=', 'h.id');

        $select = [
            'h.id', 'h.asunto', 'h.scope', 'h.estado', 'h.cuerpo_inicial_id',
            'h.creado_por_tipo', 'h.creado_por_id', 'h.creado_por_rol',
            'h.familia_puede_responder', 'h.docentes_permite_respuestas',
            'h.id_curso', 'h.cursos_envio',
            'h.ultimo_mensaje_at', 'h.created_at',
            DB::raw("SUM(CASE
                WHEN h.creado_por_tipo = 'profesor' AND h.creado_por_id = {$idProfesor} THEN
                    CASE WHEN d.id IS NOT NULL AND d.leido_at IS NULL
                        AND NOT (d.tipo_destinatario = 'profesor' AND d.id_profesor = {$idProfesor})
                    THEN 1 ELSE 0 END
                ELSE
                    CASE WHEN d.tipo_destinatario = 'profesor' AND d.id_profesor = {$idProfesor}
                        AND d.leido_at IS NULL AND d.id IS NOT NULL
                    THEN 1 ELSE 0 END
            END) as no_leidos"),
            DB::raw('SUM(CASE WHEN d.respondido_at IS NOT NULL THEN 1 ELSE 0 END) as respondidos'),
            DB::raw('COUNT(d.id) as total_dest'),
            DB::raw("CASE WHEN h.creado_por_tipo = 'profesor' AND h.creado_por_id = {$idProfesor} THEN 'enviado' ELSE 'recibido' END as direccion"),
            DB::raw('(SELECT COUNT(*) FROM com_mensajes mx WHERE mx.id_hilo = h.id) as mensajes_count'),
        ];

        $select[] = DB::raw('(SELECT m.contenido FROM com_mensajes m WHERE m.id = h.cuerpo_inicial_id LIMIT 1) as cuerpo_inicial_contenido');
        $select[] = DB::raw("(SELECT COUNT(DISTINCT d0.id_legajo) FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'familia' AND d0.id_legajo IS NOT NULL) as destinatarios_familia_count");
        $select[] = DB::raw("(SELECT COUNT(*) FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id) as destinatarios_mensaje_inicial_count");
        $select[] = DB::raw("(SELECT COUNT(*) FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.leido_at IS NOT NULL) as destinatarios_mensaje_inicial_leidos");
        $select[] = DB::raw("(SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(d0.nombre_snapshot), '') ORDER BY d0.id_legajo SEPARATOR '||') FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'familia') as destinatarios_nombres_concat");
        $select[] = DB::raw("(SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(d0.nombre_snapshot), '') ORDER BY d0.id_profesor SEPARATOR '||') FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'profesor') as destinatarios_doc_nombres_concat");
        $select[] = DB::raw("(SELECT CASE WHEN TRIM(COALESCE(c.cursec, '')) <> '' THEN TRIM(c.cursec) ELSE TRIM(COALESCE(cp.curPlanCurso, 'Curso')) END FROM cursos c LEFT JOIN curplan cp ON cp.id = c.idCurPlan WHERE c.Id = h.id_curso LIMIT 1) as curso_envio_label");

        $select[] = DB::raw('(SELECT m0.tipo_remitente FROM com_mensajes m0 WHERE m0.id = h.cuerpo_inicial_id LIMIT 1) as cuerpo_inicial_tipo');
        $select[] = DB::raw('(SELECT m0.nombre_remitente_snapshot FROM com_mensajes m0 WHERE m0.id = h.cuerpo_inicial_id LIMIT 1) as cuerpo_inicial_nombre');
        $select[] = DB::raw('(SELECT m0.vinculo_familiar FROM com_mensajes m0 WHERE m0.id = h.cuerpo_inicial_id LIMIT 1) as cuerpo_inicial_vinculo');

        $havingNoLeidos = "SUM(CASE
            WHEN h.creado_por_tipo = 'profesor' AND h.creado_por_id = {$idProfesor} THEN
                CASE WHEN d.id IS NOT NULL AND d.leido_at IS NULL
                    AND NOT (d.tipo_destinatario = 'profesor' AND d.id_profesor = {$idProfesor})
                THEN 1 ELSE 0 END
            ELSE
                CASE WHEN d.tipo_destinatario = 'profesor' AND d.id_profesor = {$idProfesor}
                    AND d.leido_at IS NULL AND d.id IS NOT NULL
                THEN 1 ELSE 0 END
        END) > 0";

        $query->select($select)
            ->groupBy('h.id', 'h.asunto', 'h.scope', 'h.estado', 'h.creado_por_tipo',
                      'h.creado_por_id', 'h.creado_por_rol', 'h.familia_puede_responder',
                      'h.docentes_permite_respuestas',
                      'h.ultimo_mensaje_at', 'h.created_at', 'h.cuerpo_inicial_id', 'h.id_curso', 'h.cursos_envio')
            ->orderByDesc('h.ultimo_mensaje_at');

        if ($filtro === 'no_leidos') {
            $query->havingRaw($havingNoLeidos);
        } elseif ($filtro === 'respondidos') {
            $query->havingRaw('SUM(CASE WHEN d.respondido_at IS NOT NULL THEN 1 ELSE 0 END) > 0');
        }

        return $query->get();
    }

    /**
     * Bandeja de control / revisión institucional: todos los hilos del nivel y ciclo,
     * sin acotar a la participación de un solo profesor.
     *
     * Si $idProfesorFiltro o $idLegajoFiltro es mayor que cero, delega en la bandeja de ese usuario.
     *
     * @param  string  $direccion  todos|recibidos|enviados
     * @return \Illuminate\Support\Collection
     */
    public static function bandejaRevisionControl(
        int $idNivel,
        int $idTerlec,
        string $filtro = 'todos',
        string $direccion = 'todos',
        bool $soloTerlecActual = true,
        ?int $idProfesorFiltro = null,
        ?int $idLegajoFiltro = null
    ) {
        if ($idLegajoFiltro !== null && $idLegajoFiltro > 0) {
            $dir = in_array($direccion, ['todos', 'recibidos', 'enviados'], true) ? $direccion : 'todos';

            return static::bandejaFamilia(
                $idLegajoFiltro,
                $idNivel,
                $idTerlec,
                $filtro,
                $dir,
                $soloTerlecActual
            );
        }

        if ($idProfesorFiltro !== null && $idProfesorFiltro > 0) {
            return static::bandejaProfesor(
                $idProfesorFiltro,
                $idNivel,
                $idTerlec,
                $filtro,
                $direccion,
                $soloTerlecActual
            );
        }

        $direccion = in_array($direccion, ['recibidos', 'enviados'], true) ? $direccion : 'todos';

        $query = DB::table('com_hilos as h')
            ->where('h.id_nivel', $idNivel)
            ->when($soloTerlecActual, fn ($q) => $q->where('h.id_terlec', $idTerlec))
            ->when($direccion === 'enviados', fn ($q) => $q->where('h.creado_por_tipo', 'profesor'))
            ->when($direccion === 'recibidos', function ($q) {
                $q->where(function ($w) {
                    $w->where('h.creado_por_tipo', 'familia')
                        ->orWhereExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('com_mensajes_destinatarios as dx')
                                ->whereColumn('dx.id_hilo', 'h.id')
                                ->where('dx.tipo_destinatario', 'profesor');
                        });
                });
            })
            ->leftJoin('com_mensajes_destinatarios as d', 'd.id_hilo', '=', 'h.id');

        $select = [
            'h.id', 'h.asunto', 'h.scope', 'h.estado', 'h.cuerpo_inicial_id',
            'h.creado_por_tipo', 'h.creado_por_id', 'h.creado_por_rol',
            'h.familia_puede_responder', 'h.docentes_permite_respuestas',
            'h.id_curso', 'h.cursos_envio',
            'h.ultimo_mensaje_at', 'h.created_at',
            DB::raw('SUM(CASE WHEN d.leido_at IS NULL AND d.id IS NOT NULL THEN 1 ELSE 0 END) as no_leidos'),
            DB::raw('SUM(CASE WHEN d.respondido_at IS NOT NULL THEN 1 ELSE 0 END) as respondidos'),
            DB::raw('COUNT(d.id) as total_dest'),
            DB::raw("CASE WHEN h.creado_por_tipo = 'profesor' THEN 'enviado' ELSE 'recibido' END as direccion"),
            DB::raw('(SELECT COUNT(*) FROM com_mensajes mx WHERE mx.id_hilo = h.id) as mensajes_count'),
            DB::raw("(SELECT TRIM(CONCAT(COALESCE(p.apellido, ''), ', ', COALESCE(p.nombre, ''))) FROM profesores p WHERE p.id = h.creado_por_id AND h.creado_por_tipo = 'profesor' LIMIT 1) as remitente_institucional"),
        ];

        $select[] = DB::raw('(SELECT m.contenido FROM com_mensajes m WHERE m.id = h.cuerpo_inicial_id LIMIT 1) as cuerpo_inicial_contenido');
        $select[] = DB::raw("(SELECT COUNT(DISTINCT d0.id_legajo) FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'familia' AND d0.id_legajo IS NOT NULL) as destinatarios_familia_count");
        $select[] = DB::raw("(SELECT COUNT(*) FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id) as destinatarios_mensaje_inicial_count");
        $select[] = DB::raw("(SELECT COUNT(*) FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.leido_at IS NOT NULL) as destinatarios_mensaje_inicial_leidos");
        $select[] = DB::raw("(SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(d0.nombre_snapshot), '') ORDER BY d0.id_legajo SEPARATOR '||') FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'familia') as destinatarios_nombres_concat");
        $select[] = DB::raw("(SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(d0.nombre_snapshot), '') ORDER BY d0.id_profesor SEPARATOR '||') FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'profesor') as destinatarios_doc_nombres_concat");
        $select[] = DB::raw("(SELECT CASE WHEN TRIM(COALESCE(c.cursec, '')) <> '' THEN TRIM(c.cursec) ELSE TRIM(COALESCE(cp.curPlanCurso, 'Curso')) END FROM cursos c LEFT JOIN curplan cp ON cp.id = c.idCurPlan WHERE c.Id = h.id_curso LIMIT 1) as curso_envio_label");
        $select[] = DB::raw('(SELECT m0.tipo_remitente FROM com_mensajes m0 WHERE m0.id = h.cuerpo_inicial_id LIMIT 1) as cuerpo_inicial_tipo');
        $select[] = DB::raw('(SELECT m0.nombre_remitente_snapshot FROM com_mensajes m0 WHERE m0.id = h.cuerpo_inicial_id LIMIT 1) as cuerpo_inicial_nombre');
        $select[] = DB::raw('(SELECT m0.vinculo_familiar FROM com_mensajes m0 WHERE m0.id = h.cuerpo_inicial_id LIMIT 1) as cuerpo_inicial_vinculo');

        $query->select($select)
            ->groupBy('h.id', 'h.asunto', 'h.scope', 'h.estado', 'h.creado_por_tipo',
                'h.creado_por_id', 'h.creado_por_rol', 'h.familia_puede_responder',
                'h.docentes_permite_respuestas',
                'h.ultimo_mensaje_at', 'h.created_at', 'h.cuerpo_inicial_id', 'h.id_curso', 'h.cursos_envio')
            ->orderByDesc('h.ultimo_mensaje_at');

        if ($filtro === 'no_leidos') {
            $query->havingRaw('SUM(CASE WHEN d.leido_at IS NULL AND d.id IS NOT NULL THEN 1 ELSE 0 END) > 0');
        } elseif ($filtro === 'respondidos') {
            $query->havingRaw('SUM(CASE WHEN d.respondido_at IS NOT NULL THEN 1 ELSE 0 END) > 0');
        }

        return $query->get();
    }

    /**
     * Bandeja de la familia: hilos donde el legajo es creador o destinatario.
     *
     * @param  string  $direccion  todos|recibidos|enviados — unificar bandeja o filtrar por origen
     * @return \Illuminate\Support\Collection
     */
    public static function bandejaFamilia(
        int $idLegajo,
        int $idNivel,
        int $idTerlec,
        string $filtro = 'todos',
        string $direccion = 'todos',
        bool $soloTerlecActual = true
    ) {
        $direccion = in_array($direccion, ['todos', 'recibidos', 'enviados'], true)
            ? $direccion
            : 'todos';

        $query = DB::table('com_hilos as h')
            ->where(function ($q) use ($idLegajo) {
                $q->where(function ($q2) use ($idLegajo) {
                    $q2->where('h.creado_por_tipo', 'familia')
                       ->where('h.creado_por_id', $idLegajo);
                })->orWhereExists(function ($sub) use ($idLegajo) {
                    $sub->select(DB::raw(1))
                        ->from('com_mensajes_destinatarios as d2')
                        ->whereColumn('d2.id_hilo', 'h.id')
                        ->where('d2.tipo_destinatario', 'familia')
                        ->where('d2.id_legajo', $idLegajo);
                });
            })
            ->when($direccion === 'recibidos', function ($q) use ($idLegajo) {
                $q->where(function ($w) use ($idLegajo) {
                    $w->where('h.creado_por_tipo', '!=', 'familia')
                        ->orWhere('h.creado_por_id', '!=', $idLegajo);
                });
            })
            ->when($direccion === 'enviados', function ($q) use ($idLegajo) {
                $q->where('h.creado_por_tipo', 'familia')
                    ->where('h.creado_por_id', $idLegajo);
            })
            ->where('h.id_nivel', $idNivel)
            ->when($soloTerlecActual, fn ($q) => $q->where('h.id_terlec', $idTerlec))
            ->leftJoin('com_mensajes_destinatarios as d', 'd.id_hilo', '=', 'h.id')
            ->select([
                'h.id', 'h.asunto', 'h.scope', 'h.estado', 'h.cuerpo_inicial_id',
                'h.creado_por_tipo', 'h.creado_por_id', 'h.creado_por_rol',
                'h.familia_puede_responder', 'h.docentes_permite_respuestas',
                'h.id_curso', 'h.cursos_envio',
                'h.ultimo_mensaje_at', 'h.created_at',
                DB::raw("SUM(CASE
                    WHEN h.creado_por_tipo = 'familia' AND h.creado_por_id = {$idLegajo} THEN
                        CASE WHEN d.tipo_destinatario = 'profesor' AND d.leido_at IS NULL AND d.id IS NOT NULL
                        THEN 1 ELSE 0 END
                    ELSE
                        CASE WHEN d.tipo_destinatario = 'familia' AND d.id_legajo = {$idLegajo}
                            AND d.leido_at IS NULL AND d.id IS NOT NULL
                        THEN 1 ELSE 0 END
                END) as no_leidos"),
                DB::raw('SUM(CASE WHEN d.respondido_at IS NOT NULL THEN 1 ELSE 0 END) as respondidos'),
                DB::raw("CASE WHEN h.creado_por_tipo = 'familia' AND h.creado_por_id = {$idLegajo} THEN 'enviado' ELSE 'recibido' END as direccion"),
                DB::raw('(SELECT COUNT(*) FROM com_mensajes mx WHERE mx.id_hilo = h.id) as mensajes_count'),
                DB::raw('(SELECT m.contenido FROM com_mensajes m WHERE m.id = h.cuerpo_inicial_id LIMIT 1) as cuerpo_inicial_contenido'),
                DB::raw("(SELECT COUNT(DISTINCT d0.id_legajo) FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'familia' AND d0.id_legajo IS NOT NULL) as destinatarios_familia_count"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(d0.nombre_snapshot), '') ORDER BY d0.id_legajo SEPARATOR '||') FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'familia') as destinatarios_nombres_concat"),
                DB::raw("(SELECT COUNT(DISTINCT d0.id_profesor) FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'profesor' AND d0.id_profesor IS NOT NULL) as destinatarios_prof_count"),
                DB::raw("(SELECT COUNT(*) FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'profesor') as destinatarios_mensaje_inicial_count"),
                DB::raw("(SELECT COUNT(*) FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'profesor' AND d0.leido_at IS NOT NULL) as destinatarios_mensaje_inicial_leidos"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(d0.nombre_snapshot), '') ORDER BY d0.id_profesor SEPARATOR '||') FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'profesor') as destinatarios_doc_nombres_concat"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(d0.nombre_snapshot), '') ORDER BY d0.id_profesor SEPARATOR '||') FROM com_mensajes_destinatarios d0 WHERE d0.id_mensaje = h.cuerpo_inicial_id AND d0.tipo_destinatario = 'profesor') as destinatarios_prof_nombres_concat"),
                DB::raw("(SELECT CASE WHEN TRIM(COALESCE(c.cursec, '')) <> '' THEN TRIM(c.cursec) ELSE TRIM(COALESCE(cp.curPlanCurso, 'Curso')) END FROM cursos c LEFT JOIN curplan cp ON cp.id = c.idCurPlan WHERE c.Id = h.id_curso LIMIT 1) as curso_envio_label"),
                DB::raw('(SELECT m0.tipo_remitente FROM com_mensajes m0 WHERE m0.id = h.cuerpo_inicial_id LIMIT 1) as cuerpo_inicial_tipo'),
                DB::raw('(SELECT m0.nombre_remitente_snapshot FROM com_mensajes m0 WHERE m0.id = h.cuerpo_inicial_id LIMIT 1) as cuerpo_inicial_nombre'),
                DB::raw('(SELECT m0.vinculo_familiar FROM com_mensajes m0 WHERE m0.id = h.cuerpo_inicial_id LIMIT 1) as cuerpo_inicial_vinculo'),
            ])
            ->groupBy('h.id', 'h.asunto', 'h.scope', 'h.estado', 'h.creado_por_tipo',
                      'h.creado_por_id', 'h.creado_por_rol', 'h.familia_puede_responder',
                      'h.docentes_permite_respuestas',
                      'h.ultimo_mensaje_at', 'h.created_at', 'h.cuerpo_inicial_id', 'h.id_curso', 'h.cursos_envio')
            ->orderByDesc('h.ultimo_mensaje_at');

        $havingNoLeidosFam = "SUM(CASE
            WHEN h.creado_por_tipo = 'familia' AND h.creado_por_id = {$idLegajo} THEN
                CASE WHEN d.tipo_destinatario = 'profesor' AND d.leido_at IS NULL AND d.id IS NOT NULL
                THEN 1 ELSE 0 END
            ELSE
                CASE WHEN d.tipo_destinatario = 'familia' AND d.id_legajo = {$idLegajo}
                    AND d.leido_at IS NULL AND d.id IS NOT NULL
                THEN 1 ELSE 0 END
        END) > 0";

        if ($filtro === 'no_leidos') {
            $query->havingRaw($havingNoLeidosFam);
        } elseif ($filtro === 'respondidos') {
            $query->havingRaw('SUM(CASE WHEN d.respondido_at IS NOT NULL THEN 1 ELSE 0 END) > 0');
        }

        return $query->get();
    }

    /**
     * Marca como leído todos los mensajes de un hilo para un destinatario.
     *
     * @return list<int> IDs de mensajes que pasaron de no leído a leído
     */
    public static function marcarLeidoHiloProfesor(int $idHilo, int $idProfesor): array
    {
        $base = ComMensajeDestinatario::query()
            ->where('id_hilo', $idHilo)
            ->where('tipo_destinatario', 'profesor')
            ->where('id_profesor', $idProfesor)
            ->whereNull('leido_at');

        $idsMensajes = $base->clone()
            ->distinct()
            ->pluck('id_mensaje')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($idsMensajes === []) {
            return [];
        }

        $base->update(['leido_at' => now()]);

        return $idsMensajes;
    }

    /**
     * @return list<int> IDs de mensajes que pasaron de no leído a leído
     */
    public static function marcarLeidoHiloFamilia(int $idHilo, int $idLegajo): array
    {
        $base = ComMensajeDestinatario::query()
            ->where('id_hilo', $idHilo)
            ->where('tipo_destinatario', 'familia')
            ->where('id_legajo', $idLegajo)
            ->whereNull('leido_at');

        $idsMensajes = $base->clone()
            ->distinct()
            ->pluck('id_mensaje')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($idsMensajes === []) {
            return [];
        }

        $base->update(['leido_at' => now()]);

        return $idsMensajes;
    }

    /**
     * Marca un mensaje concreto como no leído para el profesor (mensajes recibidos: familia u otro docente).
     */
    public static function marcarNoLeidoMensajeProfesor(
        int $idMensaje,
        int $idHilo,
        int $idProfesor,
        int $idNivel,
        int $idTerlec
    ): bool {
        if (! ComHilo::query()
            ->where('id', $idHilo)
            ->where('id_nivel', $idNivel)
            ->where('id_terlec', $idTerlec)
            ->exists()) {
            return false;
        }

        $msg = ComMensaje::query()
            ->where('id', $idMensaje)
            ->where('id_hilo', $idHilo)
            ->where(function ($q) use ($idProfesor) {
                $q->where('tipo_remitente', '!=', 'profesor')
                    ->orWhereNull('id_profesor')
                    ->orWhere('id_profesor', '!=', $idProfesor);
            })
            ->first();

        if ($msg === null) {
            return false;
        }

        $affected = ComMensajeDestinatario::query()
            ->where('id_mensaje', $idMensaje)
            ->where('id_hilo', $idHilo)
            ->where('tipo_destinatario', 'profesor')
            ->where('id_profesor', $idProfesor)
            ->whereNotNull('leido_at')
            ->update(['leido_at' => null]);

        return $affected > 0;
    }

    /**
     * Marca un mensaje concreto como no leído para la familia (solo mensajes recibidos desde la escuela).
     */
    public static function marcarNoLeidoMensajeFamilia(
        int $idMensaje,
        int $idHilo,
        int $idLegajo,
        int $idNivel,
        int $idTerlec
    ): bool {
        if (! ComHilo::query()
            ->where('id', $idHilo)
            ->where('id_nivel', $idNivel)
            ->where('id_terlec', $idTerlec)
            ->exists()) {
            return false;
        }

        $msg = ComMensaje::query()
            ->where('id', $idMensaje)
            ->where('id_hilo', $idHilo)
            ->where('tipo_remitente', 'profesor')
            ->first();

        if ($msg === null) {
            return false;
        }

        $affected = ComMensajeDestinatario::query()
            ->where('id_mensaje', $idMensaje)
            ->where('id_hilo', $idHilo)
            ->where('tipo_destinatario', 'familia')
            ->where('id_legajo', $idLegajo)
            ->whereNotNull('leido_at')
            ->update(['leido_at' => null]);

        return $affected > 0;
    }

    /**
     * Detalle de lectura por destinatario (mensaje enviado por la escuela).
     *
     * @return array{
     *   titulo:string,
     *   resumen:array{etiqueta:string,estado:string,total:int,leidos:int},
     *   filas:list<array{nombre:string,tipo_etiqueta:string,leido:bool,fecha_lectura:string}>
     * }|null
     */
    public static function payloadDetalleLecturaMensajeGestion(
        int $idMensaje,
        int $idHilo,
        int $idNivel,
        int $idTerlec
    ): ?array {
        if (! ComHilo::query()
            ->where('id', $idHilo)
            ->where('id_nivel', $idNivel)
            ->where('id_terlec', $idTerlec)
            ->exists()) {
            return null;
        }

        $msg = ComMensaje::query()
            ->where('id', $idMensaje)
            ->where('id_hilo', $idHilo)
            ->where('tipo_remitente', 'profesor')
            ->with('destinatarios')
            ->first();

        if ($msg === null) {
            return null;
        }

        $resumen = $msg->resumenLecturaDestinatarios();
        if (($resumen['total'] ?? 0) === 0) {
            return null;
        }

        return [
            'titulo'  => 'Confirmación de lectura',
            'resumen' => $resumen,
            'filas'   => $msg->filasDetalleLecturaDestinatarios(),
        ];
    }

    /**
     * Detalle de lectura por destinatario (mensaje enviado por la familia).
     *
     * @return array{
     *   titulo:string,
     *   resumen:array{etiqueta:string,estado:string,total:int,leidos:int},
     *   filas:list<array{nombre:string,tipo_etiqueta:string,leido:bool,fecha_lectura:string}>
     * }|null
     */
    public static function payloadDetalleLecturaMensajeFamilia(
        int $idMensaje,
        int $idHilo,
        int $idLegajo,
        int $idNivel,
        int $idTerlec
    ): ?array {
        if (! static::familiaPuedeVerHilo($idHilo, $idLegajo, $idNivel, $idTerlec)) {
            return null;
        }

        $msg = ComMensaje::query()
            ->where('id', $idMensaje)
            ->where('id_hilo', $idHilo)
            ->where('tipo_remitente', 'familia')
            ->where('id_legajo', $idLegajo)
            ->with('destinatarios')
            ->first();

        if ($msg === null) {
            return null;
        }

        $resumen = $msg->resumenLecturaDestinatarios();
        if (($resumen['total'] ?? 0) === 0) {
            return null;
        }

        return [
            'titulo'  => 'Confirmación de lectura',
            'resumen' => $resumen,
            'filas'   => $msg->filasDetalleLecturaDestinatarios(),
        ];
    }

    /**
     * Crea un nuevo hilo con su primer mensaje y destinatarios.
     *
     * @param array{
     *   asunto: string,
     *   contenido: string,
     *   scope: string,
     *   id_legajos: list<int>,
     *   id_curso: ?int,
     *   cursos_envio: ?list<array{id:int,label:string}>,
     *   id_nivel: int,
     *   id_terlec: int,
     *   creado_por_tipo: string,
     *   creado_por_id: int,
     *   creado_por_rol: string,
     *   rol_receptor: string,
     *   vinculo_familiar: ?string,
     *   nombre_remitente: ?string,
     *   dni_remitente: ?string,
     *   destinatarios_profesores: list<int>,
     *   familia_puede_responder?: bool,
     *   docentes_permite_respuestas?: ?bool, // solo scope docentes; null = permitir respuestas (legado)
     * } $datos
     * @param list<string> $mediosCanal
     */
    public static function crearHiloConMensaje(array $datos, array $mediosCanal): ComHilo
    {
        return DB::transaction(function () use ($datos, $mediosCanal) {
            // 1. Hilo
            $hiloAttrs = [
                'asunto'                  => $datos['asunto'],
                'scope'                   => $datos['scope'],
                'id_legajo'               => $datos['id_legajos'][0] ?? null,
                'id_curso'                => $datos['id_curso'] ?? null,
                'cursos_envio'            => $datos['cursos_envio'] ?? null,
                'id_nivel'                => $datos['id_nivel'],
                'id_terlec'               => $datos['id_terlec'],
                'creado_por_tipo'         => $datos['creado_por_tipo'],
                'creado_por_id'           => $datos['creado_por_id'],
                'creado_por_rol'          => $datos['creado_por_rol'],
                'estado'                  => 'abierto',
                'familia_puede_responder' => (bool) ($datos['familia_puede_responder'] ?? true),
                'ultimo_mensaje_at'       => now(),
                'created_at'              => now(),
                'updated_at'              => now(),
            ];
            if (array_key_exists('docentes_permite_respuestas', $datos)) {
                $hiloAttrs['docentes_permite_respuestas'] = $datos['docentes_permite_respuestas'];
            }
            $hilo = ComHilo::create($hiloAttrs);

            // 2. Primer mensaje
            $mensaje = ComMensaje::create([
                'id_hilo'                   => $hilo->id,
                'tipo_remitente'            => $datos['creado_por_tipo'],
                'id_profesor'               => $datos['creado_por_tipo'] === 'profesor' ? $datos['creado_por_id'] : null,
                'id_legajo'                 => $datos['creado_por_tipo'] === 'familia' ? $datos['creado_por_id'] : null,
                'rol_remitente'             => $datos['creado_por_rol'],
                'vinculo_familiar'          => $datos['vinculo_familiar'] ?? null,
                'nombre_remitente_snapshot' => $datos['nombre_remitente'] ?? null,
                'dni_remitente_snapshot'    => $datos['dni_remitente'] ?? null,
                'contenido'                 => $datos['contenido'],
                'fecha'                     => now()->toDateString(),
                'hora'                      => now()->toTimeString(),
                'created_at'                => now(),
            ]);

            // Vincula el primer mensaje al hilo
            $hilo->update(['cuerpo_inicial_id' => $mensaje->id]);

            // 3. Destinatarios: familias (legajos)
            foreach ($datos['id_legajos'] as $idLegajo) {
                $legajo = Legajo::find($idLegajo);
                ComMensajeDestinatario::create([
                    'id_mensaje'        => $mensaje->id,
                    'id_hilo'           => $hilo->id,
                    'tipo_destinatario' => 'familia',
                    'id_legajo'         => $idLegajo,
                    'rol_destinatario'  => 'familia',
                    'nombre_snapshot'   => $legajo ? trim("{$legajo->apellido}, {$legajo->nombre}") : null,
                    'dni_snapshot'      => $legajo?->dni ?? null,
                ]);
            }

            // 4. Destinatarios: profesores (cuando la familia escribe a la escuela)
            foreach (($datos['destinatarios_profesores'] ?? []) as $idProf) {
                $prof = Profesor::with('tipo')->find($idProf);
                $rolDest = $prof
                    ? CanalesPolicy::rolDeProfesor($prof)
                    : (string) ($datos['rol_receptor'] ?? 'profesor');
                ComMensajeDestinatario::create([
                    'id_mensaje'        => $mensaje->id,
                    'id_hilo'           => $hilo->id,
                    'tipo_destinatario' => 'profesor',
                    'id_profesor'       => $idProf,
                    'rol_destinatario'  => $rolDest,
                    'nombre_snapshot'   => $prof ? trim("{$prof->apellido}, {$prof->nombre}") : null,
                    'dni_snapshot'      => $prof?->dni ?? null,
                ]);
            }

            // 5. Distribuir por medios
            $mensaje->load('hilo');
            Distribuidor::distribuir($mensaje, $mediosCanal);

            return $hilo;
        });
    }

    /**
     * Enlaces WhatsApp manuales generados para un mensaje (driver wa_link: wa.me o web.whatsapp.com/send).
     *
     * @return list<array{label:string, url:string}>
     */
    public static function enlacesWhatsappWaMeDelMensaje(int $idMensaje): array
    {
        $rows = DB::table('com_mensajes_envios as e')
            ->join('com_mensajes_destinatarios as d', 'd.id', '=', 'e.id_mensaje_destinatario')
            ->where('d.id_mensaje', $idMensaje)
            ->where('e.medio', 'whatsapp')
            ->where('e.estado', 'enviado')
            ->whereNotNull('e.proveedor_msgid')
            ->orderBy('d.id')
            ->get(['e.proveedor_msgid as url', 'd.nombre_snapshot as label']);

        $out = [];
        foreach ($rows as $r) {
            $url = trim((string) $r->url);
            $esManual = $url !== ''
                && (str_starts_with($url, 'https://wa.me/')
                    || str_starts_with($url, 'https://web.whatsapp.com/send'));
            if ($esManual) {
                $label = trim((string) ($r->label ?? ''));
                $out[] = [
                    'label' => $label !== '' ? $label : 'WhatsApp',
                    'url'   => $url,
                ];
            }
        }

        return $out;
    }

    /**
     * Informe de envíos del primer mensaje de un hilo: una fila por destinatario y medio.
     *
     * @return array{
     *   id_hilo:int,
     *   id_mensaje:int,
     *   asunto:string,
     *   contenido_preview:string,
     *   filas:list<array{nombre:string,tipo_destinatario:string,medio:string,estado:string,motivo:?string,proveedor_msgid:?string}>,
     *   totales:array{enviado:int,fallido:int,no_aplicable:int,pendiente:int}
     * }|null
     */
    public static function informeEnviosPrimerMensajeDelHilo(int $idHilo, int $idNivel, int $idTerlec): ?array
    {
        $hilo = DB::table('com_hilos')
            ->where('id', $idHilo)
            ->where('id_nivel', $idNivel)
            ->where('id_terlec', $idTerlec)
            ->first(['id', 'asunto', 'cuerpo_inicial_id']);

        if ($hilo === null || empty($hilo->cuerpo_inicial_id)) {
            return null;
        }

        $idMensaje = (int) $hilo->cuerpo_inicial_id;

        $contenidoPreview = (string) (DB::table('com_mensajes')
            ->where('id', $idMensaje)
            ->value('contenido') ?? '');
        if (mb_strlen($contenidoPreview) > 220) {
            $contenidoPreview = mb_substr($contenidoPreview, 0, 217) . '…';
        }

        $destRows = DB::table('com_mensajes_destinatarios')
            ->where('id_mensaje', $idMensaje)
            ->orderBy('nombre_snapshot')
            ->get(['id', 'tipo_destinatario', 'nombre_snapshot']);

        $porId = [];
        foreach ($destRows as $d) {
            $porId[(int) $d->id] = [
                'nombre'             => trim((string) ($d->nombre_snapshot ?? '')),
                'tipo_destinatario' => (string) $d->tipo_destinatario,
            ];
        }

        $totales = ['enviado' => 0, 'fallido' => 0, 'no_aplicable' => 0, 'pendiente' => 0];
        $filas   = [];

        if ($porId !== []) {
            $idsDest = array_keys($porId);
            $envios  = DB::table('com_mensajes_envios')
                ->whereIn('id_mensaje_destinatario', $idsDest)
                ->orderBy('id_mensaje_destinatario')
                ->orderBy('medio')
                ->get(['id_mensaje_destinatario', 'medio', 'estado', 'motivo', 'proveedor_msgid']);

            foreach ($envios as $e) {
                $idDest = (int) $e->id_mensaje_destinatario;
                $meta    = $porId[$idDest] ?? ['nombre' => '—', 'tipo_destinatario' => ''];
                $estado  = (string) $e->estado;
                if (isset($totales[$estado])) {
                    $totales[$estado]++;
                }
                $nombre = $meta['nombre'] !== '' ? $meta['nombre'] : '—';
                $filas[] = [
                    'nombre'              => $nombre,
                    'tipo_destinatario'  => $meta['tipo_destinatario'],
                    'medio'               => (string) $e->medio,
                    'estado'              => $estado,
                    'motivo'              => $e->motivo !== null ? (string) $e->motivo : null,
                    'proveedor_msgid'     => $e->proveedor_msgid !== null ? (string) $e->proveedor_msgid : null,
                ];
            }
        }

        return [
            'id_hilo'    => (int) $hilo->id,
            'id_mensaje' => $idMensaje,
            'asunto'             => (string) ($hilo->asunto ?? ''),
            'contenido_preview' => $contenidoPreview,
            'filas'              => $filas,
            'totales'    => $totales,
        ];
    }

    /**
     * Agrega una respuesta a un hilo existente y actualiza estados.
     *
     * @param list<string> $mediosCanal
     */
    public static function responder(
        int $idHilo,
        string $tipoRemitente,
        int $idRemitente,
        string $rolRemitente,
        string $contenido,
        array $mediosCanal,
        ?string $vinculo = null,
        ?string $nombreSnapshot = null,
        ?string $dniSnapshot = null,
        ?int $idMensajePadre = null
    ): ComMensaje {
        return DB::transaction(function () use (
            $idHilo, $tipoRemitente, $idRemitente, $rolRemitente,
            $contenido, $mediosCanal, $vinculo, $nombreSnapshot, $dniSnapshot, $idMensajePadre
        ) {
            $hilo = ComHilo::findOrFail($idHilo);

            $mensaje = ComMensaje::create([
                'id_hilo'                   => $idHilo,
                'id_mensaje_padre'          => $idMensajePadre,
                'tipo_remitente'            => $tipoRemitente,
                'id_profesor'               => $tipoRemitente === 'profesor' ? $idRemitente : null,
                'id_legajo'                 => $tipoRemitente === 'familia' ? $idRemitente : null,
                'rol_remitente'             => $rolRemitente,
                'vinculo_familiar'          => $tipoRemitente === 'familia' ? $vinculo : null,
                'nombre_remitente_snapshot' => $nombreSnapshot,
                'dni_remitente_snapshot'    => $dniSnapshot,
                'contenido'                 => $contenido,
                'fecha'                     => now()->toDateString(),
                'hora'                      => now()->toTimeString(),
                'created_at'                => now(),
            ]);

            if ($tipoRemitente === 'profesor') {
                ComMensajeDestinatario::query()
                    ->where('id_hilo', $idHilo)
                    ->where('tipo_destinatario', 'profesor')
                    ->where('id_profesor', $idRemitente)
                    ->whereNull('respondido_at')
                    ->update([
                        'respondido_at'        => now(),
                        'id_mensaje_respuesta' => $mensaje->id,
                    ]);
            } else {
                ComMensajeDestinatario::query()
                    ->where('id_hilo', $idHilo)
                    ->where('tipo_destinatario', 'familia')
                    ->where('id_legajo', $idRemitente)
                    ->whereNull('respondido_at')
                    ->update([
                        'respondido_at'        => now(),
                        'id_mensaje_respuesta' => $mensaje->id,
                    ]);
            }

            static::crearDestinatariosRespuesta($hilo, $mensaje, $tipoRemitente, $idRemitente);

            $hilo->update(['ultimo_mensaje_at' => now()]);

            $mensaje->load('hilo');
            Distribuidor::distribuir($mensaje, $mediosCanal);

            return $mensaje;
        });
    }

    /**
     * Para una respuesta, los destinatarios son los remitentes previos del hilo
     * del tipo opuesto al que responde.
     */
    private static function crearDestinatariosRespuesta(
        ComHilo $hilo,
        ComMensaje $mensaje,
        string $tipoRemitente,
        int $idRemitente
    ): void {
        if ($tipoRemitente === 'profesor' && $hilo->esComunicacionInternaDocentes()) {
            foreach (static::idsProfesoresDestinoRespuestaDocente((int) $hilo->id, $idRemitente) as $idProf) {
                static::insertarDestinatario($mensaje, (int) $hilo->id, 'profesor', $idProf);
            }

            return;
        }

        $tipoDestino = $tipoRemitente === 'profesor' ? 'familia' : 'profesor';

        $idsEnHilo = ComMensaje::query()
            ->where('id_hilo', $hilo->id)
            ->where('tipo_remitente', $tipoDestino)
            ->when($tipoDestino === 'profesor', fn ($q) => $q->whereNotNull('id_profesor'))
            ->when($tipoDestino === 'familia', fn ($q) => $q->whereNotNull('id_legajo'))
            ->distinct()
            ->get($tipoDestino === 'profesor' ? ['id_profesor'] : ['id_legajo']);

        foreach ($idsEnHilo as $row) {
            $id = $tipoDestino === 'profesor' ? $row->id_profesor : $row->id_legajo;
            static::insertarDestinatario($mensaje, (int) $hilo->id, $tipoDestino, (int) $id);
        }
    }

    private static function insertarDestinatario(
        ComMensaje $mensaje,
        int $idHilo,
        string $tipo,
        int $id
    ): void {
        if ($tipo === 'profesor') {
            $prof = Profesor::with('tipo')->find($id);
            $rolDest = $prof ? CanalesPolicy::rolDeProfesor($prof) : 'profesor';
            ComMensajeDestinatario::create([
                'id_mensaje'        => $mensaje->id,
                'id_hilo'           => $idHilo,
                'tipo_destinatario' => 'profesor',
                'id_profesor'       => $id,
                'rol_destinatario'  => $rolDest,
                'nombre_snapshot'   => $prof ? trim("{$prof->apellido}, {$prof->nombre}") : null,
                'dni_snapshot'      => $prof?->dni ?? null,
            ]);
        } else {
            $legajo = Legajo::find($id);
            ComMensajeDestinatario::create([
                'id_mensaje'        => $mensaje->id,
                'id_hilo'           => $idHilo,
                'tipo_destinatario' => 'familia',
                'id_legajo'         => $id,
                'rol_destinatario'  => 'familia',
                'nombre_snapshot'   => $legajo ? trim("{$legajo->apellido}, {$legajo->nombre}") : null,
                'dni_snapshot'      => $legajo?->dni ?? null,
            ]);
        }
    }

    /**
     * Retorna profesores del nivel/terlec de un rol específico.
     *
     * @return list<array{id:int,label:string,rol:string}>
     */
    public static function profesoresPorRol(int $idNivel, string $rol): array
    {
        return DB::table('profesores as p')
            ->join('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')
            ->where('p.nivel', $idNivel)
            ->get(['p.id', 'p.apellido', 'p.nombre', 'pt.tipo'])
            ->filter(function ($r) use ($rol) {
                return CanalesPolicy::normalizarRolProfesor((string) $r->tipo) === $rol;
            })
            ->map(fn ($r) => [
                'id'    => (int) $r->id,
                'label' => trim("{$r->apellido}, {$r->nombre}"),
                'rol'   => $rol,
            ])
            ->values()
            ->all();
    }

    /**
     * Retorna el preceptor(es) del curso de un legajo.
     *
     * @return list<array{id:int,label:string,rol:string}>
     */
    public static function preceptoresDeCurso(int $idLegajo, int $idNivel, int $idTerlec): array
    {
        $idCurso = DB::table('matricula')
            ->where('idLegajos', $idLegajo)
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->value('idCursos');

        if (! $idCurso) {
            return [];
        }

        return static::profesoresPorRol($idNivel, 'preceptor');
    }

    /**
     * Contadores para el panel de inicio: mensajes no leídos (recibidos) y destinatarios
     * que aún no abrieron mensajes enviados por el profesor en el ciclo activo.
     *
     * @return array{
     *   mensajes_no_leidos:int,
     *   hilos_con_no_leidos:int,
     *   destinatarios_sin_leer:int,
     *   hilos_enviados_pendientes_lectura:int,
     *   hilos_total:int
     * }
     */
    public static function resumenBandejaProfesor(int $idProfesor, int $idNivel, int $idTerlec): array
    {
        $mensajesNoLeidos = (int) DB::table('com_mensajes_destinatarios as d')
            ->join('com_hilos as h', 'h.id', '=', 'd.id_hilo')
            ->where('h.id_nivel', $idNivel)
            ->where('h.id_terlec', $idTerlec)
            ->where('d.tipo_destinatario', 'profesor')
            ->where('d.id_profesor', $idProfesor)
            ->whereNull('d.leido_at')
            ->count();

        $hilosConNoLeidos = (int) DB::table('com_hilos as h')
            ->where('h.id_nivel', $idNivel)
            ->where('h.id_terlec', $idTerlec)
            ->where(function ($q) use ($idProfesor) {
                $q->where('h.creado_por_tipo', '!=', 'profesor')
                    ->orWhere('h.creado_por_id', '!=', $idProfesor);
            })
            ->whereExists(function ($sub) use ($idProfesor) {
                $sub->select(DB::raw(1))
                    ->from('com_mensajes_destinatarios as d')
                    ->whereColumn('d.id_hilo', 'h.id')
                    ->where('d.tipo_destinatario', 'profesor')
                    ->where('d.id_profesor', $idProfesor)
                    ->whereNull('d.leido_at');
            })
            ->count();

        $destSinLeer = (int) DB::table('com_mensajes_destinatarios as d')
            ->join('com_mensajes as m', 'm.id', '=', 'd.id_mensaje')
            ->join('com_hilos as h', 'h.id', '=', 'm.id_hilo')
            ->where('h.id_nivel', $idNivel)
            ->where('h.id_terlec', $idTerlec)
            ->where('m.tipo_remitente', 'profesor')
            ->where('m.id_profesor', $idProfesor)
            ->whereNull('d.leido_at')
            ->where(function ($q) use ($idProfesor) {
                $q->where('d.tipo_destinatario', '!=', 'profesor')
                    ->orWhere('d.id_profesor', '!=', $idProfesor)
                    ->orWhereNull('d.id_profesor');
            })
            ->count();

        $hilosEnviadosPendientes = (int) DB::table('com_hilos as h')
            ->where('h.id_nivel', $idNivel)
            ->where('h.id_terlec', $idTerlec)
            ->where('h.creado_por_tipo', 'profesor')
            ->where('h.creado_por_id', $idProfesor)
            ->whereExists(function ($sub) use ($idProfesor) {
                $sub->select(DB::raw(1))
                    ->from('com_mensajes as m')
                    ->join('com_mensajes_destinatarios as d', 'd.id_mensaje', '=', 'm.id')
                    ->whereColumn('m.id_hilo', 'h.id')
                    ->where('m.tipo_remitente', 'profesor')
                    ->where('m.id_profesor', $idProfesor)
                    ->whereNull('d.leido_at')
                    ->where(function ($q) use ($idProfesor) {
                        $q->where('d.tipo_destinatario', '!=', 'profesor')
                            ->orWhere('d.id_profesor', '!=', $idProfesor)
                            ->orWhereNull('d.id_profesor');
                    });
            })
            ->count();

        $hilosTotal = (int) DB::table('com_hilos as h')
            ->where('h.id_nivel', $idNivel)
            ->where('h.id_terlec', $idTerlec)
            ->where(function ($q) use ($idProfesor) {
                $q->where(function ($q2) use ($idProfesor) {
                    $q2->where('h.creado_por_tipo', 'profesor')
                        ->where('h.creado_por_id', $idProfesor);
                })->orWhereExists(function ($sub) use ($idProfesor) {
                    $sub->select(DB::raw(1))
                        ->from('com_mensajes_destinatarios as d2')
                        ->whereColumn('d2.id_hilo', 'h.id')
                        ->where('d2.tipo_destinatario', 'profesor')
                        ->where('d2.id_profesor', $idProfesor);
                });
            })
            ->count();

        return [
            'mensajes_no_leidos'                  => $mensajesNoLeidos,
            'hilos_con_no_leidos'                 => $hilosConNoLeidos,
            'destinatarios_sin_leer'              => $destSinLeer,
            'hilos_enviados_pendientes_lectura'   => $hilosEnviadosPendientes,
            'hilos_total'                         => $hilosTotal,
        ];
    }

    /**
     * Resumen de bandeja para el escritorio del portal familia.
     *
     * @return array{
     *   mensajes_no_leidos:int,
     *   hilos_con_no_leidos:int,
     *   destinatarios_sin_leer:int,
     *   hilos_enviados_pendientes_lectura:int,
     *   hilos_total:int
     * }
     */
    public static function resumenBandejaFamilia(int $idLegajo, int $idNivel, int $idTerlec): array
    {
        $mensajesNoLeidos = (int) DB::table('com_mensajes_destinatarios as d')
            ->join('com_hilos as h', 'h.id', '=', 'd.id_hilo')
            ->where('h.id_nivel', $idNivel)
            ->where('h.id_terlec', $idTerlec)
            ->where('d.tipo_destinatario', 'familia')
            ->where('d.id_legajo', $idLegajo)
            ->whereNull('d.leido_at')
            ->count();

        $hilosConNoLeidos = (int) DB::table('com_hilos as h')
            ->where('h.id_nivel', $idNivel)
            ->where('h.id_terlec', $idTerlec)
            ->where(function ($q) use ($idLegajo) {
                $q->where('h.creado_por_tipo', '!=', 'familia')
                    ->orWhere('h.creado_por_id', '!=', $idLegajo);
            })
            ->whereExists(function ($sub) use ($idLegajo) {
                $sub->select(DB::raw(1))
                    ->from('com_mensajes_destinatarios as d')
                    ->whereColumn('d.id_hilo', 'h.id')
                    ->where('d.tipo_destinatario', 'familia')
                    ->where('d.id_legajo', $idLegajo)
                    ->whereNull('d.leido_at');
            })
            ->count();

        $destSinLeer = (int) DB::table('com_mensajes_destinatarios as d')
            ->join('com_mensajes as m', 'm.id', '=', 'd.id_mensaje')
            ->join('com_hilos as h', 'h.id', '=', 'm.id_hilo')
            ->where('h.id_nivel', $idNivel)
            ->where('h.id_terlec', $idTerlec)
            ->where('m.tipo_remitente', 'familia')
            ->where('m.id_legajo', $idLegajo)
            ->whereNull('d.leido_at')
            ->where('d.tipo_destinatario', 'profesor')
            ->count();

        $hilosEnviadosPendientes = (int) DB::table('com_hilos as h')
            ->where('h.id_nivel', $idNivel)
            ->where('h.id_terlec', $idTerlec)
            ->where('h.creado_por_tipo', 'familia')
            ->where('h.creado_por_id', $idLegajo)
            ->whereExists(function ($sub) use ($idLegajo) {
                $sub->select(DB::raw(1))
                    ->from('com_mensajes as m')
                    ->join('com_mensajes_destinatarios as d', 'd.id_mensaje', '=', 'm.id')
                    ->whereColumn('m.id_hilo', 'h.id')
                    ->where('m.tipo_remitente', 'familia')
                    ->where('m.id_legajo', $idLegajo)
                    ->whereNull('d.leido_at')
                    ->where('d.tipo_destinatario', 'profesor');
            })
            ->count();

        $hilosTotal = (int) DB::table('com_hilos as h')
            ->where('h.id_nivel', $idNivel)
            ->where('h.id_terlec', $idTerlec)
            ->where(function ($q) use ($idLegajo) {
                $q->where(function ($q2) use ($idLegajo) {
                    $q2->where('h.creado_por_tipo', 'familia')
                        ->where('h.creado_por_id', $idLegajo);
                })->orWhereExists(function ($sub) use ($idLegajo) {
                    $sub->select(DB::raw(1))
                        ->from('com_mensajes_destinatarios as d2')
                        ->whereColumn('d2.id_hilo', 'h.id')
                        ->where('d2.tipo_destinatario', 'familia')
                        ->where('d2.id_legajo', $idLegajo);
                });
            })
            ->count();

        return [
            'mensajes_no_leidos'                  => $mensajesNoLeidos,
            'hilos_con_no_leidos'                 => $hilosConNoLeidos,
            'destinatarios_sin_leer'              => $destSinLeer,
            'hilos_enviados_pendientes_lectura'   => $hilosEnviadosPendientes,
            'hilos_total'                         => $hilosTotal,
        ];
    }
}
