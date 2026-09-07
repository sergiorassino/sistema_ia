<div>
    <div class="se-page se-page--ancho-completo min-w-0">
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
                 class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-2">
                    <p class="se-eyebrow">Seguimiento de gabinete</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                        <span @class(['inline-block rounded-lg px-2 py-0.5', $claseNombre])>
                            {{ $matricula->legajo?->apellido }}, {{ $matricula->legajo?->nombre }}
                        </span>
                    </h2>
                    <p class="text-sm text-white/80">
                        {{ $matricula->curso?->nombreParaListado() ?? '—' }}
                        · {{ schoolCtx()->nivelNombre() }}
                        · Año lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a href="{{ route('seguimiento.gabinete') }}"
                       class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                        Volver
                    </a>
                </div>
            </div>
        </section>

        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-neutral-500">
                        Registros desde el ingreso a la escuela (todos los ciclos). El alta se guarda en la matrícula del año actual.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @php
                            $refHistorial = \App\Support\Security\OpaqueRouteToken::forGabineteHistorial((int) $matricula->idLegajos);
                        @endphp
                        <a class="btn-secondary btn-sm"
                           target="_blank"
                           rel="noopener noreferrer"
                           href="{{ route('seguimiento.gabinete.historial', ['ref' => $refHistorial]) }}">
                            Historial
                        </a>
                        <x-nav-contexto-estudiante
                            destino="seguimiento.gabinete.create"
                            :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::SEGUIMIENTO_GABINETE"
                            :matricula="$matricula->id"
                            :curso="$matricula->idCursos"
                            class="inline">
                            <span class="btn-primary btn-sm">+ Nuevo</span>
                        </x-nav-contexto-estudiante>
                    </div>
                </div>
            </div>

            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <table class="se-gabinete-registros-tabla">
                        <colgroup>
                            <col class="se-gab-col-fecha">
                            <col class="se-gab-col-tipo">
                            <col class="se-gab-col-soli">
                            <col class="se-gab-col-motivo">
                            <col class="se-gab-col-acciones">
                        </colgroup>
                        <thead class="bg-accent-50">
                            <tr>
                                <th class="table-header se-gab-col-fecha">Fecha</th>
                                <th class="table-header se-gab-col-tipo">Tipo</th>
                                <th class="table-header">Solicitud</th>
                                <th class="table-header">Motivo</th>
                                <th class="table-header se-gab-th-acciones">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-200 bg-white">
                            @forelse ($registros as $r)
                                @php
                                    $refActa = \App\Support\Security\OpaqueRouteToken::forGabineteActa((int) $r->id, (int) $matricula->idLegajos);
                                    $motivoTexto = trim((string) ($r->motivo ?? ''));
                                    $motivoMostrar = $motivoTexto === ''
                                        ? '—'
                                        : \Illuminate\Support\Str::limit($motivoTexto, 500);
                                    $ciclo = $r->matricula?->terlec?->ano;
                                @endphp
                                <tr class="transition-colors hover:bg-accent-50/60" wire:key="gab-reg-{{ (int) $r->id }}">
                                    <td class="table-cell se-gab-col-fecha font-mono">
                                        {{ $r->fecha?->format('d/m/Y') ?? '—' }}
                                        @if ($ciclo)
                                            <span class="mt-0.5 block text-[10px] text-neutral-500">{{ $ciclo }}</span>
                                        @endif
                                    </td>
                                    <td class="table-cell se-gab-col-tipo">{{ $r->tipo?->tipo ?? ('#'.$r->idTipoSancion) }}</td>
                                    <td class="table-cell se-gab-col-soli">{{ trim((string) ($r->solipor ?? '')) !== '' ? $r->solipor : '—' }}</td>
                                    <td class="table-cell se-gab-col-motivo">
                                        <div class="se-gab-motivo">{{ $motivoMostrar }}</div>
                                    </td>
                                    <td class="table-cell se-gab-col-acciones">
                                        <div class="se-gab-acciones-wrap">
                                            <a class="btn-secondary btn-sm shrink-0"
                                               href="{{ route('seguimiento.gabinete.edit', ['id' => $r->id]) }}">
                                                Editar
                                            </a>
                                            <button type="button"
                                                    class="btn-danger btn-sm shrink-0"
                                                    x-on:click="window.seSwalConfirmar('¿Confirma borrar este registro de gabinete?', 'Confirmar borrado', { confirmButtonText: 'Sí, borrar' }).then((ok) => { if (ok) $wire.borrar({{ (int) $r->id }}); })">
                                                Borrar
                                            </button>
                                            <a class="btn-secondary btn-sm shrink-0"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               title="Imprimir acta"
                                               href="{{ route('seguimiento.gabinete.acta', ['ref' => $refActa]) }}">
                                                Acta
                                            </a>
                                            <button type="button"
                                                    class="btn-primary btn-sm shrink-0"
                                                    wire:click="abrirCompartir({{ (int) $r->id }})"
                                                    title="Compartir con docentes">
                                                Compartir
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="table-cell py-12 text-center text-sm text-neutral-500">
                                        Sin registros de gabinete para este alumno.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @teleport('body')
        <div>
            @if ($modalCompartirAbierto)
                <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="gab-modal-compartir-titulo">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarCompartir"></div>

                    <div class="relative z-10 my-auto flex w-full max-w-xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),42rem)]">
                        <div class="border-b border-accent-200 bg-accent-50/60 px-4 py-2.5 sm:px-5 sm:py-3">
                            <p id="gab-modal-compartir-titulo" class="text-sm font-bold text-neutral-900">Compartir registro</p>
                            <p class="mt-0.5 text-[11px] leading-snug text-neutral-600">
                                Docentes, directivos, preceptores y gabinete de orientación. Puede marcar uno a uno o a todos los docentes de un curso.
                            </p>
                        </div>

                        <div class="border-b border-accent-100 bg-white px-4 py-2 sm:px-5 sm:py-2.5">
                            <label for="gab-modal-curso-marcar" class="form-label">Docentes de un curso</label>
                            <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:items-stretch">
                                <select id="gab-modal-curso-marcar"
                                        wire:model="idCursoMarcar"
                                        class="form-select min-w-0 flex-1">
                                    <option value="0">— Elegir curso —</option>
                                    @foreach ($cursosMarcar as $c)
                                        <option value="{{ $c['id'] }}">{{ $c['label'] }}</option>
                                    @endforeach
                                </select>
                                <button type="button"
                                        wire:click="marcarDocentesDelCurso"
                                        class="inline-flex shrink-0 items-center justify-center rounded-xl border border-accent-200 bg-white px-3 py-2 text-xs font-semibold text-primary-800 shadow-sm transition hover:bg-accent-50">
                                    Marcar docentes del curso
                                </button>
                            </div>
                            <p class="mt-1.5 text-[11px] text-neutral-500">Marca a los profesores asignados al curso en este ciclo. Directivos, preceptores y gabinete se eligen en la lista.</p>

                            <label for="gab-modal-docentes-filtro" class="form-label mt-3">Filtrar listado</label>
                            <input id="gab-modal-docentes-filtro"
                                   type="text"
                                   wire:model.live.debounce.400ms="modalDocentesFiltro"
                                   placeholder="Apellido, nombre, DNI o rol…"
                                   class="form-input mt-1.5" />
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <button type="button"
                                        wire:click="modalDocentesSeleccionarTodosVisibles"
                                        class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition hover:bg-accent-50">
                                    Marcar visibles
                                </button>
                                <button type="button"
                                        wire:click="modalDocentesQuitarVisibles"
                                        class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50">
                                    Desmarcar visibles
                                </button>
                                <span class="text-[11px] font-medium text-neutral-500">{{ count($modalDocentesMarcados) }} seleccionados</span>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-1 sm:px-5">
                            @forelse ($modalDocentesLista as $d)
                                <label wire:key="gab-modal-docente-{{ $d['id'] }}"
                                       class="flex cursor-pointer items-start gap-2 border-b border-accent-100 py-1 last:border-b-0 hover:bg-accent-50/60">
                                    <input type="checkbox"
                                           wire:model="modalDocentesMarcados"
                                           value="{{ $d['id'] }}"
                                           class="mt-0.5 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                    <span class="min-w-0 flex-1 text-sm leading-tight text-neutral-900">
                                        <span class="font-semibold">{{ $d['label'] }}</span>
                                        @if (! empty($d['rol_label']))
                                            <span class="ml-1 text-[11px] font-medium leading-tight text-primary-700">{{ $d['rol_label'] }}</span>
                                        @endif
                                        @if (! empty($d['dni']))
                                            <span class="ml-1 text-[11px] font-normal leading-tight text-neutral-400">DNI {{ $d['dni'] }}</span>
                                        @endif
                                    </span>
                                </label>
                            @empty
                                <p class="py-8 text-center text-sm text-neutral-500">No hay destinatarios que coincidan con el filtro.</p>
                            @endforelse
                        </div>

                        <div class="flex flex-col gap-2 border-t border-accent-200 bg-accent-50/40 px-4 py-2.5 sm:flex-row sm:justify-end sm:px-5 sm:py-3">
                            <button type="button"
                                    wire:click="cerrarCompartir"
                                    class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-800 shadow-sm transition hover:bg-accent-50 sm:w-auto">
                                Cancelar
                            </button>
                            <button type="button"
                                    class="inline-flex w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 sm:w-auto"
                                    x-on:click="
                                        window.seSwalConfirmar(
                                            '¿Confirma compartir este registro de gabinete con los destinatarios seleccionados?',
                                            'Compartir registro',
                                            { confirmButtonText: 'Sí, compartir' }
                                        ).then(async (ok) => {
                                            if (! ok) { return; }
                                            if (typeof window.Swal !== 'undefined') {
                                                window.Swal.fire({
                                                    title: 'Compartiendo…',
                                                    text: 'Si el canal incluye correo, puede demorar unos segundos.',
                                                    allowOutsideClick: false,
                                                    allowEscapeKey: false,
                                                    showConfirmButton: false,
                                                    didOpen: () => window.Swal.showLoading(),
                                                });
                                            }
                                            try {
                                                await $wire.compartir();
                                            } catch (e) {
                                                window.seSwalError?.(
                                                    'No se pudo completar el envío (tiempo de espera o error de red).',
                                                    'Envío incompleto'
                                                );
                                            }
                                        })
                                    ">
                                Enviar
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endteleport

    @script
    <script>
        (function () {
            function payloadDeEvento(event) {
                if (event && typeof event === 'object' && ! Array.isArray(event) && (event.mensaje != null || event.titulo != null)) {
                    return event;
                }
                if (Array.isArray(event) && event[0] && typeof event[0] === 'object') {
                    return event[0];
                }

                return event?.detail && typeof event.detail === 'object' ? event.detail : {};
            }

            function mensajeDeEvento(event, fallback) {
                return payloadDeEvento(event)?.mensaje ?? fallback;
            }

            function tituloDeEvento(event, fallback) {
                return payloadDeEvento(event)?.titulo ?? fallback;
            }

            $wire.on('se-swal-exito', (event) => {
                window.seSwalExito?.(
                    mensajeDeEvento(event, 'Operación realizada.'),
                    tituloDeEvento(event, 'Listo')
                );
            });
            $wire.on('se-swal-error', (event) => {
                window.seSwalError?.(
                    mensajeDeEvento(event, 'No se pudo completar la operación.'),
                    tituloDeEvento(event, 'No se pudo completar')
                );
            });
            $wire.on('se-swal-aviso', (event) => {
                window.seSwalAviso?.(
                    mensajeDeEvento(event, 'Atención.'),
                    tituloDeEvento(event, 'Atención')
                );
            });
        })();
    </script>
    @endscript
</div>
