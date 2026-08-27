<?php

namespace App\Support\Certificados;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\NivelSistema;
use App\Support\Pdf\PdfPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Certificado de Jardín (sala de 5) y Certificado de Sexto Grado.
 */
final class CertificadoFinalizacionNivel
{
    public const TIPO_JARDIN = 'jardin';

    public const TIPO_SEXTO = 'sexto';

    public const MAX_MATRICULAS = 80;

    public const PASO_CURSOS = 'cursos';

    public const PASO_ALUMNOS = 'alumnos';

    public const PASO_FORMULARIO = 'formulario';

    public static function tipoValido(?string $tipo): ?string
    {
        return match ($tipo) {
            self::TIPO_JARDIN, self::TIPO_SEXTO => $tipo,
            default => null,
        };
    }

    public static function tipoDesdeRuta(?string $routeName): ?string
    {
        return match ($routeName) {
            'certificados.jardin', 'certificados.jardin.pdf' => self::TIPO_JARDIN,
            'certificados.sextoGrado', 'certificados.sextoGrado.pdf' => self::TIPO_SEXTO,
            default => null,
        };
    }

    public static function nivelRequerido(string $tipo): int
    {
        return $tipo === self::TIPO_JARDIN
            ? NivelSistema::INICIAL
            : NivelSistema::PRIMARIO;
    }

    public static function titulo(string $tipo): string
    {
        return $tipo === self::TIPO_JARDIN
            ? 'Certificado Jardín'
            : 'Certificado Sexto Grado';
    }

    public static function tabla(string $tipo): string
    {
        return $tipo === self::TIPO_JARDIN
            ? 'certificadojardin'
            : 'certificadosextogrado';
    }

    public static function gradoPedagogico(string $tipo): int
    {
        return $tipo === self::TIPO_JARDIN ? 5 : 6;
    }

