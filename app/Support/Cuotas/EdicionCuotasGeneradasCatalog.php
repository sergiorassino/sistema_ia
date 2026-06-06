<?php

namespace App\Support\Cuotas;

use App\Models\CuotaGenerada;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vista previa, validación y aplicación masiva sobre cuotas generadas.
 */
final class EdicionCuotasGeneradasCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function filaVistaPrevia(CuotaGenerada $registro): array
    {
        $apellido = mb_strtoupper(trim((string) ($registro->legajo?->apellido ?? '')));
        $nombre = mb_strtoupper(trim((string) ($registro->legajo?->nombre ?? '')));
        $estudiante = trim($apellido.($apellido !== '' && $nombre !== '' ? ', ' : '').$nombre);
        $curso = $registro->curso;
        $nivelAbrev = trim((string) ($curso?->nivel?->abrev ?? ''));
        $cursoLabel = $curso !== null
            ? ($nivelAbrev !== '' ? $curso->nombreParaListado().' ('.$nivelAbrev.')' : $curso->nombreParaListado())
            : 'Curso #'.(int) $registro->idCursos;

        return [
            'id' => (int) $registro->id,
            'estudiante' => $estudiante,
            'cursoLabel' => $cursoLabel,
            'cuotaNombre' => trim((string) ($registro->cuota?->nombre ?? '')),
            'beca' => GestionAranceles::etiquetaBeca($registro) ?: 'C/E',
            'importe' => CuotasFormato::formatearImporte($registro->importe),
            'pagado' => CuotasFormato::formatearImporte($registro->pagado),
            'saldo' => CuotasFormato::formatearImporte($registro->faltapa),
            'venc1' => CuotasFormato::formatearFecha($registro->venc1),
            'venc2' => CuotasFormato::formatearFecha($registro->venc2),
            'venc3' => CuotasFormato::formatearFecha($registro->venc3),
            'nueVenc' => CuotasFormato::formatearFecha($registro->nueVenc),
            'puedeModificarImporte' => self::puedeModificarImporte($registro),
        ];
    }

    public static function puedeModificarImporte(CuotaGenerada $registro): bool
    {
        $pagado = round((float) ($registro->pagado ?? 0), 2);
        $faltapa = round((float) ($registro->faltapa ?? 0), 2);

        return $pagado <= 0 && $faltapa > 0;
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, string>
     */
    public static function reglasCambios(array $cambios): array
    {
        $reglas = [];

        if (self::cambioImporteActivo($cambios)) {
            $reglas['nuevoImporte'] = ['required', 'string'];
        }
        if (trim((string) ($cambios['venc1'] ?? '')) !== '') {
            $reglas['nuevoVenc1'] = ['required', 'date'];
        }
        if (trim((string) ($cambios['venc2'] ?? '')) !== '') {
            $reglas['nuevoVenc2'] = ['required', 'date'];
        }
        if (self::cambioNueVencActivo($cambios) && ! self::limpiarNueVenc($cambios)) {
            $reglas['nuevoNueVenc'] = ['required', 'date'];
        }

        return $reglas;
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    public static function validarCambios(array $cambios): void
    {
        if (! self::tieneCambiosParaAplicar($cambios)) {
            throw ValidationException::withMessages([
                'cambios' => 'Indique al menos un dato nuevo (importe, vencimiento o vencimiento actualizado).',
            ]);
        }

        if (self::cambioImporteActivo($cambios)) {
            $importe = CuotasFormato::parseImporte((string) ($cambios['importe'] ?? ''));
            if ($importe < 0) {
                throw ValidationException::withMessages([
                    'nuevoImporte' => 'El importe base debe ser mayor o igual a cero.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    public static function tieneCambiosParaAplicar(array $cambios): bool
    {
        return self::cambioImporteActivo($cambios)
            || trim((string) ($cambios['venc1'] ?? '')) !== ''
            || trim((string) ($cambios['venc2'] ?? '')) !== ''
            || self::cambioNueVencActivo($cambios);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    public static function cambioImporteActivo(array $cambios): bool
    {
        return trim((string) ($cambios['importe'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    public static function cambioNueVencActivo(array $cambios): bool
    {
        return self::limpiarNueVenc($cambios) || trim((string) ($cambios['nueVenc'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    public static function limpiarNueVenc(array $cambios): bool
    {
        return (bool) ($cambios['limpiarNueVenc'] ?? false);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $cambios
     * @return array{actualizados: int, importeActualizados: int, importeOmitidos: int, fallos: int}
     */
    public static function aplicarMasivo(array $ids, array $cambios): array
    {
        self::validarCambios($cambios);

        $modificaImporte = self::cambioImporteActivo($cambios);
        $importeBase = $modificaImporte
            ? round(CuotasFormato::parseImporte((string) $cambios['importe']), 2)
            : 0.0;

        $nuevoVenc1 = trim((string) ($cambios['venc1'] ?? ''));
        $nuevoVenc2 = trim((string) ($cambios['venc2'] ?? ''));
        $modificaNueVenc = self::cambioNueVencActivo($cambios);
        $limpiarNueVenc = self::limpiarNueVenc($cambios);
        $nuevoNueVenc = trim((string) ($cambios['nueVenc'] ?? ''));

        $actualizados = 0;
        $importeActualizados = 0;
        $importeOmitidos = 0;
        $fallos = 0;

        DB::transaction(function () use (
            $ids,
            $modificaImporte,
            $importeBase,
            $nuevoVenc1,
            $nuevoVenc2,
            $modificaNueVenc,
            $limpiarNueVenc,
            $nuevoNueVenc,
            &$actualizados,
            &$importeActualizados,
            &$importeOmitidos,
            &$fallos,
        ): void {
            foreach ($ids as $id) {
                $registro = EdicionCuotasGeneradasConsulta::registroEditable((int) $id);
                if ($registro === null) {
                    $fallos++;

                    continue;
                }

                $registro->loadMissing(['cuota:id,sinConBeca,idCuotastipo']);

                $huboCambio = false;

                if ($modificaImporte) {
                    if (self::aplicarImporteMasivo($registro, $importeBase)) {
                        $importeActualizados++;
                        $huboCambio = true;
                    } else {
                        $importeOmitidos++;
                    }
                }

                if ($nuevoVenc1 !== '') {
                    $registro->venc1 = $nuevoVenc1;
                    $huboCambio = true;
                }

                if ($nuevoVenc2 !== '') {
                    $registro->venc2 = $nuevoVenc2;
                    $registro->venc3 = $nuevoVenc2;
                    $huboCambio = true;
                }

                if ($modificaNueVenc) {
                    $registro->nueVenc = $limpiarNueVenc ? null : $nuevoNueVenc;
                    $huboCambio = true;
                }

                if ($huboCambio) {
                    $registro->save();
                    $actualizados++;
                }
            }
        });

        return [
            'actualizados' => $actualizados,
            'importeActualizados' => $importeActualizados,
            'importeOmitidos' => $importeOmitidos,
            'fallos' => $fallos,
        ];
    }

    /**
     * Aplica importe y faltapa según beca del registro. False si no cumple condiciones de pago.
     */
    private static function aplicarImporteMasivo(CuotaGenerada $registro, float $importeBase): bool
    {
        if (! self::puedeModificarImporte($registro)) {
            return false;
        }

        $importe = GeneracionCuotaEstudianteService::importeDesdeBaseYBecaEnRegistro($registro, $importeBase);
        if ($importe === null) {
            return false;
        }

        $registro->importe = $importe;
        $registro->faltapa = CuotasFormato::calcularFaltapa(
            $importe,
            (float) ($registro->pagado ?? 0),
            (float) ($registro->bonificacion ?? 0),
            (float) ($registro->interes ?? 0),
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    public static function textoConfirmacion(array $cambios, int $totalRegistros, int $elegiblesImporte): string
    {
        $lineas = [];

        if (self::cambioImporteActivo($cambios)) {
            $base = CuotasFormato::formatearImporte(CuotasFormato::parseImporte((string) $cambios['importe']));
            $lineas[] = "Importe base {$base} (con beca de cada alumno) en hasta {$elegiblesImporte} cuotas sin pago y con saldo.";
        }
        if (trim((string) ($cambios['venc1'] ?? '')) !== '') {
            $lineas[] = 'Vencimiento 1: '.self::formatearFechaConfirmacion((string) $cambios['venc1']).'.';
        }
        if (trim((string) ($cambios['venc2'] ?? '')) !== '') {
            $fecha = self::formatearFechaConfirmacion((string) $cambios['venc2']);
            $lineas[] = "Vencimiento 2 y 3: {$fecha}.";
        }
        if (self::limpiarNueVenc($cambios)) {
            $lineas[] = 'Quitar vencimiento actualizado.';
        } elseif (trim((string) ($cambios['nueVenc'] ?? '')) !== '') {
            $lineas[] = 'Vencimiento actualizado: '.self::formatearFechaConfirmacion((string) $cambios['nueVenc']).'.';
        }

        $detalle = implode(' ', $lineas);

        return "Se aplicarán cambios en {$totalRegistros} "
            .($totalRegistros === 1 ? 'cuota' : 'cuotas')
            .". {$detalle} ¿Continuar?";
    }

    /**
     * @param  list<array<string, mixed>>  $registrosVista
     */
    public static function contarElegiblesImporte(array $registrosVista): int
    {
        return count(array_filter(
            $registrosVista,
            fn (array $fila): bool => (bool) ($fila['puedeModificarImporte'] ?? false),
        ));
    }

    private static function formatearFechaConfirmacion(string $fecha): string
    {
        try {
            return \Carbon\Carbon::parse(trim($fecha))->format('d/m/Y');
        } catch (\Throwable) {
            return trim($fecha);
        }
    }
}
