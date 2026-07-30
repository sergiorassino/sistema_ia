<?php

namespace App\Support\Cuotas;

use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Models\CuotaGenerada;
use App\Models\CuotasMes;
use App\Models\Ento;
use App\Models\Familia;
use App\Models\Legajo;
use App\Support\Cooperadora\ResponsablesLegajoCooperadora;
use App\Support\Database\PersistenciaColumnas;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Utilidades compartidas entre facturación AFIP en pago y en devengamiento.
 */
final class FacturacionAfipComun
{
    /**
     * Importe a facturar por cuota en modo devengamiento.
     *
     * Neto (`cuotasgeneradas.importe`, ya con beca si aplica). Si la fórmula del
     * 1.er vencimiento es bonificación (`signo1v = '-'` con valor > 0),
     * neto − bonificación; si no, el neto.
     */
    public static function importeAFacturarDevengamiento(CuotaGenerada $registro): float
    {
        $neto = round((float) ($registro->importe ?? 0), 2);

        return self::aplicarBonificacionPrimerVencimiento($registro, $neto);
    }

    /**
     * Importe de la leyenda «Importe Original de la cuota» (PDF AFIP con beca).
     *
     * Parte del neto sin beca (importe de catálogo / pre-beca) y, si la cuota
     * tiene bonificación en el 1.er vencimiento, resta esa bonificación.
     */
    public static function importeOriginalLeyendaBeca(CuotaGenerada $registro, float $porcentajeBeca): float
    {
        $importeConBeca = round((float) ($registro->importe ?? 0), 2);
        if ($importeConBeca <= 0) {
            return 0.0;
        }

        $porcentajeBeca = max(0.0, min(100.0, $porcentajeBeca));
        $factor = 1.0 - ($porcentajeBeca / 100.0);
        $netoSinBeca = $factor > 0.0
            ? round($importeConBeca / $factor, 2)
            : $importeConBeca;

        return self::aplicarBonificacionPrimerVencimiento($registro, $netoSinBeca);
    }

    /**
     * Si el 1.er vencimiento tiene bonificación, $base − bonificación; si no, $base.
     */
    public static function aplicarBonificacionPrimerVencimiento(CuotaGenerada $registro, float $base): float
    {
        $base = round(max(0, $base), 2);
        if ($base <= 0) {
            return 0.0;
        }

        $fechaRef = $registro->venc1 instanceof CarbonInterface
            ? $registro->venc1->copy()->startOfDay()
            : Carbon::today();

        $calc = ImputacionPagoCalculo::calcular($registro, $base, $fechaRef);
        if (! ($calc['esBonificacion'] ?? false)) {
            return $base;
        }

        $bonificacion = round((float) ($calc['bonificacion'] ?? 0), 2);
        if ($bonificacion <= 0) {
            return $base;
        }

        return round(max(0, $base - $bonificacion), 2);
    }

