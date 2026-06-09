<?php

namespace App\Support\Alumnos;

use App\Models\Legajo;
use App\Models\Matricula;
use App\Models\Sexo;
use App\Support\InformeInasistencias;
use Carbon\CarbonInterface;

/**
 * Datos para la ficha de matrícula (portal alumno), equivalente al SELECT legacy sobre `legajos`.
 */
final class FichaMatriculaDatos
{
    /** @var list<string> */
    private const COLUMNAS_LEGAJO = [
        'codigo', 'apellido', 'nombre', 'dni', 'cuil', 'sexo', 'fechnaci', 'ln_ciudad', 'nacion',
        'callenum', 'barrio', 'localidad', 'telefono', 'email',
        'nombrepad', 'dnipad', 'telepad', 'emailpad', 'ocupacpad',
        'nombremad', 'dnimad', 'telemad', 'emailmad', 'ocupacmad',
        'ec_padres', 'vivecon', 'contacto1', 'contacto2', 'contacto3', 'obs_web',
        'needes', 'needes_detalle', 'certDisc',
        'nombretut', 'dnitut', 'teletut', 'ocupactut', 'emailtut',
        'legajo', 'escori', 'retira1', 'retira2',
        'reglamApenom', 'reglamDni', 'reglamEmail',
    ];

    /**
     * Datos para secretaría (matrícula en contexto escolar activo).
     *
     * @return array<string, mixed>|null
     */
    public static function paraMatricula(int $idMatricula): ?array
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        /** @var Matricula|null $matricula */
        $matricula = Matricula::query()
            ->with('curso')
            ->where('id', $idMatricula)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->whereNull('fechaBaja')
            ->first();

        if ($matricula === null) {
            return null;
        }

        $legajo = Legajo::query()
            ->where('id', (int) $matricula->idLegajos)
            ->first(self::COLUMNAS_LEGAJO);

        if ($legajo === null) {
            return null;
        }

        return self::armarDesdeRegistros($legajo, $matricula, (int) $ctx->idNivel, (int) ($ctx->terlecAno() ?? 0));
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

        $matricula = InformeInasistencias::matriculaAutogestion();
        if ($matricula === null) {
            return null;
        }

        $legajo = Legajo::query()
            ->where('id', (int) $ctx->idLegajo)
            ->first(self::COLUMNAS_LEGAJO);

        if ($legajo === null) {
            return null;
        }

        return self::armarDesdeRegistros(
            $legajo,
            $matricula,
            (int) $ctx->idNivel,
            (int) ($ctx->terlecAno() ?? 0),
            true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function armarDesdeRegistros(
        Legajo $legajo,
        Matricula $matricula,
        int $idNivel,
        int $anoTerlec,
        bool $usarLegajoComoNroMatricula = false,
    ): array {
        $header = studentPdfHeaderData();
        $curso = trim((string) ($matricula->curso?->nombreParaListado() ?? ''));
        $nroMatricula = $usarLegajoComoNroMatricula
            ? trim((string) ($legajo->legajo ?? ''))
            : trim((string) ($matricula->nroMatricula ?? ''));

        if ($nroMatricula === '') {
            $nroMatricula = trim((string) ($legajo->legajo ?? ''));
        }

        return [
            'header' => $header,
            'cicloLectivo' => $anoTerlec > 0 ? (string) $anoTerlec : '',
            'nroMatricula' => $nroMatricula,
            'curso' => $curso,
            'idNivel' => $idNivel,
            'mostrarRetira2' => $idNivel !== 3,
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
            'escori' => trim((string) ($legajo->escori ?? '')),
            'needes' => trim((string) ($legajo->needes ?? '')),
            'needes_detalle' => trim((string) ($legajo->needes_detalle ?? '')),
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
            'nombretut' => trim((string) ($legajo->nombretut ?? '')),
            'dnitut' => trim((string) ($legajo->dnitut ?? '')),
            'teletut' => trim((string) ($legajo->teletut ?? '')),
            'emailtut' => trim((string) ($legajo->emailtut ?? '')),
            'ocupactut' => trim((string) ($legajo->ocupactut ?? '')),
            'ec_padres' => trim((string) ($legajo->ec_padres ?? '')),
            'vivecon' => trim((string) ($legajo->vivecon ?? '')),
            'contacto1' => trim((string) ($legajo->contacto1 ?? '')),
            'contacto2' => trim((string) ($legajo->contacto2 ?? '')),
            'contacto3' => trim((string) ($legajo->contacto3 ?? '')),
            'retira1' => trim((string) ($legajo->retira1 ?? '')),
            'retira2' => trim((string) ($legajo->retira2 ?? '')),
            'obs_web' => trim((string) ($legajo->obs_web ?? '')),
            'reglamApenom' => trim((string) ($legajo->reglamApenom ?? '')),
            'reglamDni' => trim((string) ($legajo->reglamDni ?? '')),
            'reglamEmail' => trim((string) ($legajo->reglamEmail ?? '')),
        ];
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
