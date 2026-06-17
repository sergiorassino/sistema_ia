<?php



namespace App\Support\Mora;



use App\Models\CuotaGenerada;

use App\Models\DatoVario;

use App\Support\Cuotas\CuotasFormato;

use App\Support\Cuotas\ImputacionPagoCalculo;

use Carbon\Carbon;

use Illuminate\Database\Eloquent\Builder;

use Illuminate\Support\Collection;



/**

 * Datos para el PDF «Notificación de deuda» (una página por familia).

 */

final class NotificacionDeudaDatos

{

    /**

     * @param  array<string, mixed>  $filtros  Normalizados con {@see GestionMorososFiltros::normalizarDesdeLivewire}

     * @return array<string, mixed>|null

     */

    public static function build(array $filtros): ?array

    {

        $fechaCalculo = Carbon::parse((string) ($filtros['fechaCalculo'] ?? now()->format('Y-m-d')))->startOfDay();



        $registros = self::consulta($filtros)

            ->with([

                'legajo:id,apellido,nombre,dni,idFamilias',

                'legajo.familia:id,apellido,responsable',

                'cuota:id,nombre,orden',

                'terlec:id,ano',

                'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',

                'curso.nivel:id,abrev',

                'beca:id,nombreBeca',

            ])

            ->get();



        if ($registros->isEmpty()) {

            return null;

        }



        ImputacionPagoCalculo::precargarFormulas($registros);



        try {



        $porGrupo = GestionMorososAgrupacion::porFamiliaOEstudiante($registros)

            ->sortBy(fn (Collection $items) => GestionMorososAgrupacion::claveOrden($items->first()));



        $paginas = [];

        $textos = DatoVario::singleton();



        foreach ($porGrupo as $clave => $items) {

            if (! GestionMorososAgrupacion::claveEsValida($clave)) {

                continue;

            }



            $items = $items->sortBy([

                fn (CuotaGenerada $r) => (int) ($r->cuota?->orden ?? 9999),

                fn (CuotaGenerada $r) => (int) $r->id,

            ])->values();



            $familiaLinea = GestionMorososAgrupacion::familiaLinea($items);

            $tituloFamilia = GestionMorososAgrupacion::tituloSeccion($items);



            $filas = [];

            $totImporte = 0.0;

            $totBonif = 0.0;

            $totInter = 0.0;

            $totPagado = 0.0;

            $totSaldo = 0.0;

            $totIntereses = 0.0;

            $totAPagar = 0.0;

            $tieneBeca = false;



            foreach ($items as $registro) {

                $idBeca = (int) ($registro->idCuotasbecas ?? 0);

                if ($idBeca > 1) {

                    $tieneBeca = true;

                }



                $saldo = round((float) ($registro->faltapa ?? 0), 2);

                $calc = ImputacionPagoCalculo::calcular($registro, $saldo, $fechaCalculo, null);



                $importe = round((float) ($registro->importe ?? 0), 2);

                $bonif = round((float) ($registro->bonificacion ?? 0), 2);

                $inter = round((float) ($registro->interes ?? 0), 2);

                $pagado = round((float) ($registro->pagado ?? 0), 2);

                $intereses = round((float) $calc['interes'], 2);

                $aPagar = round((float) $calc['aPagar'], 2);



                $totImporte += $importe;

                $totBonif += $bonif;

                $totInter += $inter;

                $totPagado += $pagado;

                $totSaldo += $saldo;

                $totIntereses += $intereses;

                $totAPagar += $aPagar;



                $legajo = $registro->legajo;

                $curso = $registro->curso;

                $becaEtiqueta = trim((string) ($registro->beca?->nombreBeca ?? ''));

                if ($becaEtiqueta === '') {

                    $becaEtiqueta = (int) ($registro->idCuotasbecas ?? 0) === 1 ? 'C/E' : '';

                }



                $filas[] = [

                    'estudiante' => mb_strtoupper(trim(

                        trim((string) ($legajo?->apellido ?? '')).' '.trim((string) ($legajo?->nombre ?? '')),

                    )),

                    'dni' => trim((string) ($legajo?->dni ?? '')),

                    'curso' => mb_strtoupper(trim((string) ($curso?->cursec ?? $curso?->nombreParaListado() ?? ''))),

                    'cuota' => mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? ''))),

                    'ano' => (string) ($registro->terlec?->ano ?? ''),

                    'beca' => mb_strtoupper($becaEtiqueta),

                    'venc1' => CuotasFormato::formatearFecha($registro->venc1),

                    'importe' => CuotasFormato::formatearImporte($importe),

                    'bonificacion' => CuotasFormato::formatearImporte($bonif),

                    'interes' => CuotasFormato::formatearImporte($inter),

                    'pagado' => CuotasFormato::formatearImporte($pagado),

                    'saldo' => CuotasFormato::formatearImporte($saldo),

                    'intereses' => CuotasFormato::formatearImporte($intereses),

                    'aPagar' => CuotasFormato::formatearImporte($aPagar),

                ];

            }



            $paginas[] = [

                'familiaLinea' => $familiaLinea !== '' ? $familiaLinea : '—',

                'tituloFamilia' => $tituloFamilia,

                'usarTextoFinalBec' => $tieneBeca,

                'filas' => $filas,

                'totales' => [

                    'importe' => CuotasFormato::formatearImporte($totImporte),

                    'bonificacion' => CuotasFormato::formatearImporte($totBonif),

                    'interes' => CuotasFormato::formatearImporte($totInter),

                    'pagado' => CuotasFormato::formatearImporte($totPagado),

                    'saldo' => CuotasFormato::formatearImporte($totSaldo),

                    'intereses' => CuotasFormato::formatearImporte($totIntereses),

                    'aPagar' => CuotasFormato::formatearImporte($totAPagar),

                ],

            ];

        }



        if ($paginas === []) {

            return null;

        }



        $header = schoolPdfHeaderData();



        return [

            'pdfHeader' => $header,

            'localidad' => trim((string) ($header['localidad'] ?? '')),

            'fechaCalculo' => $fechaCalculo->format('d/m/Y'),

            'fechaCarta' => Carbon::now()->format('d/m/Y'),

            'textoInicial' => trim((string) ($textos->textoInicNotDeuda ?? '')),

            'textoFinal' => trim((string) ($textos->textoFinalNotDeuda ?? '')),

            'textoFinalBec' => trim((string) ($textos->textoFinalNotDeudaBec ?? '')),

            'paginas' => $paginas,

        ];

        } finally {

            ImputacionPagoCalculo::limpiarCacheFormulas();

        }

    }



    /**

     * @param  array<string, mixed>  $filtros

     * @return Builder<CuotaGenerada>

     */

    private static function consulta(array $filtros): Builder

    {

        return GestionMorososConsulta::cuotasAdeudadas($filtros);

    }

}