    public static function abortSiNivelIncorrecto(string $tipo): void
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);

        if ($tipo === self::TIPO_JARDIN) {
            abort_unless(
                NivelSistema::esInicial($idNivel),
                403,
                'El Certificado Jardín corresponde al nivel inicial. Cambie el contexto de nivel en el menú lateral.'
            );

            return;
        }

        abort_unless(
            NivelSistema::esPrimario($idNivel),
            403,
            'El Certificado de Sexto Grado corresponde al nivel primario. Cambie el contexto de nivel en el menú lateral.'
        );
    }

    /**
     * Cursos del ciclo: sala de 5 (inicial) o sextos (primario).
     *
     * @return Collection<int, Curso>
     */
    public static function cursosImplicados(string $tipo): Collection
    {
        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;
        $grado = self::gradoPedagogico($tipo);

        if ($idNivel < 1 || $idTerlec < 1) {
            return collect();
        }

        if ($idNivel !== self::nivelRequerido($tipo)) {
            return collect();
        }

        return Curso::query()
            ->with(['curplan', 'turnoClase'])
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->where(function ($q) use ($grado, $tipo) {
                $q->where('c', $grado);
                if ($tipo === self::TIPO_SEXTO) {
                    $q->orWhere('cursec', 'like', '%SEXTO%');
                }
                if ($tipo === self::TIPO_JARDIN) {
                    $q->orWhere('cursec', 'like', '%SALA DE 5%')
                        ->orWhere('cursec', 'like', '%SALA 5%');
                }
            })
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    public static function cursoImplicadoValido(string $tipo, int $cursoId): bool
    {
        if ($cursoId <= 0) {
            return false;
        }

        return self::cursosImplicados($tipo)->contains(fn (Curso $c) => (int) $c->Id === $cursoId);
    }

    /**
     * @return Collection<int, Matricula>
     */
    public static function matriculasDelCurso(string $tipo, int $cursoId): Collection
    {
        if (! self::cursoImplicadoValido($tipo, $cursoId)) {
            return collect();
        }

        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        return self::queryRegularesCurso($cursoId, $idNivel, $idTerlec)
            ->with('legajo')
            ->get()
            ->sortBy(function (Matricula $m) {
                $a = mb_strtolower((string) ($m->legajo?->apellido ?? ''));
                $n = mb_strtolower((string) ($m->legajo?->nombre ?? ''));

                return [$a, $n];
            })
            ->values();
    }

    /**
     * @param  list<int>  $idsSolicitados
     * @return list<int>
     */
    public static function resolverIdsMatriculas(string $tipo, int $cursoId, array $idsSolicitados): array
    {
        if (! self::cursoImplicadoValido($tipo, $cursoId)) {
            return [];
        }

        $parsed = collect($idsSolicitados)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($parsed->isEmpty() || $parsed->count() > self::MAX_MATRICULAS) {
            return [];
        }

        $permitidos = self::matriculasDelCurso($tipo, $cursoId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $parsed
            ->filter(fn (int $id) => in_array($id, $permitidos, true))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     serie: string,
     *     mesApro: string,
     *     anoApro: string,
     *     diaEmision: string,
     *     mesEmision: string,
     *     anoEmision: string,
     *     ppi: string
     * }
     */
    public static function valoresPorDefecto(): array
    {
        $ahora = now();
        $anoLectivo = (int) (schoolCtx()->terlecAno() ?? $ahora->year);

        return [
            'serie' => (string) $anoLectivo,
            'mesApro' => 'diciembre',
            'anoApro' => (string) $anoLectivo,
            'diaEmision' => $ahora->format('d'),
            'mesEmision' => CertificadoFinalizacionTextoEs::mesNombre((int) $ahora->format('n')),
            'anoEmision' => (string) $ahora->year,
            'ppi' => '',
        ];
    }

    /**
     * @return array{
     *     serie: string,
     *     mesApro: string,
     *     anoApro: string,
     *     diaEmision: string,
     *     mesEmision: string,
     *     anoEmision: string,
     *     ppi: string
     * }
     */
    public static function datosComunes(string $tipo): array
    {
        $defaults = self::valoresPorDefecto();
        $tabla = self::tabla($tipo);
        if (! Schema::hasTable($tabla)) {
            return $defaults;
        }

        $row = DB::table($tabla)->where('id', 1)->first();
        if ($row === null) {
            return $defaults;
        }

        $arr = (array) $row;

        $serie = trim((string) ($arr['serie'] ?? ''));
        $mesApro = trim((string) ($arr['mesApro'] ?? $arr['mesAprobacion'] ?? ''));
        $anoApro = trim((string) ($arr['anoApro'] ?? $arr['anoAprobacion'] ?? ''));
        $diaEmision = trim((string) ($arr['diaEmision'] ?? ''));
        $mesEmision = trim((string) ($arr['mesEmision'] ?? ''));
        $anoEmision = trim((string) ($arr['anoEmision'] ?? ''));
        $ppi = trim((string) ($arr['ppi'] ?? ''));

        return [
            'serie' => $serie !== '' ? $serie : $defaults['serie'],
            'mesApro' => $mesApro !== '' ? $mesApro : $defaults['mesApro'],
            'anoApro' => $anoApro !== '' ? $anoApro : $defaults['anoApro'],
            'diaEmision' => $diaEmision !== '' ? $diaEmision : $defaults['diaEmision'],
            'mesEmision' => $mesEmision !== '' ? $mesEmision : $defaults['mesEmision'],
            'anoEmision' => $anoEmision !== '' ? $anoEmision : $defaults['anoEmision'],
            'ppi' => $ppi,
        ];
    }

    /**
     * @param  array{
     *     serie: string,
     *     mesApro: string,
     *     anoApro: string,
     *     diaEmision: string,
     *     mesEmision: string,
     *     anoEmision: string,
     *     ppi: string
     * }  $datos
     * @return array{ok: bool, error?: string}
     */
    public static function guardarDatosComunes(string $tipo, array $datos): array
    {
        $tabla = self::tabla($tipo);
        $payload = self::payloadColumnas($tabla, $datos);
        $payload['id'] = 1;

        $preparado = PersistenciaColumnas::prepararPayload($tabla, $payload, ['id']);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            return [
                'ok' => false,
                'error' => PersistenciaColumnas::mensajeColumnasInexistentes(
                    $tabla,
                    $preparado['columnas_con_valor_sin_columna']
                ),
            ];
        }

        $paraGuardar = $preparado['payload'];
        $paraGuardar['id'] = 1;

        try {
            if (! Schema::hasTable($tabla)) {
                return [
                    'ok' => false,
                    'error' => PersistenciaColumnas::mensajeColumnasInexistentes($tabla, array_keys($payload)),
                ];
            }

            $existe = DB::table($tabla)->where('id', 1)->exists();
            if ($existe) {
                $update = $paraGuardar;
                unset($update['id']);
                DB::table($tabla)->where('id', 1)->update($update);
            } else {
                DB::table($tabla)->insert($paraGuardar);
            }
        } catch (QueryException $e) {
            return [
                'ok' => false,
                'error' => PersistenciaColumnas::mensajeDesdeQueryException($e) ?? 'No se pudieron guardar los datos del certificado.',
            ];
        }

        $esperados = $paraGuardar;
        unset($esperados['id']);
        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas($tabla, ['id' => 1], $esperados);
        if ($noPersistidas !== []) {
            return [
                'ok' => false,
                'error' => PersistenciaColumnas::mensajeColumnasNoPersistidas($tabla, $noPersistidas),
            ];
        }

        return ['ok' => true];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function reglasFormulario(): array
    {
        return [
            'serie' => ['nullable', 'string', 'max:50'],
            'mesApro' => ['required', 'string', 'max:40'],
            'anoApro' => ['required', 'string', 'max:20'],
            'diaEmision' => ['required', 'string', 'max:40'],
            'mesEmision' => ['required', 'string', 'max:40'],
            'anoEmision' => ['required', 'string', 'max:20'],
            'ppi' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajesValidacion(): array
    {
        return [
            'mesApro.required' => 'Indique el mes de aprobación.',
            'anoApro.required' => 'Indique el año de aprobación.',
            'diaEmision.required' => 'Indique el día de emisión.',
            'mesEmision.required' => 'Indique el mes de emisión.',
            'anoEmision.required' => 'Indique el año de emisión.',
        ];
    }

    /**
     * @param  array{
     *     serie: string,
     *     mesApro: string,
     *     anoApro: string,
     *     diaEmision: string,
     *     mesEmision: string,
     *     anoEmision: string,
     *     ppi: string
     * }  $form
     * @param  list<int>  $idsMatricula
     * @return array{action: string, fields: array<string, mixed>}
     */
    public static function pdfPost(string $tipo, int $cursoId, array $idsMatricula, array $form): array
    {
        $ruta = $tipo === self::TIPO_JARDIN
            ? 'certificados.jardin.pdf'
            : 'certificados.sextoGrado.pdf';

        return PdfPost::datos(route($ruta), [
            'curso' => $cursoId,
            'matriculas' => $idsMatricula,
            'serie' => $form['serie'],
            'mesApro' => $form['mesApro'],
            'anoApro' => $form['anoApro'],
            'diaEmision' => $form['diaEmision'],
            'mesEmision' => $form['mesEmision'],
            'anoEmision' => $form['anoEmision'],
            'ppi' => $form['ppi'],
        ]);
    }

    /**
     * @param  array{
     *     serie: string,
     *     mesApro: string,
     *     anoApro: string,
     *     diaEmision: string,
     *     mesEmision: string,
     *     anoEmision: string,
     *     ppi: string
     * }  $datos
     * @return array<string, mixed>
     */
    private static function payloadColumnas(string $tabla, array $datos): array
    {
        $payload = [];

        if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'serie')) {
            $payload['serie'] = $datos['serie'];
        } elseif (trim($datos['serie']) !== '') {
            $payload['serie'] = $datos['serie'];
        }

        $colMes = self::columnaExistente($tabla, ['mesApro', 'mesAprobacion'], 'mesApro');
        $payload[$colMes] = $datos['mesApro'];

        $colAno = self::columnaExistente($tabla, ['anoApro', 'anoAprobacion'], 'anoApro');
        $payload[$colAno] = $datos['anoApro'];

        $payload['diaEmision'] = $datos['diaEmision'];
        $payload['mesEmision'] = $datos['mesEmision'];
        $payload['anoEmision'] = $datos['anoEmision'];

        if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'ppi')) {
            $payload['ppi'] = $datos['ppi'];
        } elseif (trim($datos['ppi']) !== '') {
            $payload['ppi'] = $datos['ppi'];
        }

        return $payload;
    }

    /**
     * @param  list<string>  $candidatas
     */
    private static function columnaExistente(string $tabla, array $candidatas, string $fallback): string
    {
        if (! Schema::hasTable($tabla)) {
            return $fallback;
        }

        foreach ($candidatas as $columna) {
            if (Schema::hasColumn($tabla, $columna)) {
                return $columna;
            }
        }

        return $fallback;
    }

    /**
     * @return Builder<Matricula>
     */
    private static function queryRegularesCurso(int $cursoId, int $idNivel, int $idTerlec): Builder
    {
        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES,
        );

        return Matricula::query()
            ->where('idCursos', $cursoId)
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->whereIn('idCondiciones', $idsCondiciones)
            ->whereNull('fechaBaja');
    }
}
