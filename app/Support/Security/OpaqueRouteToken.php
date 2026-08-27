<?php

namespace App\Support\Security;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

/**
 * Referencias opacas para rutas GET (PDF, descargas) sin IDs numéricos en la URL.
 *
 * El valor en la ruta es cifrado con APP_KEY; no es enumerable ni adivinable.
 * Siempre revalidar alcance en el controlador (sesión, schoolCtx, etc.).
 */
final class OpaqueRouteToken
{
    public const PURPOSE_COMPROBANTE_PAGO = 'alumnos.comprobante-pago';

    public const PURPOSE_ALUMNOS_RESUMEN_PAGOS = 'alumnos.aranceles-escolares.resumen-pagos';

    public const PURPOSE_ALUMNOS_CUOTAS_ADEUDADAS = 'alumnos.aranceles-escolares.cuotas-adeudadas';

    public const PURPOSE_ALUMNOS_COMPROBANTE_AFIP_REG = 'alumnos.comprobante-afip-reg';

    public const PURPOSE_ADMIN_COMPROBANTE_PAGO = 'cuotas.comprobante-pago';

    public const PURPOSE_ADMIN_COMPROBANTE_PAGO_IMPUTACION = 'cuotas.comprobante-pago-imputacion';

    public const PURPOSE_ADMIN_COMPROBANTE_PAGO_IMPUTACION_MULTI = 'cuotas.comprobante-pago-imputacion-multi';

    public const PURPOSE_ADMIN_COMPROBANTE_AFIP = 'cuotas.comprobante-afip';

    public const PURPOSE_ADMIN_COMPROBANTE_AFIP_REG = 'cuotas.comprobante-afip-reg';

    public const PURPOSE_ADMIN_RESUMEN_PAGOS = 'cuotas.resumen-pagos';

    public const PURPOSE_ADMIN_CUOTAS_ADEUDADAS = 'cuotas.cuotas-adeudadas';

    public const PURPOSE_ADMIN_SOLICITUD_AYUDA_FAMILIAR = 'cuotas.solicitud-ayuda-familiar';

    public const PURPOSE_ADMIN_SIRO_DESCARGA_PLANILLA = 'cuotas.siro-descarga-planilla';

    public const PURPOSE_MORA_ESTADO_DEUDA = 'mora.estado-deuda-familiar';

    public const PURPOSE_MORA_LISTADO_DEUDA = 'mora.listado-deuda';

    public const PURPOSE_MORA_NOTIFICACION_DEUDA = 'mora.notificacion-deuda';

    public const PURPOSE_COMUNICACION_HILO = 'comunicaciones.hilo-pdf';

    public const PURPOSE_COOP_RECIBO = 'cooperadora.recibo';

    public const PURPOSE_COOP_ORDEN_PAGO = 'cooperadora.orden-pago';

    public const PURPOSE_COOP_PAGOS_ESTUDIANTE = 'cooperadora.pagos-estudiante';

    public const PURPOSE_DOC_ESTUDIANTE_AUTOGESTION = 'alumnos.doc-estudiante';

    public const PURPOSE_EMAILS_MASIVOS_ADJUNTO = 'emails-masivos.adjunto';

    public const PURPOSE_DOC_PP_ARCHIVO = 'doc-pp.archivo';

    public const PURPOSE_CAPACITACION_DOCENTE_CERT = 'capacitacion-docente.certificado';

    public const PURPOSE_LEGAJO_FOTO_CARNET = 'abm.legajos.foto-carnet';

    public const PURPOSE_MATERIAS_ADEUDADAS_POR_CURSO = 'examenes.materias-adeudadas-por-curso';

    public const PURPOSE_EXT_ACTIVIDAD = 'ext.actividad';

    public static function forComprobantePagoCuota(int $idCuotaGenerada, int $idLegajo): string
    {
        return self::encode(self::PURPOSE_COMPROBANTE_PAGO, $idCuotaGenerada, $idLegajo);
    }

    public static function forResumenPagosAutogestion(int $idLegajo): string
    {
        return self::encode(self::PURPOSE_ALUMNOS_RESUMEN_PAGOS, $idLegajo, $idLegajo);
    }

