@if ($modalLecturaAbierto)
    <div class="fixed inset-0 z-[95] flex items-center justify-center overflow-hidden p-4 sm:p-6"
         role="dialog"
         aria-modal="true"
         aria-labelledby="com-lectura-titulo">
        <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarDetalleLectura"></div>

        {{-- max-height en style inline: no depende de npm run build / public/build en producción --}}
        <div class="relative z-10 flex w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5"
             style="max-height: min(90dvh, calc(100vh - 2rem));">
            <div class="shrink-0 border-b border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5 sm:py-4">
                <p id="com-lectura-titulo" class="text-sm font-bold text-neutral-900">
                    {{ $modalLecturaTitulo }}
                </p>
                @if ($modalLecturaResumen !== '')
                    <p class="mt-1 text-xs font-semibold text-primary-800">{{ $modalLecturaResumen }}</p>
                @endif
                <p class="mt-1 text-xs text-neutral-600">
                    {{ count($modalLecturaFilas) }} destinatario{{ count($modalLecturaFilas) === 1 ? '' : 's' }} · desplácese para ver todos
                </p>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-2 sm:px-5 sm:py-3"
                 style="overflow-y: auto; -webkit-overflow-scrolling: touch;">
                <ul class="divide-y divide-accent-100">
                    @foreach ($modalLecturaFilas as $fila)
                        <li class="flex flex-wrap items-start justify-between gap-2 py-2.5">
                            <div class="min-w-0 flex-1">
                                <p class="break-words text-sm font-medium text-neutral-900">{{ $fila['nombre'] }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                                    {{ $fila['tipo_etiqueta'] }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                @if ($fila['leido'])
                                    <span class="inline-flex rounded-lg border border-primary-200 bg-primary-50 px-2 py-0.5 text-[10px] font-semibold text-primary-800">
                                        Leído
                                    </span>
                                    <p class="mt-1 text-[10px] tabular-nums text-neutral-600">{{ $fila['fecha_lectura'] }}</p>
                                @else
                                    <span class="inline-flex rounded-lg border border-neutral-200 bg-neutral-100 px-2 py-0.5 text-[10px] font-semibold text-neutral-600">
                                        Sin leer
                                    </span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="shrink-0 border-t border-accent-200 bg-accent-50/40 px-4 py-3 sm:px-5 sm:py-4">
                <button type="button"
                        wire:click="cerrarDetalleLectura"
                        class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-800 shadow-sm transition hover:bg-accent-50 sm:ml-auto sm:w-auto">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
@endif
