<?php

namespace App\Support\Alumnos;

use App\Models\Ento;
use App\Models\Legajo;
use App\Models\Matricula;
use App\Models\Sexo;
use App\Models\Terlec;
use Illuminate\Support\Facades\Storage;
use App\Support\Listados\EstudiantesDatosConsulta;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

/**
 * Datos para la ficha de matrícula (portal alumno / secretaría).
 * El SELECT de `legajos` solo pide columnas usadas por la variante PDF del tenant.
 */
final class FichaMatriculaDatos
{
    /**
     * Columnas de `legajos` usadas por `FichaMatriculaIessTcpdf`.
     *
     * @var list<string>
     */
    private const COLUMNAS_LEGAJO_IESS = [
        'apellido', 'nombre', 'dni', 'sexo', 'fechnaci', 'ln_ciudad', 'nacion',
        'callenum', 'barrio', 'localidad', 'telefono', 'email',
        'nombrepad', 'dnipad', 'telepad', 'emailpad', 'ocupacpad',
        'nombremad', 'dnimad', 'telemad', 'emailmad', 'ocupacmad',
        'vivecon', 'legajo', 'obs_web', 'grupsang',
    ];

    /**
     * Columnas de `legajos` usadas por `FichaMatriculaConAceptacionTcpdf`.
     *
     * @var list<string>
     */
    private const COLUMNAS_LEGAJO_ACEPTACION = [
        'apellido', 'nombre', 'dni', 'sexo', 'fechnaci', 'ln_ciudad', 'nacion',
        'callenum', 'barrio', 'localidad', 'telefono', 'email',
        'nombrepad', 'dnipad', 'telepad', 'emailpad', 'ocupacpad',
        'nombremad', 'dnimad', 'telemad', 'emailmad', 'ocupacmad',
        'ec_padres', 'vivecon', 'contacto1', 'contacto2', 'contacto3', 'obs_web',
        'needes', 'needes_detalle',
        'nombretut', 'dnitut', 'teletut', 'ocupactut', 'emailtut',
        'respAdmiNom', 'respAdmiDni',
        'legajo', 'escori', 'retira1', 'retira2',
        'reglamApenom', 'reglamDni', 'reglamEmail',
    ];

    /** @return list<string> */
    private static function columnasLegajoSelect(): array
    {
        $candidatas = self::implementacionActual() === 'iess'
            ? self::COLUMNAS_LEGAJO_IESS
            : self::COLUMNAS_LEGAJO_ACEPTACION;

        $columnas = [];
        foreach ($candidatas as $columna) {
            if (Schema::hasColumn('legajos', $columna)) {
                $columnas[] = $columna;
            }
        }

        if (self::implementacionActual() === 'iess') {
            $colGrupo = EstudiantesDatosConsulta::columnaGrupoSanguineo();
            if ($colGrupo !== null && ! in_array($colGrupo, $columnas, true)) {
                $columnas[] = $colGrupo;
            }

            if (Schema::hasColumn('legajos', 'obs') && ! in_array('obs', $columnas, true)) {
                $columnas[] = 'obs';
            }
        }

        if ($columnas === []) {
            return ['id', 'apellido', 'nombre'];
        }

        return $columnas;
    }

    private static function implementacionActual(): string
    {
        $secretaria = tenantSecretariaFichaMatriculaImplementacion();
        if (filled($secretaria)) {
            return (string) $secretaria;
        }

        return (string) config('tenant.autogestion.ficha_matricula.implementacion', 'sanfranciscoasis');
    }

    /**
     * Datos para secretaría (matrícula en contexto escolar activo).
     *
     * @return array<string, mixed>|null
     */
    public static function paraMatricula(int $idMatricula, ?int $idTerlec = null): ?array
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $idTerlec = self::idTerlecEfectivo($idTerlec);

        /** @var Matricula|null $matricula */
        $matricula = Matricula::query()
            ->with(['curso.nivel'])
            ->where('id', $idMatricula)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', $idTerlec)
            ->whereNull('fechaBaja')
            ->first();

        if ($matricula === null) {
            return null;
        }

        $legajo = Legajo::query()
            ->where('id', (int) $matricula->idLegajos)
            ->first(self::columnasLegajoSelect());

        if ($legajo === null) {
            return null;
        }

