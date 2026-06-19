@php
    $bandeja = $bandeja ?? [];
    $ctx = studentCtx();
    $urgenteRecibidos = (int) ($bandeja['mensajes_no_leidos'] ?? 0) > 0;
    $urgenteEnviados = (int) ($bandeja['destinatarios_sin_leer'] ?? 0) > 0;
@endphp

<section class="se-dash-mail-panel" aria-labelledby="alumno-dash-mail-heading">
    <div class="flex flex-col gap-4 border-b border-[#C1D7DA]/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div class="min-w-0">
            <h2 id="alumno-dash-mail-heading" class="text-lg font-bold tracking-tight text-neutral-900">
                Cuaderno de comunicados
            </h2>
            <p class="mt-0.5 text-sm text-neutral-600">
                Ciclo {{ $ctx->terlecAno() }} · {{ (int) ($bandeja['hilos_total'] ?? 0) }} hilos en bandeja
            </p>
        </div>
        <a href="{{ route('alumnos.comunicaciones.index') }}"
           class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#40848D] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            Abrir bandeja
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-4">
        <a href="{{ route('alumnos.comunicaciones.index', ['filtro' => 'no_leidos']) }}"
           @class([
               'se-dash-mail-stat group',
               'se-dash-mail-stat--urgent' => $urgenteRecibidos,
           ])>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 group-hover:text-neutral-600">
                Mensajes no leídos
            </p>
            <p @class([
                'mt-2 text-3xl font-bold tabular-nums',
                'text-pink-700' => $urgenteRecibidos,
                'text-[#40848D]' => ! $urgenteRecibidos,
            ])>{{ (int) ($bandeja['mensajes_no_leidos'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-neutral-600">
                {{ (int) ($bandeja['hilos_con_no_leidos'] ?? 0) }} hilo{{ (int) ($bandeja['hilos_con_no_leidos'] ?? 0) === 1 ? '' : 's' }} con pendientes
            </p>
            @if ($urgenteRecibidos)
                <span class="se-dash-urgent-badge mt-3">Requiere atención</span>
            @endif
        </a>

        <a href="{{ route('alumnos.comunicaciones.index') }}"
           @class([
               'se-dash-mail-stat group',
               'se-dash-mail-stat--warn' => $urgenteEnviados,
           ])>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500 group-hover:text-neutral-600">
                Sin lectura del destinatario
            </p>
            <p @class([
                'mt-2 text-3xl font-bold tabular-nums',
                'text-amber-800' => $urgenteEnviados,
                'text-[#40848D]' => ! $urgenteEnviados,
            ])>{{ (int) ($bandeja['destinatarios_sin_leer'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-neutral-600">
                Destinatarios que aún no abrieron sus envíos
            </p>
            @if ($urgenteEnviados)
                <span class="se-dash-warn-badge mt-3">Seguimiento sugerido</span>
            @endif
        </a>

        <div class="se-dash-mail-stat">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Envíos pendientes</p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-neutral-800">
                {{ (int) ($bandeja['hilos_enviados_pendientes_lectura'] ?? 0) }}
            </p>
            <p class="mt-1 text-xs text-neutral-600">Hilos enviados con lecturas pendientes</p>
        </div>

        <div class="se-dash-mail-stat se-dash-mail-stat--calm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Total en bandeja</p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-neutral-800">{{ (int) ($bandeja['hilos_total'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-neutral-600">Recibidos y enviados del año lectivo</p>
        </div>
    </div>
</section>
