<?php

namespace App\Support\CertificacionServicios;

use App\Models\Certificacion;
use App\Models\Ento;
use App\Models\LicenciaDocente;
use App\Models\Nivel;
use App\Models\Profesor;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Pdf\PdfPost;
use App\Support\SchoolAlcancePedagogico;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class CertificacionServicios
{
    public const PERMISO_ORDEN = \App\Support\PermisosIaCatalog::CERTIFICACION_SERVICIOS;

    public static function tablasDisponibles(): bool
    {
        return Schema::hasTable('certificacion') && Schema::hasTable('licencias');
    }

    public static function mensajeTablasFaltantes(): string
    {
        $faltan = [];
        if (! Schema::hasTable('certificacion')) {
            $faltan[] = 'certificacion';
        }
        if (! Schema::hasTable('licencias')) {
            $faltan[] = 'licencias';
        }

        return 'Este colegio no tiene las tablas necesarias: '.implode(', ', $faltan).'.';
    }

    public static function scopedProfesorOrFail(int $id): Profesor
    {
        return Profesor::query()
            ->delNivel(SchoolAlcancePedagogico::idNivelLegajosDocente())
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @return LengthAwarePaginator<int, Profesor>
     */
    public static function paginarProfesores(string $buscar, int $porPagina = 50): LengthAwarePaginator
    {
        $q = Profesor::query()
            ->delNivel(SchoolAlcancePedagogico::idNivelLegajosDocente())
            ->with('tipo')
            ->orderBy('apellido')
            ->orderBy('nombre');

        $termino = trim($buscar);
        if ($termino !== '') {
            $q->buscar($termino);
        }

        return $q->paginate(max(10, min(100, $porPagina)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarServicios(int $idPersonal): array
    {
        return Certificacion::query()
            ->where('idpersonal', $idPersonal)
            ->orderByRaw('CASE WHEN fechaAlta IS NULL OR fechaAlta = \'0000-00-00\' THEN 1 ELSE 0 END')
            ->orderBy('fechaAlta')
            ->orderBy('FechaBaja')
            ->orderBy('idcertificacion')
            ->get()
            ->map(static function (Certificacion $r): array {
                return [
                    'id' => (int) $r->idcertificacion,
                    'cargo' => (string) ($r->cargo ?? ''),
                    'titularSuplente' => (string) ($r->titularSuplente ?? ''),
                    'nroResolucion' => (string) ($r->nroResolucion ?? ''),
                    'fechaAlta' => self::fechaParaInput($r->fechaAlta),
                    // Columna legacy `FechaBaja` (F mayúscula); no existe `fechaBaja` en atributos Eloquent.
                    'fechaBaja' => self::fechaParaInput($r->getAttribute('FechaBaja')),
                    'hsCatedra' => (string) ($r->hsCatedra ?? ''),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarLicencias(int $idPersonal): array
    {
        return LicenciaDocente::query()
            ->where('idPersonal', $idPersonal)
            ->orderByRaw('CASE WHEN fechaInicio IS NULL OR fechaInicio = \'0000-00-00\' THEN 1 ELSE 0 END')
            ->orderBy('fechaInicio')
            ->orderBy('fechaFin')
            ->orderBy('idlicencias')
            ->get()
            ->map(static function (LicenciaDocente $r): array {
                // Legacy ScriptCase: null se comporta como No (0). En UI siempre '0'/'1' (string) para el <select>.
                $parcial = AntiguedadServiciosCalculator::esParcial($r->parcial) ? '1' : '0';

                return [
                    'id' => (int) $r->idlicencias,
                    'fechaInicio' => self::fechaParaInput($r->fechaInicio),
                    'fechaFin' => self::fechaParaInput($r->fechaFin),
                    'parcial' => $parcial,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{ok: bool, id?: int, error?: string}
     */
    public static function guardarServicio(int $idPersonal, ?int $id, array $datos): array
    {
        self::scopedProfesorOrFail($idPersonal);

        $payload = [
            'idpersonal' => $idPersonal,
            'cargo' => trim((string) ($datos['cargo'] ?? '')),
            'titularSuplente' => trim((string) ($datos['titularSuplente'] ?? '')),
            'nroResolucion' => trim((string) ($datos['nroResolucion'] ?? '')),
            'fechaAlta' => self::fechaParaDb($datos['fechaAlta'] ?? null, true),
            'FechaBaja' => self::fechaBajaParaDb($datos['fechaBaja'] ?? null),
            'hsCatedra' => self::hsCatedraParaDb($datos['hsCatedra'] ?? null),
        ];

        $preparado = PersistenciaColumnas::prepararPayload('certificacion', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            return [
                'ok' => false,
                'error' => PersistenciaColumnas::mensajeColumnasInexistentes('certificacion', $preparado['columnas_con_valor_sin_columna']),
            ];
        }

        try {
            if ($id !== null && $id > 0) {
                $row = Certificacion::query()
                    ->where('idpersonal', $idPersonal)
                    ->whereKey($id)
                    ->firstOrFail();
                $row->fill($preparado['payload']);
                $row->save();
                $savedId = (int) $row->idcertificacion;
            } else {
                $row = Certificacion::query()->create($preparado['payload']);
                $savedId = (int) $row->idcertificacion;
            }
        } catch (QueryException $e) {
            return ['ok' => false, 'error' => PersistenciaColumnas::mensajeDesdeQueryException($e) ?? 'Error al guardar el servicio.'];
        }

        $check = PersistenciaColumnas::columnasNoPersistidas(
            'certificacion',
            ['idcertificacion' => $savedId],
            [
                'cargo' => $payload['cargo'],
                'titularSuplente' => $payload['titularSuplente'],
                'nroResolucion' => $payload['nroResolucion'],
                'fechaAlta' => $payload['fechaAlta'],
                'hsCatedra' => $payload['hsCatedra'],
            ]
        );
        if ($check !== []) {
            return [
                'ok' => false,
                'error' => 'No se pudo verificar el guardado de: '.implode(', ', $check).'.',
            ];
        }

        return ['ok' => true, 'id' => $savedId];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{ok: bool, id?: int, error?: string}
     */
    public static function guardarLicencia(int $idPersonal, ?int $id, array $datos): array
    {
        self::scopedProfesorOrFail($idPersonal);

        $payload = [
            'idPersonal' => $idPersonal,
            'fechaInicio' => self::fechaParaDb($datos['fechaInicio'] ?? null, true),
            'fechaFin' => self::fechaParaDb($datos['fechaFin'] ?? null, true),
            'parcial' => self::parcialParaDb($datos['parcial'] ?? null),
        ];

        $preparado = PersistenciaColumnas::prepararPayload('licencias', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            return [
                'ok' => false,
                'error' => PersistenciaColumnas::mensajeColumnasInexistentes('licencias', $preparado['columnas_con_valor_sin_columna']),
            ];
        }

        try {
            if ($id !== null && $id > 0) {
                $row = LicenciaDocente::query()
                    ->where('idPersonal', $idPersonal)
                    ->whereKey($id)
                    ->firstOrFail();
                $row->fill($preparado['payload']);
                $row->save();
                $savedId = (int) $row->idlicencias;
            } else {
                $row = LicenciaDocente::query()->create($preparado['payload']);
                $savedId = (int) $row->idlicencias;
            }
        } catch (QueryException $e) {
            return ['ok' => false, 'error' => PersistenciaColumnas::mensajeDesdeQueryException($e) ?? 'Error al guardar el servicio.'];
        }

        return ['ok' => true, 'id' => $savedId];
    }

    public static function eliminarServicio(int $idPersonal, int $id): void
    {
        self::scopedProfesorOrFail($idPersonal);
        Certificacion::query()
            ->where('idpersonal', $idPersonal)
            ->whereKey($id)
            ->delete();
    }

    public static function eliminarLicencia(int $idPersonal, int $id): void
    {
        self::scopedProfesorOrFail($idPersonal);
        LicenciaDocente::query()
            ->where('idPersonal', $idPersonal)
            ->whereKey($id)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public static function armarDatosPdf(int $idPersonal, string $fechaEmision, string $paraPresentar): array
    {
        $profesor = self::scopedProfesorOrFail($idPersonal);
        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);

        $ento = Ento::query()->where('idNivel', $idNivel)->first();
        $nivelNombre = '';
        if ($idNivel > 0) {
            $nivel = Nivel::query()->whereKey($idNivel)->first();
            $nivelNombre = trim((string) ($nivel->nivel ?? $ctx->nivelNombre() ?? ''));
        }

        $insti = trim((string) ($ento->insti ?? ''));
        $direccion = trim((string) ($ento->direccion ?? ''));
        $localidad = trim((string) ($ento->localidad ?? ''));
        $ubicacion = trim($direccion.($localidad !== '' ? ' - '.$localidad : ''));
        $replegal = trim((string) ($ento->replegal ?? ''));

        $ref = Carbon::createFromFormat('Y-m-d', $fechaEmision)->startOfDay();
        $servicios = self::listarServicios($idPersonal);
        $licencias = self::listarLicencias($idPersonal);

        $calc = AntiguedadServiciosCalculator::calcular(
            array_map(static fn (array $s): array => [
                'fechaAlta' => $s['fechaAlta'],
                'fechaBaja' => $s['fechaBaja'] !== '' ? $s['fechaBaja'] : null,
            ], $servicios),
            array_map(static fn (array $l): array => [
                'fechaInicio' => $l['fechaInicio'],
                'fechaFin' => $l['fechaFin'],
                'parcial' => $l['parcial'],
            ], $licencias),
            $ref
        );

        if (! $calc['antiguedad']['ok']) {
            throw ValidationException::withMessages([
                'fechaEmision' => 'El tiempo de licencias supera el subtotal de servicios. Revise los períodos.',
            ]);
        }

        $filasServicios = [];
        foreach ($servicios as $i => $s) {
            $dur = $calc['filasServicios'][$i] ?? ['anios' => 0, 'meses' => 0, 'dias' => 0];
            $filasServicios[] = [
                'cargo' => $s['cargo'],
                'hsCatedra' => $s['hsCatedra'],
                'titularSuplente' => $s['titularSuplente'],
                'nroResolucion' => $s['nroResolucion'],
                'fechaAlta' => self::fechaParaMostrar($s['fechaAlta']),
                'fechaBaja' => $s['fechaBaja'] === '' ? 'Continúa' : self::fechaParaMostrar($s['fechaBaja']),
                'anios' => $dur['anios'],
                'meses' => $dur['meses'],
                'dias' => $dur['dias'],
            ];
        }

        $filasLicencias = [];
        foreach ($licencias as $i => $l) {
            $filasLicencias[] = [
                'fechaInicio' => self::fechaParaMostrar($l['fechaInicio']),
                'fechaFin' => self::fechaParaMostrar($l['fechaFin']),
                'parcial' => AntiguedadServiciosCalculator::esParcial($l['parcial'] ?? 0) ? 'Si' : 'No',
                'anios' => $calc['filasLicencias'][$i]['anios'] ?? 0,
                'meses' => $calc['filasLicencias'][$i]['meses'] ?? 0,
                'dias' => $calc['filasLicencias'][$i]['dias'] ?? 0,
            ];
        }

        $nombre = trim($profesor->apellido.' '.$profesor->nombre);
        $dni = trim((string) ($profesor->dni ?? ''));

        return [
            'insti' => $insti,
            'nivelNombre' => $nivelNombre,
            'ubicacion' => $ubicacion,
            'profesorNombre' => $nombre,
            'profesorDni' => $dni,
            'servicios' => $filasServicios,
            'subtotal' => $calc['subtotal'],
            'licencias' => $filasLicencias,
            'antiguedad' => $calc['antiguedad'],
            'paraPresentar' => trim($paraPresentar),
            'fechaEmision' => $ref,
            'replegal' => $replegal,
        ];
    }

    /**
     * @return array{action: string, fields: array<string, mixed>}
     */
    public static function pdfPost(int $idPersonal, string $fechaEmision, string $paraPresentar): array
    {
        return PdfPost::datos(route('docentes.certificacion-servicios.pdf'), [
            'idPersonal' => $idPersonal,
            'fechaEmision' => $fechaEmision,
            'paraPresentar' => $paraPresentar,
        ]);
    }

    public static function nombreArchivoPdf(string $apellido, string $nombre): string
    {
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($apellido.'_'.$nombre)) ?: 'docente';

        return 'Certificacion_Servicios_'.$base.'.pdf';
    }

    public static function fechaParaInput(mixed $fecha): string
    {
        if (AntiguedadServiciosCalculator::fechaVacia($fecha)) {
            return '';
        }
        try {
            if ($fecha instanceof \DateTimeInterface) {
                return Carbon::instance(\DateTimeImmutable::createFromInterface($fecha))->format('Y-m-d');
            }
            $s = trim((string) $fecha);
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s) === 1) {
                return Carbon::createFromFormat('d/m/Y', $s)->format('Y-m-d');
            }

            return Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    public static function fechaParaMostrar(mixed $fecha): string
    {
        if (AntiguedadServiciosCalculator::fechaVacia($fecha)) {
            return '';
        }
        try {
            return Carbon::parse((string) $fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    public static function fechaParaDb(mixed $fecha, bool $nullable = false): ?string
    {
        if (AntiguedadServiciosCalculator::fechaVacia($fecha)) {
            return $nullable ? null : '';
        }
        try {
            return Carbon::parse((string) $fecha)->format('Y-m-d');
        } catch (\Throwable) {
            return $nullable ? null : '';
        }
    }

    /** Baja vacía → `0000-00-00` (convención legacy ScriptCase = “Continúa”). */
    public static function fechaBajaParaDb(mixed $fecha): string
    {
        if (AntiguedadServiciosCalculator::fechaVacia($fecha)) {
            return '0000-00-00';
        }
        try {
            return Carbon::parse((string) $fecha)->format('Y-m-d');
        } catch (\Throwable) {
            return '0000-00-00';
        }
    }

    public static function hsCatedraParaDb(mixed $valor): ?int
    {
        $s = trim((string) ($valor ?? ''));
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (int) $s;
    }

    public static function parcialParaDb(mixed $parcial): int
    {
        // Vacío / null → No (0), como ScriptCase (null == 0).
        if ($parcial === null || $parcial === '') {
            return 0;
        }

        return AntiguedadServiciosCalculator::esParcial($parcial) ? 1 : 0;
    }
}