        return self::armarDesdeRegistros(
            $legajo,
            $matricula,
            (int) $ctx->idNivel,
            self::anoDeTerlec($idTerlec),
            schoolPdfHeaderData(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function paraAutogestion(): ?array
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $matricula = MatriculaFamiliaCiclo::matricula();
        if ($matricula === null) {
            return null;
        }

        $legajo = Legajo::query()
            ->where('id', (int) $ctx->idLegajo)
            ->first(self::columnasLegajoSelect());

        if ($legajo === null) {
            return null;
        }

        $idNivel = (int) ($matricula->idNivel ?? 0);
        if ($idNivel < 1) {
            $idNivel = (int) $ctx->idNivel;
        }

        return self::armarDesdeRegistros(
            $legajo,
            $matricula,
            $idNivel,
            MatriculaFamiliaCiclo::ano($matricula),
            studentPdfHeaderData($idNivel),
            true,
        );
    }

    /**
     * @param  array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string}  $header
     * @return array<string, mixed>
     */
    private static function armarDesdeRegistros(
        Legajo $legajo,
        Matricula $matricula,
        int $idNivel,
        int $anoTerlec,
        array $header,
        bool $usarLegajoComoNroMatricula = false,
    ): array {
        $cursoModel = $matricula->curso;
        $curso = trim((string) ($cursoModel?->nombreParaListado() ?? ''));
        $legajoNumero = trim((string) ($legajo->legajo ?? ''));
        $nroMatricula = $usarLegajoComoNroMatricula
            ? $legajoNumero
            : trim((string) ($matricula->nroMatricula ?? ''));

        if ($nroMatricula === '') {
            $nroMatricula = $legajoNumero;
        }

        $obs = trim((string) ($legajo->obs ?? ''));
        if ($obs === '') {
            $obs = trim((string) ($legajo->obs_web ?? ''));
        }

        $header = self::headerConLogo($header, $idNivel);

        $base = [
            'header' => $header,
            'cicloLectivo' => $anoTerlec > 0 ? (string) $anoTerlec : '',
            'nroMatricula' => $nroMatricula,
            'legajo' => $legajoNumero,
            'curso' => $curso,
            'cursoC' => trim((string) ($cursoModel?->c ?? '')),
            'cursoS' => trim((string) ($cursoModel?->s ?? '')),
            'nivelAbrev' => trim((string) ($cursoModel?->nivel?->abrev ?? '')),
            'idNivel' => $idNivel,
            'apellido' => trim((string) ($legajo->apellido ?? '')),
            'nombre' => trim((string) ($legajo->nombre ?? '')),
            'dni' => trim((string) ($legajo->dni ?? '')),
            'sexo' => Sexo::etiquetaParaValorAlmacenado($legajo->sexo),
            'fechnaci' => self::fecha($legajo->fechnaci),
            'ln_ciudad' => trim((string) ($legajo->ln_ciudad ?? '')),
            'nacion' => trim((string) ($legajo->nacion ?? '')),
            'callenum' => trim((string) ($legajo->callenum ?? '')),
            'barrio' => trim((string) ($legajo->barrio ?? '')),
            'localidad' => trim((string) ($legajo->localidad ?? '')),
            'telefono' => trim((string) ($legajo->telefono ?? '')),
            'email' => trim((string) ($legajo->email ?? '')),
            'nombremad' => trim((string) ($legajo->nombremad ?? '')),
            'dnimad' => trim((string) ($legajo->dnimad ?? '')),
            'telemad' => trim((string) ($legajo->telemad ?? '')),
            'emailmad' => trim((string) ($legajo->emailmad ?? '')),
            'ocupacmad' => trim((string) ($legajo->ocupacmad ?? '')),
            'nombrepad' => trim((string) ($legajo->nombrepad ?? '')),
            'dnipad' => trim((string) ($legajo->dnipad ?? '')),
            'telepad' => trim((string) ($legajo->telepad ?? '')),
            'emailpad' => trim((string) ($legajo->emailpad ?? '')),
            'ocupacpad' => trim((string) ($legajo->ocupacpad ?? '')),
            'vivecon' => trim((string) ($legajo->vivecon ?? '')),
            'obs' => $obs,
            'obs_web' => trim((string) ($legajo->obs_web ?? '')),
        ];

        if (self::implementacionActual() === 'iess') {
            $base['grupsang'] = EstudiantesDatosConsulta::valorGrupoSanguineo($legajo);

            return $base;
        }

        $extra = [
            'mostrarRetira2' => $idNivel !== 3,
            'escori' => trim((string) ($legajo->escori ?? '')),
            'needes' => trim((string) ($legajo->needes ?? '')),
            'needes_detalle' => trim((string) ($legajo->needes_detalle ?? '')),
            'nombretut' => trim((string) ($legajo->nombretut ?? '')),
            'dnitut' => trim((string) ($legajo->dnitut ?? '')),
            'teletut' => trim((string) ($legajo->teletut ?? '')),
            'emailtut' => trim((string) ($legajo->emailtut ?? '')),
            'ocupactut' => trim((string) ($legajo->ocupactut ?? '')),
            'ec_padres' => trim((string) ($legajo->ec_padres ?? '')),
            'contacto1' => trim((string) ($legajo->contacto1 ?? '')),
            'contacto2' => trim((string) ($legajo->contacto2 ?? '')),
            'contacto3' => trim((string) ($legajo->contacto3 ?? '')),
            'retira1' => trim((string) ($legajo->retira1 ?? '')),
            'retira2' => trim((string) ($legajo->retira2 ?? '')),
            'respAdmiNom' => trim((string) ($legajo->respAdmiNom ?? '')),
            'respAdmiDni' => trim((string) ($legajo->respAdmiDni ?? '')),
            'reglamApenom' => trim((string) ($legajo->reglamApenom ?? '')),
            'reglamDni' => trim((string) ($legajo->reglamDni ?? '')),
            'reglamEmail' => trim((string) ($legajo->reglamEmail ?? '')),
        ];

        if (AutorizadosEmergenciaRetiro::habilitado()) {
            $extra['autorizadosEmergencia'] = AutorizadosEmergenciaRetiro::filasDeLegajo((int) $matricula->idLegajos);
        }

        return array_merge($base, $extra);
    }

    /**
     * El logo de secretaría sale del nivel de la sesión del personal.
     * En autogestión ese archivo a veces no se resuelve: se busca el del nivel
     * del estudiante y, si no está, cualquier logo institucional que exista.
     *
     * @param  array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string}  $header
     * @return array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string}
     */
    private static function headerConLogo(array $header, int $idNivel): array
    {
        $actual = $header['logo_file'] ?? null;
        if (is_string($actual) && $actual !== '' && is_file($actual)) {
            return $header;
        }

        $header['logo_file'] = self::buscarLogoAbsoluto($idNivel);

        return $header;
    }

    private static function buscarLogoAbsoluto(int $idNivel): ?string
    {
        $rutas = [];

        if ($idNivel > 0) {
            $propia = Ento::query()->where('idNivel', $idNivel)->value('logo_path');
            if (is_string($propia) && trim($propia) !== '') {
                $rutas[] = trim($propia);
            }
        }

        $otras = Ento::query()
            ->whereNotNull('logo_path')
            ->where('logo_path', '<>', '')
            ->orderBy('idNivel')
            ->pluck('logo_path');

        foreach ($otras as $ruta) {
            if (is_string($ruta) && trim($ruta) !== '') {
                $rutas[] = trim($ruta);
            }
        }

        $login = entoLoginLogoStoragePath();
        if (is_string($login) && $login !== '') {
            $rutas[] = $login;
        }

        foreach ($rutas as $ruta) {
            $abs = self::absolutoSiExiste($ruta);
            if ($abs !== null) {
                return $abs;
            }
        }

        return null;
    }

    private static function absolutoSiExiste(string $ruta): ?string
    {
        $relativa = ltrim(str_replace('\\', '/', trim($ruta)), '/');
        if ($relativa === '' || str_contains($relativa, '..')) {
            return null;
        }

        $candidatas = [];
        if (is_file($ruta)) {
            $candidatas[] = $ruta;
        }
        $candidatas[] = Storage::disk('public')->path($relativa);
        $candidatas[] = public_path('storage/'.$relativa);
        $candidatas[] = storage_path('app/public/'.$relativa);

        foreach ($candidatas as $abs) {
            if ($abs !== '' && is_file($abs)) {
                return $abs;
            }
        }

        return null;
    }

    private static function idTerlecEfectivo(?int $idTerlec): int
    {
        $id = $idTerlec ?? (int) (schoolCtx()->idTerlec ?? 0);

        return $id > 0 ? $id : 0;
    }

    private static function anoDeTerlec(int $idTerlec): int
    {
        $ctx = schoolCtx();
        if ($idTerlec > 0 && $idTerlec === (int) $ctx->idTerlec) {
            return (int) ($ctx->terlecAno() ?? 0);
        }

        return (int) (Terlec::query()->whereKey($idTerlec)->value('ano') ?? 0);
    }

    private static function fecha(mixed $valor): string
    {
        if ($valor instanceof CarbonInterface) {
            return $valor->format('d/m/Y');
        }

        if ($valor === null || $valor === '') {
            return '';
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($texto)->format('d/m/Y');
        } catch (\Throwable) {
            return $texto;
        }
    }
}