    public static function forCuotasAdeudadasAutogestion(int $idLegajo): string
    {
        return self::encode(self::PURPOSE_ALUMNOS_CUOTAS_ADEUDADAS, $idLegajo, $idLegajo);
    }

    public static function forEmailsMasivosAdjunto(int $idTerlec, int $idEmailEscrito, string $nombreArchivo): string
    {
        return self::encodePayload(self::PURPOSE_EMAILS_MASIVOS_ADJUNTO, [
            't' => $idTerlec,
            'e' => $idEmailEscrito,
            'n' => mb_substr(trim($nombreArchivo), 0, 30),
        ]);
    }

    public static function forComprobanteAfipAutogestion(int $idComprobanteAfip, int $idCuotaGenerada, int $idLegajo): string
    {
        return self::encodePayload(self::PURPOSE_ALUMNOS_COMPROBANTE_AFIP_REG, [
            'a' => $idComprobanteAfip,
            'c' => $idCuotaGenerada,
            'l' => $idLegajo,
        ]);
    }

    public static function forComprobantePagoCuotaAdministracion(int $idCuotaGenerada, int $idLegajo): string
    {
        return self::encode(self::PURPOSE_ADMIN_COMPROBANTE_PAGO, $idCuotaGenerada, $idLegajo);
    }

    public static function forComprobantePagoImputacionAdministracion(int $idCuotaPago, int $idLegajo): string
    {
        return self::encode(self::PURPOSE_ADMIN_COMPROBANTE_PAGO_IMPUTACION, $idCuotaPago, $idLegajo);
    }