    /**
     * @param  list<CuotaGenerada>  $registros
     * @return array{0: string, 1: string}
     */
    public static function periodoServicioLote(array $registros): array
    {
        $desde = null;
        $hasta = null;

        foreach ($registros as $registro) {
            [$ini, $fin] = self::periodoServicio($registro);
            if ($desde === null || $ini < $desde) {
                $desde = $ini;
            }
            if ($hasta === null || $fin > $hasta) {
                $hasta = $fin;
            }
        }

        if ($desde === null || $hasta === null) {
            $hoy = Carbon::today()->format('Ymd');

            return [$hoy, $hoy];
        }

        return [$desde, $hasta];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function periodoServicio(CuotaGenerada $registro): array
    {
        $ano = (int) ($registro->terlec?->ano ?? schoolCtx()->terlecAno());
        if ($ano <= 0) {
            $ano = (int) Carbon::today()->year;
        }

        $mes = self::numeroMesDesdeRegistro($registro);
        if ($mes < 1 || $mes > 12) {
            $fecha = $registro->venc1 ?? Carbon::today();

            return [$fecha->copy()->startOfMonth()->format('Ymd'), $fecha->copy()->endOfMonth()->format('Ymd')];
        }

        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();

        return [$inicio->format('Ymd'), $fin->format('Ymd')];
    }

    public static function documentoNumerico(mixed $valor): int
    {
        $digits = preg_replace('/\D/', '', (string) $valor) ?? '';

        return (int) $digits;
    }

    public static function cursoTextoDesdeRegistro(CuotaGenerada $registro): string
    {
        if (! $registro->relationLoaded('curso')) {
            $registro->load([
                'curso:Id,cursec,c,s,idCurPlan,idTurnoClase',
                'curso.curplan:id,curPlanCurso',
                'curso.turnoClase:id,nombre',
            ]);
        }

        return mb_strtoupper(trim((string) ($registro->curso?->nombreParaListado() ?? '')));
    }

    /**
     * @return array{telefonoInstitucion: string, aporteEstatal: string}
     */
    public static function snapshotInstitucionalPdf(Ento $ento): array
    {
        return [
            'telefonoInstitucion' => trim((string) ($ento->telefono ?? '')),
            'aporteEstatal' => Schema::hasColumn('ento', 'aporteEstatal')
                ? trim((string) ($ento->aporteEstatal ?? ''))
                : '',
        ];
    }

    /**
     * Responsable económico impreso en la factura: campo `familias.responsable`.
     */
    public static function responsableEconomicoFamilia(Legajo $legajo): string
    {
        $idFamilia = (int) ($legajo->idFamilias ?? 0);
        if ($idFamilia <= 0) {
            return '';
        }

        if ($legajo->relationLoaded('familia')) {
            return trim((string) ($legajo->familia?->responsable ?? ''));
        }

        return trim((string) (Familia::query()->whereKey($idFamilia)->value('responsable') ?? ''));
    }

    /**
     * DNI del responsable económico (`familias.dniResp`): receptor AFIP y texto del PDF.
     */
    public static function dniRespDesdeFamilia(Legajo $legajo): string
    {
        if (! Schema::hasColumn('familias', 'dniResp')) {
            return '';
        }

        $idFamilia = (int) ($legajo->idFamilias ?? 0);
        if ($idFamilia <= 0) {
            return '';
        }

        if ($legajo->relationLoaded('familia')) {
            return trim((string) ($legajo->familia?->dniResp ?? ''));
        }

        return trim((string) (Familia::query()->whereKey($idFamilia)->value('dniResp') ?? ''));
    }

    /**
     * Documento del receptor ante AFIP (`DocNro`): `familias.dniResp`.
     */
    public static function docNroReceptorDesdeLegajo(Legajo $legajo): int
    {
        return self::documentoNumerico(self::dniRespDesdeFamilia($legajo));
    }

    /**
     * @return array{
     *     idFamilia: int,
     *     responsable: string,
     *     dniResp: string,
     *     valido: bool,
     *     motivo: string
     * }
     */
    public static function destinatarioFacturaDesdeLegajo(Legajo $legajo, bool $asegurarFamilia = false): array
    {
        if ($asegurarFamilia) {
            self::asegurarFamiliaDesdeVinculosLegajo($legajo);
        }

        $idFamilia = (int) ($legajo->idFamilias ?? 0);
        $responsable = self::responsableEconomicoFamilia($legajo);
        $dniResp = self::dniRespDesdeFamilia($legajo);
        $motivo = self::motivoDestinatarioInvalido($idFamilia, $responsable, $dniResp);

        return [
            'idFamilia' => $idFamilia,
            'responsable' => $responsable,
            'dniResp' => $dniResp,
            'valido' => $motivo === null,
            'motivo' => $motivo ?? '',
        ];
    }

    /**
     * Si el estudiante no tiene familia asignada, crea una con datos del primer vínculo cargado
     * (padre, madre o tutor) y la asigna al legajo.
     *
     * @return array{creada: bool, error: string, vinculo: string}
     */
    public static function asegurarFamiliaDesdeVinculosLegajo(Legajo $legajo): array
    {
        if (LegajoFamilia::tieneFamiliaAsignada($legajo)) {
            return ['creada' => false, 'error' => '', 'vinculo' => ''];
        }

        $vinculo = self::primerVinculoResponsableEconomico($legajo);
        if ($vinculo === null) {
            return [
                'creada' => false,
                'error' => 'El estudiante no tiene padre, madre ni tutor cargados en el legajo.',
                'vinculo' => '',
            ];
        }

        $responsable = trim((string) ($vinculo['nombre'] ?? ''));
        // familias.apellido: apellido y nombre del vínculo (padre/madre/tutor).
        $apellidoFamilia = self::apellidoFamiliaDesdeVinculo($vinculo);
        if ($apellidoFamilia === '') {
            $apellidoFamilia = trim((string) ($legajo->apellido ?? ''));
        }
        if ($apellidoFamilia === '') {
            return [
                'creada' => false,
                'error' => 'No se pudo determinar el apellido de la familia desde el legajo.',
                'vinculo' => '',
            ];
        }

        $dniResp = trim((string) ($vinculo['dni'] ?? ''));
        // Solo email* del vínculo; si no es un email válido, queda vacío (no se usa teléfono).
        $email = self::emailFehaciente($vinculo['email'] ?? '');
        $claveVinculo = (string) ($vinculo['vinculo'] ?? '');

        try {
            $familia = DB::transaction(function () use ($legajo, $apellidoFamilia, $responsable, $dniResp, $email): Familia {
                $payload = [
                    'apellido' => mb_substr($apellidoFamilia, 0, 50),
                    'responsable' => mb_substr($responsable, 0, 50),
                    'dniResp' => $dniResp !== '' ? $dniResp : null,
                    'email' => $email !== '' ? $email : '',
                ];

                $preparado = PersistenciaColumnas::prepararPayload('familias', $payload);
                if ($preparado['columnas_con_valor_sin_columna'] !== []) {
                    throw new \RuntimeException(
                        PersistenciaColumnas::mensajeColumnasInexistentes('familias', $preparado['columnas_con_valor_sin_columna'])
                    );
                }

                $familia = Familia::query()->create($preparado['payload']);

                Legajo::query()->whereKey($legajo->id)->update(['idFamilias' => $familia->id]);

                return $familia;
            });

            $legajo->idFamilias = (int) $familia->id;
            $legajo->setRelation('familia', $familia);

            return ['creada' => true, 'error' => '', 'vinculo' => $claveVinculo];
        } catch (Throwable) {
            return [
                'creada' => false,
                'error' => 'No se pudo crear la familia automáticamente. Intente nuevamente o asigne una familia desde el legajo.',
                'vinculo' => '',
            ];
        }
    }

    /**
     * Primer vínculo usable como responsable económico: nombre y DNI (padre → madre → tutor).
     * Si el padre tiene nombre pero no DNI, se continúa con madre/tutor.
     *
     * @return array{
     *     vinculo: string,
     *     apellido: string,
     *     nombrePila: string,
     *     nombre: string,
     *     dni: string,
     *     email: string,
     *     tieneDatos: bool
     * }|null
     */
    public static function primerVinculoResponsableEconomico(Legajo $legajo): ?array
    {
        $vinculos = self::vinculosResponsableEconomico($legajo);
        foreach (['padre', 'madre', 'tutor'] as $clave) {
            $fila = $vinculos[$clave] ?? null;
            if ($fila === null) {
                continue;
            }
            $nombre = trim((string) ($fila['nombre'] ?? ''));
            $dni = trim((string) ($fila['dni'] ?? ''));
            $dniLen = strlen($dni);
            if ($nombre !== '' && $dniLen >= 7 && $dniLen <= 11) {
                return array_merge($fila, ['vinculo' => $clave]);
            }
        }

        return null;
    }

    /**
     * Texto para `familias.apellido` a partir de un vínculo (apellido y nombre).
     *
     * @param  array{apellido?: string, nombrePila?: string, nombre?: string}  $vinculo
     */
    public static function apellidoFamiliaDesdeVinculo(array $vinculo): string
    {
        $apellido = trim((string) ($vinculo['apellido'] ?? ''));
        $nombrePila = trim((string) ($vinculo['nombrePila'] ?? ''));
        if ($apellido !== '' && $nombrePila !== '') {
            return trim($apellido.', '.$nombrePila);
        }

        $nombreCompleto = trim((string) ($vinculo['nombre'] ?? ''));
        if ($nombreCompleto !== '') {
            return $nombreCompleto;
        }

        return $apellido !== '' ? $apellido : $nombrePila;
    }

    public static function motivoDestinatarioInvalido(int $idFamilia, string $responsable, string $dniResp): ?string
    {
        if ($idFamilia <= 0 || $idFamilia === LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR) {
            return 'El estudiante no tiene familia asignada.';
        }

        if (trim($responsable) === '') {
            return 'Falta el responsable económico de la familia.';
        }

        if (self::documentoNumerico($dniResp) <= 0) {
            return 'Falta o es inválido el DNI del responsable económico.';
        }

        return null;
    }

    /**
     * Opciones de padre, madre y tutor para asignar responsable económico de la familia.
     *
     * @return array{
     *     padre: array{apellido: string, nombrePila: string, nombre: string, dni: string, email: string, tieneDatos: bool},
     *     madre: array{apellido: string, nombrePila: string, nombre: string, dni: string, email: string, tieneDatos: bool},
     *     tutor: array{apellido: string, nombrePila: string, nombre: string, dni: string, email: string, tieneDatos: bool}
     * }
     */
    public static function vinculosResponsableEconomico(Legajo $legajo): array
    {
        return [
            'padre' => self::filaVinculoResponsableEconomico(
                (string) ($legajo->nombrepad ?? ''),
                (string) ($legajo->dnipad ?? ''),
                (string) ($legajo->emailpad ?? ''),
            ),
            'madre' => self::filaVinculoResponsableEconomico(
                (string) ($legajo->nombremad ?? ''),
                (string) ($legajo->dnimad ?? ''),
                (string) ($legajo->emailmad ?? ''),
            ),
            'tutor' => self::filaVinculoResponsableEconomico(
                (string) ($legajo->nombretut ?? ''),
                (string) ($legajo->dnitut ?? ''),
                (string) ($legajo->emailtut ?? ''),
            ),
        ];
    }

    /**
     * @return array{apellido: string, nombrePila: string, nombre: string, dni: string, email: string, tieneDatos: bool}
     */
    private static function filaVinculoResponsableEconomico(string $nombreCompleto, string $dni, string $email = ''): array
    {
        $nombre = trim($nombreCompleto);
        $partes = ResponsablesLegajoCooperadora::separarNombre($nombre);
        $dniLimpio = preg_replace('/\D/', '', $dni) ?? '';

        return [
            'apellido' => trim((string) ($partes['apellido'] ?? '')),
            'nombrePila' => trim((string) ($partes['nombre'] ?? '')),
            'nombre' => $nombre,
            'dni' => $dniLimpio,
            'email' => self::emailFehaciente($email),
            'tieneDatos' => $nombre !== '' || $dniLimpio !== '',
        ];
    }

    /**
     * Solo acepta un email con formato válido. No usa teléfonos ni otros campos del legajo.
     */
    public static function emailFehaciente(mixed $valor): string
    {
        $email = mb_strtolower(trim((string) ($valor ?? '')), 'UTF-8');
        if ($email === '' || ! str_contains($email, '@')) {
            return '';
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function responsablePago(Legajo $legajo): array
    {
        $nombre = trim((string) ($legajo->respAdmiNom ?? ''));
        $dni = self::documentoNumerico($legajo->respAdmiDni ?? null);

        if ($nombre === '' || $dni <= 0) {
            $nombre = trim((string) ($legajo->nombrepad ?? ''));
            $dni = self::documentoNumerico($legajo->dnipad ?? null);
        }
        if ($nombre === '' || $dni <= 0) {
            $nombre = trim((string) ($legajo->nombremad ?? ''));
            $dni = self::documentoNumerico($legajo->dnimad ?? null);
        }

        return [$nombre, $dni > 0 ? (string) $dni : ''];
    }

    public static function formatearFechaBarra(string $yyyymmdd): string
    {
        $raw = preg_replace('/\D/', '', $yyyymmdd) ?? '';
        if (strlen($raw) !== 8) {
            return Carbon::today()->format('Y/m/d');
        }

        return substr($raw, 0, 4).'/'.substr($raw, 4, 2).'/'.substr($raw, 6, 2);
    }

    public static function formatearFechaEnto(mixed $valor): string
    {
        $raw = trim((string) ($valor ?? ''));
        if ($raw === '') {
            return '';
        }

        if (str_contains($raw, '/')) {
            return $raw;
        }

        try {
            return Carbon::parse($raw)->format('d/m/Y');
        } catch (Throwable) {
            return $raw;
        }
    }

    public static function guardarMensajeCuota(CuotaGenerada $registro, string $mensaje): void
    {
        if (! Schema::hasColumn('cuotasgeneradas', 'mensajeResultado')) {
            return;
        }

        try {
            $registro->mensajeResultado = mb_substr($mensaje, 0, 500);
            $registro->save();
        } catch (Throwable) {
            // No bloquear el flujo por un fallo al guardar el mensaje auxiliar.
        }
    }

    private static function numeroMesDesdeRegistro(CuotaGenerada $registro): int
    {
        $idMes = (int) ($registro->idCuotasmeses ?? 0);
        if ($idMes > 0) {
            $mesCatalogo = CuotasMes::query()->find($idMes, ['mes']);
            $mes = self::mesDesdeEtiqueta((string) ($mesCatalogo?->mes ?? ''));
            if ($mes > 0) {
                return $mes;
            }
        }

        return self::mesDesdeEtiqueta((string) ($registro->cuota?->nombre ?? ''));
    }

    private static function mesDesdeEtiqueta(string $texto): int
    {
        $mapa = [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];

        $n = mb_strtolower(trim($texto));

        return $mapa[$n] ?? 0;
    }
}
