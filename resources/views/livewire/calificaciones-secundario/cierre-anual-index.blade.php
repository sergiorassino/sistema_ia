{{-- Cierre anual (secundario): cierre masivo + listado de matrículas del ciclo activo. --}}
<div class="se-cierre-anual-fill">
    <div class="se-cierre-anual-grid se-cierre-anual-grid--listado gap-2">
    <section class="se-hero se-cierre-anual-hero min-w-0 shrink-0">
        <div class="se-hero-inner flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow !text-[10px]">Calificaciones · Secundario</p>
                <h2 class="font-bold tracking-tight">Cierre anual</h2>
                <p class="text-xs text-white/80 truncate">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo {{ schoolCtx()->terlecAno() }}
                    · Regulares y salidos (1–4)
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="inline-flex shrink-0 items-center justify-center gap-1 rounded-lg border border-white/25 bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Panel
            </a>
        </div>
    </section>

    @php($panelCierreExpandido = $confirmarDic || $confirmarFeb || ! empty($informeCierre))
    <div @class([
        'se-card se-cierre-anual-panel min-w-0 shrink-0 p-0',
        'se-cierre-anual-panel--expandido' => $panelCierreExpandido,
        'se-cierre-anual-panel--compacto' => ! $panelCierreExpandido,
    ])>
        @error('cierre')
            <div class="border-b border-red-200 bg-red-50 px-3 py-2 text-xs text-red-900 sm:px-4" role="alert">
                {{ $message }}
            </div>
        @enderror
        <div class="border-b border-accent-200 bg-accent-50 px-3 py-2.5 sm:px-4">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Cierre masivo del ciclo lectivo</p>
            @php($anoCierre = schoolCtx()->terlecAno() ?? '—')
            <p class="mt-0.5 max-w-4xl text-xs leading-snug text-neutral-600">
                Calificaciones del secundario con matrícula en el ciclo activo.
                <span class="font-semibold text-neutral-800">Verifique calificaciones completas.</span>
                <span class="tabular-nums text-neutral-800">Año a cerrar: {{ $anoCierre }}</span>
            </p>
            <div @class([
                'mt-2.5 flex flex-col gap-2',
                'lg:flex-row lg:flex-wrap lg:items-end lg:justify-between' => ! $panelCierreExpandido,
            ])>
            <div @class([
                'flex min-w-0 gap-2',
                'w-full flex-col' => $panelCierreExpandido,
                'min-w-0 flex-1 flex-wrap' => ! $panelCierreExpandido,
            ])>
                @if ($confirmarDic)
                    <div class="se-cierre-anual-confirmacion w-full rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="alertdialog" aria-labelledby="cierre-dic-titulo">
                        <p id="cierre-dic-titulo" class="font-semibold">Confirmar cierre de diciembre</p>
                        <p class="mt-3 flex flex-col gap-1 rounded-lg border border-amber-300 bg-amber-100/90 px-3 py-2.5 text-sm font-bold uppercase leading-snug tracking-wide text-amber-950 lg:flex-row lg:flex-wrap lg:items-baseline lg:gap-x-2">
                            <span>Verifique que está cerrando un año lectivo que ya tiene las calificaciones completas.</span>
                            <span class="whitespace-nowrap tabular-nums normal-case">Año lectivo que está por cerrar: {{ $anoCierre }}</span>
                        </p>
                        <p class="mt-2">Se pasarán al matriz las materias aprobadas (promedio anual ≥ 7 o coloquio de diciembre ≥ 7).</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" wire:click="ejecutarCierreDic" wire:loading.attr="disabled" class="btn-primary">
                                <span wire:loading.remove wire:target="ejecutarCierreDic">Confirmar</span>
                                <span wire:loading wire:target="ejecutarCierreDic">Procesando…</span>
                            </button>
                            <button type="button" wire:click="cancelarCierreDic" class="btn-secondary">Cancelar</button>
                        </div>
                    </div>
                @elseif (! $confirmarFeb)
                    <button type="button"
                            wire:click="solicitarCierreDic"
                            class="btn-secondary border-primary-200 text-primary-800 hover:border-primary-300">
                        Pasar materias APROBADAS al Matriz (Dic)
                    </button>
                @endif

                @if ($confirmarFeb)
                    <div class="se-cierre-anual-confirmacion w-full rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="alertdialog" aria-labelledby="cierre-feb-titulo">
                        <p id="cierre-feb-titulo" class="font-semibold">Confirmar cierre de febrero</p>
                        <p class="mt-3 flex flex-col gap-1 rounded-lg border border-amber-300 bg-amber-100/90 px-3 py-2.5 text-sm font-bold uppercase leading-snug tracking-wide text-amber-950 lg:flex-row lg:flex-wrap lg:items-baseline lg:gap-x-2">
                            <span>Verifique que está cerrando un año lectivo que ya tiene las calificaciones completas.</span>
                            <span class="whitespace-nowrap tabular-nums normal-case">Año lectivo que está por cerrar: {{ $anoCierre }}</span>
                        </p>
                        <p class="mt-2">
                            Aprobadas (promedio ≥ 7 o coloquio dic/feb ≥ 7) pasan al matriz; el resto queda como previa (<code class="text-xs">apro = 1</code>, <code class="text-xs">condAdeuda = PR</code>).
                            Si vuelve a ejecutar el cierre, una previa con notas aprobatorias pasa al matriz; las previas ya marcadas sin cambio no se duplican.
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" wire:click="ejecutarCierreFeb" wire:loading.attr="disabled" class="btn-primary">
                                <span wire:loading.remove wire:target="ejecutarCierreFeb">Confirmar</span>
                                <span wire:loading wire:target="ejecutarCierreFeb">Procesando…</span>
                            </button>
                            <button type="button" wire:click="cancelarCierreFeb" class="btn-secondary">Cancelar</button>
                        </div>
                    </div>
                @elseif (! $confirmarDic)
                    <button type="button"
                            wire:click="solicitarCierreFeb"
                            class="btn-secondary border-primary-200 text-primary-800 hover:border-primary-300">
                        Pasar APROBADAS al Matriz y REPROBADAS como Previas (Feb)
                    </button>
                @endif
            </div>
            <div @class([
                'se-cierre-anual-buscar flex w-full min-w-0 flex-col gap-1',
                'sm:max-w-md' => $panelCierreExpandido,
                'sm:w-auto sm:min-w-[14rem] sm:max-w-xs lg:max-w-sm' => ! $panelCierreExpandido,
            ])>
                <label for="se-cierre-anual-buscar" class="form-label !mb-0 text-[10px]">Buscar alumno</label>
                <div class="flex items-center gap-2">
                    <input id="se-cierre-anual-buscar"
                           type="search"
                           wire:model.live.debounce.350ms="buscar"
                           class="form-input min-w-0 flex-1 !py-1.5 text-sm"
                           placeholder="Apellido, nombre o DNI…"
                           autocomplete="off">
                    <p class="se-pill tabular-nums shrink-0 !py-1 text-[10px]">{{ count($alumnos) }}</p>
                </div>
            </div>
            </div>

            @if (! empty($informeCierre))
                <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/80 px-4 py-4" role="status" wire:key="informe-cierre-{{ $informeCierre['operacion'] }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 space-y-1">
                            <p class="text-sm font-semibold text-emerald-950">{{ $informeCierre['titulo'] }}</p>
                            <p class="text-xs text-emerald-900/80">
                                {{ $informeCierre['nivel'] }} · Ciclo lectivo {{ $informeCierre['ano_lectivo'] }}
                                · Alcance: todas las calificaciones con matrícula del ciclo activo
                            </p>
                        </div>
                        <button type="button"
                                wire:click="cerrarInformeCierre"
                                class="btn-secondary btn-sm shrink-0 border-emerald-200 text-emerald-900 hover:bg-white">
                            Cerrar informe
                        </button>
                    </div>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl border border-emerald-200/80 bg-white px-3 py-2.5">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Registros procesados</dt>
                            <dd class="mt-0.5 text-xl font-bold tabular-nums text-neutral-900">{{ $informeCierre['procesados'] }}</dd>
                        </div>
                        <div class="rounded-xl border border-emerald-200/80 bg-white px-3 py-2.5">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Registros actualizados</dt>
                            <dd class="mt-0.5 text-xl font-bold tabular-nums text-emerald-800">{{ $informeCierre['actualizados'] }}</dd>
                        </div>
                        <div class="rounded-xl border border-emerald-200/80 bg-white px-3 py-2.5">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Pasados al matriz</dt>
                            <dd class="mt-0.5 text-xl font-bold tabular-nums text-neutral-900">{{ $informeCierre['aprobados'] }}</dd>
                        </div>
                        @if (($informeCierre['operacion'] ?? '') === 'feb')
                            <div class="rounded-xl border border-emerald-200/80 bg-white px-3 py-2.5">
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Nuevas previas en esta ejecución</dt>
                                <dd class="mt-0.5 text-xl font-bold tabular-nums text-amber-800">{{ $informeCierre['previas'] }}</dd>
                            </div>
                        @endif
                        <div class="rounded-xl border border-emerald-200/80 bg-white px-3 py-2.5 {{ ($informeCierre['operacion'] ?? '') === 'feb' ? 'sm:col-span-2 lg:col-span-1' : '' }}">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Sin cambio</dt>
                            <dd class="mt-0.5 text-xl font-bold tabular-nums text-neutral-600">{{ $informeCierre['omitidos'] }}</dd>
                            <p class="mt-1 text-[10px] leading-snug text-neutral-500">
                                @if (($informeCierre['operacion'] ?? '') === 'feb')
                                    Sin cambio: ya al matriz, ya previa sin nuevas notas aprobatorias, o sin filas modificadas
                                @else
                                    No aprobados (Dic) o ya cerrados al matriz
                                @endif
                            </p>
                        </div>
                    </dl>
                </div>
            @endif
        </div>
    </div>

    <div class="se-card flex min-h-0 min-w-0 flex-col p-0">
        <div class="se-cierre-anual-grilla">
            <div class="se-cierre-anual-head-wrap"
                 data-se-cierre-head>
                <table class="se-cierre-anual-tabla w-full table-fixed">
                    <colgroup>
                        <col style="width:38%">
                        <col style="width:12%">
                        <col style="width:22%">
                        <col style="width:18%">
                        <col style="width:10rem">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col" class="!px-2.5 text-left">Apellido y nombre</th>
                            <th scope="col" class="!px-2.5 text-left">DNI</th>
                            <th scope="col" class="!px-2.5 text-left">Curso</th>
                            <th scope="col" class="!px-2.5 text-left">Condición</th>
                            <th scope="col" class="!px-2 text-right">Acción</th>
                        </tr>
                    </thead>
                </table>
            </div>
            <div class="se-cierre-anual-body-wrap"
                 tabindex="0"
                 data-se-cierre-body>
                <table class="se-cierre-anual-tabla w-full table-fixed divide-y divide-accent-100">
                    <colgroup>
                        <col style="width:38%">
                        <col style="width:12%">
                        <col style="width:22%">
                        <col style="width:18%">
                        <col style="width:10rem">
                    </colgroup>
                    <tbody class="bg-white">
                        @forelse ($alumnos as $a)
                            <tr class="hover:bg-accent-50/60" wire:key="cierre-anual-{{ $a['idLegajos'] }}">
                                <td class="!px-2.5 !py-1 text-sm font-medium leading-tight text-neutral-800">
                                    {{ $a['apellido'] }}, {{ $a['nombre'] }}
                                </td>
                                <td class="!px-2.5 !py-1 text-sm whitespace-nowrap tabular-nums leading-tight text-neutral-700">{{ $a['dni'] !== '' ? $a['dni'] : '—' }}</td>
                                <td class="!px-2.5 !py-1 text-sm leading-tight text-neutral-700">{{ $a['curso'] !== '' ? $a['curso'] : '—' }}</td>
                                <td class="!px-2.5 !py-1 text-sm leading-tight text-neutral-600">{{ $a['condicion'] !== '' ? $a['condicion'] : '—' }}</td>
                                <td class="!px-2 !py-1 text-right">
                                    <x-nav-contexto-estudiante
                                        destino="calificacionesSecundario.cierreAnual.historial"
                                        :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::CIERRE_ANUAL_SECUNDARIO"
                                        :id-legajos="$a['idLegajos']"
                                        class="inline">
                                        <span class="btn-secondary btn-sm !px-2 !py-1 text-[11px]">Historial</span>
                                    </x-nav-contexto-estudiante>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="!px-5 !py-10 text-center text-sm text-neutral-500">
                                    No hay matrículas para el ciclo lectivo actual con las condiciones indicadas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>
</div>