    /**
     * @param  list<int>  $idsCuotasPagos
     */
    public static function forComprobantePagoImputacionMultipleAdministracion(array $idsCuotasPagos, int $idLegajo): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsCuotasPagos), fn (int $id) => $id > 0)));
        if ($ids === []) {
            throw new \InvalidArgumentException('Se requiere al menos un pago para el comprobante.');
        }

        return self::encodePayload(self::PURPOSE_ADMIN_COMPROBANTE_PAGO_IMPUTACION_MULTI, [
            'p' => $ids,
            'l' => $idLegajo,
        ]);
    }

    public static function forComprobanteAfipAdministracion(int $idCuotaPago, int $idLegajo): string
    {
        return self::encode(self::PURPOSE_ADMIN_COMPROBANTE_AFIP, $idCuotaPago, $idLegajo);
    }

    public static function forComprobanteAfipRegistro(int $idComprobanteAfip, int $idLegajo): string
    {
        return self::encode(self::PURPOSE_ADMIN_COMPROBANTE_AFIP_REG, $idComprobanteAfip, $idLegajo);
    }

    public static function forResumenPagosEstudiante(int $idLegajo): string
    {
        return self::encode(self::PURPOSE_ADMIN_RESUMEN_PAGOS, $idLegajo, $idLegajo);
    }

    public static function forCuotasAdeudadasEstudiante(int $idLegajo): string
    {
        return self::encode(self::PURPOSE_ADMIN_CUOTAS_ADEUDADAS, $idLegajo, $idLegajo);
    }

    public static function forSolicitudAyudaFamiliar(int $nroSolicitud, int $idLegajo): string
    {
        return self::encode(self::PURPOSE_ADMIN_SOLICITUD_AYUDA_FAMILIAR, $nroSolicitud, $idLegajo);
    }

    public static function forSiroDescargaPlanilla(int $nroPlanilla): string
    {
        return self::encodePayload(self::PURPOSE_ADMIN_SIRO_DESCARGA_PLANILLA, [
            'n' => $nroPlanilla,
        ]);
    }

    public static function forEstadoDeudaFamiliar(int $idFamilia): string
    {
        return self::encode(self::PURPOSE_MORA_ESTADO_DEUDA, $idFamilia, $idFamilia);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function forListadoMorosos(array $filtros): string
    {
        return self::encodePayload(self::PURPOSE_MORA_LISTADO_DEUDA, $filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function forNotificacionDeudaMorosos(array $filtros): string
    {
        return self::encodePayload(self::PURPOSE_MORA_NOTIFICACION_DEUDA, $filtros);
    }

    public static function forComunicacionHilo(int $idHilo, int $idProfesor): string
    {
        return self::encodePayload(self::PURPOSE_COMUNICACION_HILO, [
            'h' => $idHilo,
            'p' => $idProfesor,
        ]);
    }

    public static function forCoopRecibo(int $idIngreso): string
    {
        return self::encode(self::PURPOSE_COOP_RECIBO, $idIngreso, $idIngreso);
    }

    public static function forCoopOrdenPago(int $idEgreso): string
    {
        return self::encode(self::PURPOSE_COOP_ORDEN_PAGO, $idEgreso, $idEgreso);
    }

    public static function forCoopPagosEstudiante(int $idLegajo): string
    {
        return self::encode(self::PURPOSE_COOP_PAGOS_ESTUDIANTE, $idLegajo, $idLegajo);
    }

    public static function forDocumentoEstudianteAutogestion(int $idDocEstudianteTipo, int $idLegajo): string
    {
        return self::encode(self::PURPOSE_DOC_ESTUDIANTE_AUTOGESTION, $idDocEstudianteTipo, $idLegajo);
    }

    public static function forDocPpArchivo(int $idDocPp): string
    {
        return self::encodePayload(self::PURPOSE_DOC_PP_ARCHIVO, [
            'd' => $idDocPp,
        ]);
    }

    public static function forCapacitacionDocenteCertificado(int $idCapacitacion): string
    {
        return self::encodePayload(self::PURPOSE_CAPACITACION_DOCENTE_CERT, [
            'c' => $idCapacitacion,
        ]);
    }

    public static function forLegajoFotoCarnet(int $idLegajo): string
    {
        return self::encode(self::PURPOSE_LEGAJO_FOTO_CARNET, $idLegajo, $idLegajo);
    }

    public static function forExtActividad(int $idActividad): string
    {
        return self::encodePayload(self::PURPOSE_EXT_ACTIVIDAD, [
            'a' => $idActividad,
        ]);
    }

    public static function decodeExtActividad(string $ref): ?int
    {
        $data = self::decodePayload($ref, self::PURPOSE_EXT_ACTIVIDAD);
        $id = (int) ($data['a'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @param  list<int>  $idsCursos
     */
    public static function forMateriasAdeudadasPorCurso(array $idsCursos): string
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $idsCursos),
            static fn (int $id) => $id > 0,
        )));

        return self::encodePayload(self::PURPOSE_MATERIAS_ADEUDADAS_POR_CURSO, [
            'c' => $ids,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decodePayload(string $ref, string $purpose): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        try {
            $json = Crypt::decryptString(self::fromUrlSafe($ref));
            /** @var array{p?: string, d?: array<string, mixed>} $payload */
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (($payload['p'] ?? '') !== $purpose) {
            return null;
        }

        $data = $payload['d'] ?? null;

        return is_array($data) ? $data : null;
    }

    /**
     * @return array{id: int, legajo: int}|null
     */
    public static function decode(string $ref, string $purpose): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        try {
            $json = Crypt::decryptString(self::fromUrlSafe($ref));
            /** @var array{p?: string, i?: int, l?: int} $payload */
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (($payload['p'] ?? '') !== $purpose) {
            return null;
        }

        $id = (int) ($payload['i'] ?? 0);
        $legajo = (int) ($payload['l'] ?? 0);

        if ($id <= 0 || $legajo <= 0) {
            return null;
        }

        return ['id' => $id, 'legajo' => $legajo];
    }

    private static function encode(string $purpose, int $id, int $idLegajo): string
    {
        $payload = json_encode([
            'p' => $purpose,
            'i' => $id,
            'l' => $idLegajo,
        ], JSON_THROW_ON_ERROR);

        return self::toUrlSafe(Crypt::encryptString($payload));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function encodePayload(string $purpose, array $data): string
    {
        $payload = json_encode([
            'p' => $purpose,
            'd' => $data,
        ], JSON_THROW_ON_ERROR);

        return self::toUrlSafe(Crypt::encryptString($payload));
    }

    private static function toUrlSafe(string $encrypted): string
    {
        return rtrim(strtr($encrypted, '+/', '-_'), '=');
    }

    private static function fromUrlSafe(string $ref): string
    {
        $b64 = strtr($ref, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        return $b64;
    }
}
