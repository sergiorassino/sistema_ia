<?php

namespace App\Support\InasistenciasDocentes;

use App\Mail\InformeInasistenciasDocenteMail;
use App\Models\Ento;
use App\Support\InasistenciasDocentes as InasDocentesModulo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Envío masivo de informes PDF por correo (progreso en caché para polling Livewire).
 */
final class EnvioMasivoInformes
{
    private const CACHE_TTL = 3600;

    public static function cacheKey(string $token): string
    {
        return 'inas_doc_envio:'.$token;
    }

    public static function paramsKey(string $token): string
    {
        return 'inas_doc_envio_params:'.$token;
    }

    /**
     * @param  array{bimestre: int, anio: int, soloPrueba: bool, idNivel: int}  $params
     */
    public static function guardarParams(string $token, array $params): void
    {
        Cache::put(self::paramsKey($token), $params, self::CACHE_TTL);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function obtenerProgreso(string $token): ?array
    {
        $data = Cache::get(self::cacheKey($token));

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function escribirProgreso(string $token, array $data): void
    {
        Cache::put(self::cacheKey($token), $data, self::CACHE_TTL);
    }

    public static function procesar(string $token): void
    {
        $params = Cache::get(self::paramsKey($token));
        if (! is_array($params)) {
            return;
        }

        $bimestre = (int) ($params['bimestre'] ?? 0);
        $anio = (int) ($params['anio'] ?? 0);
        $soloPrueba = ! empty($params['soloPrueba']);
        $idNivel = (int) ($params['idNivel'] ?? 0);

        if ($bimestre < 1 || $bimestre > 6 || $anio <= 0) {
            return;
        }

        @ini_set('max_execution_time', '0');

        $bimInfo = InasDocentesModulo::BIMESTRES[$bimestre];
        $nombreBimestre = $bimInfo['titulo'].' '.$anio;
        $institucion = Ento::query()->where('idNivel', $idNivel > 0 ? $idNivel : schoolCtx()->idNivel)->value('insti')
            ?? config('app.name', 'Institución');

        $columnas = ['id', 'apellido', 'nombre', 'dni'];
        if (Schema::hasColumn('profesores', 'email')) {
            $columnas[] = 'email';
        }

        $docentes = InasDocentesModulo::queryDocentesIndex()->get($columnas);

        $listaResultados = [];
        $enviados = 0;
        $sinEmail = 0;
        $errores = 0;
        $erroresDetalle = '';
        $n = 0;

        $conEmail = $docentes->filter(fn ($d) => trim((string) ($d->email ?? '')) !== '');
        $totalConEmail = $conEmail->count();

        $metaProgreso = [
            'bimestre' => $bimestre,
            'anio' => $anio,
            'soloPrueba' => $soloPrueba,
        ];

        self::escribirProgreso($token, array_merge($metaProgreso, [
            'total' => $totalConEmail,
            'current' => 0,
            'nombre' => '',
            'done' => false,
            'enviados' => 0,
            'sinEmail' => 0,
            'errores' => 0,
            'mensaje' => '',
            'lista' => [],
        ]));

        if ($totalConEmail === 0) {
            self::escribirProgreso($token, array_merge($metaProgreso, [
                'total' => 0,
                'current' => 0,
                'nombre' => '',
                'done' => true,
                'enviados' => 0,
                'sinEmail' => 0,
                'errores' => 0,
                'mensaje' => 'No hay docentes con email registrado.',
                'lista' => [],
            ]));

            return;
        }

        foreach ($docentes as $d) {
            $idProfesor = (int) $d->id;
            $email = trim((string) ($d->email ?? ''));
            $apenom = trim(($d->apellido ?? '').' '.($d->nombre ?? ''));

            if ($email === '') {
                $sinEmail++;
                $listaResultados[] = ['nombre' => $apenom, 'estado' => 'omitido', 'detalle' => ''];
                self::escribirProgreso($token, array_merge($metaProgreso, [
                    'total' => $totalConEmail,
                    'current' => $n,
                    'nombre' => $apenom,
                    'done' => false,
                    'enviados' => $enviados,
                    'sinEmail' => $sinEmail,
                    'errores' => $errores,
                    'mensaje' => '',
                    'lista' => $listaResultados,
                ]));

                continue;
            }

            $n++;
            self::escribirProgreso($token, array_merge($metaProgreso, [
                'total' => $totalConEmail,
                'current' => $n,
                'nombre' => $apenom,
                'done' => false,
                'enviados' => $enviados,
                'sinEmail' => $sinEmail,
                'errores' => $errores,
                'mensaje' => '',
                'lista' => $listaResultados,
            ]));

            try {
                $pdfBin = InformeInasistenciasDocenteTcpdf::render($idProfesor, $bimestre, $anio, (string) $institucion);
                $nombreArchivo = 'InformeInasistencias_'.Str::slug($nombreBimestre).'.pdf';

                if (! $soloPrueba) {
                    Mail::to($email, $apenom)->send(new InformeInasistenciasDocenteMail(
                        $apenom,
                        $nombreBimestre,
                        (string) $institucion,
                        $pdfBin,
                        $nombreArchivo,
                    ));
                }

                $enviados++;
                $listaResultados[] = [
                    'nombre' => $apenom,
                    'estado' => $soloPrueba ? 'generado' : 'enviado',
                    'detalle' => '',
                    'idProfesor' => $idProfesor,
                ];
            } catch (\Throwable $e) {
                $errores++;
                $erroresDetalle .= "\n• {$apenom}: ".$e->getMessage();
                $listaResultados[] = ['nombre' => $apenom, 'estado' => 'error', 'detalle' => $e->getMessage()];
            }

            self::escribirProgreso($token, array_merge($metaProgreso, [
                'total' => $totalConEmail,
                'current' => $n,
                'nombre' => $apenom,
                'done' => false,
                'enviados' => $enviados,
                'sinEmail' => $sinEmail,
                'errores' => $errores,
                'mensaje' => '',
                'lista' => $listaResultados,
            ]));
        }

        if ($soloPrueba) {
            $mensajeFinal = "Modo prueba: PDF generados para {$enviados} docente(s); no se enviaron correos. Podés revisarlos uno por uno abajo.";
        } else {
            $mensajeFinal = "Correos enviados: {$enviados}. Omitidos (sin email): {$sinEmail}. Fallos: {$errores}.";
            if ($erroresDetalle !== '') {
                $mensajeFinal .= ' Detalle de fallos:'.$erroresDetalle;
            }
        }

        self::escribirProgreso($token, array_merge($metaProgreso, [
            'total' => $totalConEmail,
            'current' => $n,
            'nombre' => '',
            'done' => true,
            'enviados' => $enviados,
            'sinEmail' => $sinEmail,
            'errores' => $errores,
            'mensaje' => $mensajeFinal,
            'lista' => $listaResultados,
        ]));
    }
}
