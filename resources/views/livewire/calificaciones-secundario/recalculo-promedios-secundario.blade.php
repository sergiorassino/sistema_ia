{{-- Recálculo masivo de promedio anual (secundario estándar). --}}
<div class="mx-auto w-full max-w-5xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Secundario</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Recalcular promedios</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al panel
            </a>
        </div>
    </section>

    @error('recalculo')
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            {{ $message }}
        </div>
    @enderror

    <div class="se-card space-y-5 p-5 sm:p-6">
        <div class="space-y-2 text-sm text-neutral-600">
            <p>
                Calcula el <strong>promedio final</strong> (<code class="text-xs">calif</code>) con la misma regla que
                <strong>Carga de calificaciones</strong> (máximo de cada Eval/JIS y promedio de módulos con nota;
                si algún módulo con nota está desaprobado, el promedio queda vacío).
            </p>
            <p>
                Pensado para después de <strong>Descargar calificaciones desde CIDI</strong>: el archivo suele traer
                las notas de módulos pero no el promedio hasta el cierre anual de CIDI.
            </p>
            <ul class="list-disc space-y-1 pl-5">
                <li>Alcance: todas las materias de todos los alumnos del <strong>nivel y ciclo lectivo</strong> de la sesión.</li>
                <li>No modifica <code class="text-xs">ic01</code>–<code class="text-xs">ic28</code> ni coloquios.</li>
                <li>No pisa el promedio si ya hay coloquio de diciembre o febrero aprobado (≥ 7).</li>
            </ul>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <p class="se-pill tabular-nums">{{ number_format((int) $cantidadFilas, 0, ',', '.') }} filas</p>
            <p class="text-xs text-neutral-500">Registros de calificaciones del ciclo activo</p>
        </div>

        <div>
            <button type="button"
                    class="btn-primary"
                    wire:loading.attr="disabled"
                    wire:target="ejecutar"
                    x-on:click="window.seSwalConfirmar(
                        'Se recalculará el promedio final de todas las materias del ciclo {{ schoolCtx()->terlecAno() }}. Esta operación puede tardar unos minutos.',
                        'Recalcular promedios',
                        { confirmButtonText: 'Sí, recalcular', icon: 'warning' }
                    ).then(ok => ok && $wire.ejecutar())">
                <span wire:loading.remove wire:target="ejecutar">Recalcular promedios del ciclo</span>
                <span wire:loading wire:target="ejecutar">Procesando…</span>
            </button>
        </div>
    </div>

    @if (! empty($informe))
        <div class="se-card space-y-3 p-5 sm:p-6" role="status">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <p class="text-sm font-semibold text-neutral-800">Informe del recálculo</p>
                    <p class="text-xs text-neutral-500">
                        {{ $informe['nivel'] }} · Ciclo lectivo {{ $informe['ano_lectivo'] }}
                    </p>
                </div>
                <button type="button" wire:click="cerrarInforme" class="btn-secondary text-xs">Cerrar</button>
            </div>
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-accent-200 bg-accent-50/60 px-3 py-2">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Procesados</dt>
                    <dd class="text-lg font-semibold tabular-nums text-neutral-800">{{ $informe['procesados'] }}</dd>
                </div>
                <div class="rounded-xl border border-accent-200 bg-accent-50/60 px-3 py-2">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Actualizados</dt>
                    <dd class="text-lg font-semibold tabular-nums text-primary-700">{{ $informe['actualizados'] }}</dd>
                </div>
                <div class="rounded-xl border border-accent-200 bg-accent-50/60 px-3 py-2">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Sin cambio</dt>
                    <dd class="text-lg font-semibold tabular-nums text-neutral-800">{{ $informe['sin_cambio'] }}</dd>
                </div>
                <div class="rounded-xl border border-accent-200 bg-accent-50/60 px-3 py-2">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Omitidos (coloquio)</dt>
                    <dd class="text-lg font-semibold tabular-nums text-neutral-800">{{ $informe['omitidos_coloquio'] }}</dd>
                </div>
                <div class="rounded-xl border border-accent-200 bg-accent-50/60 px-3 py-2">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Errores</dt>
                    <dd class="text-lg font-semibold tabular-nums {{ $informe['errores'] > 0 ? 'text-red-700' : 'text-neutral-800' }}">{{ $informe['errores'] }}</dd>
                </div>
            </dl>
        </div>
    @endif

    @script
    <script>
        (function () {
            function mensajeDeEvento(event, fallback) {
                return event?.mensaje ?? event?.detail?.mensaje ?? fallback;
            }

            $wire.on('se-swal-exito', (event) => {
                const mensaje = mensajeDeEvento(event, 'Recálculo completado.');
                if (typeof window.seSwalExito === 'function') {
                    window.seSwalExito(mensaje);
                }
            });

            $wire.on('se-swal-error', (event) => {
                const mensaje = mensajeDeEvento(event, 'No se pudo completar el recálculo.');
                if (typeof window.seSwalError === 'function') {
                    window.seSwalError(mensaje);
                }
            });
        })();
    </script>
    @endscript
</div>
