<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <p class="se-eyebrow">Docentes / Usuarios</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Preceptores por curso</h2>
                    <p class="text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                    <p class="max-w-xl text-xs text-white/65">
                        Asigne quién es el preceptor de cada curso de este año lectivo. El personal se toma de los legajos con rol Preceptor.
                    </p>
                </div>
                <span class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white/85">
                    <span class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Con preceptor</span>
                    <span class="text-xl font-bold tabular-nums">{{ $cursosConAsignacion }} / {{ $totalCursos }}</span>
                </span>
            </div>
        </section>

        @if (! $tablaOk)
            <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">
                {{ $mensajeTabla }}
            </div>
        @else
            <div class="se-card overflow-hidden">
                <div class="se-toolbar-pocos-campos border-b border-accent-100 bg-white px-5 py-3">
                    <div class="relative min-w-[12rem] max-w-md flex-1">
                        <label for="se-ppc-buscar-curso" class="sr-only">Buscar curso</label>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <input id="se-ppc-buscar-curso"
                               wire:model.live.debounce.300ms="search"
                               type="search"
                               placeholder="Buscar curso…"
                               class="form-input pl-9"
                               autocomplete="off">
                    </div>
                </div>

                @if ($preceptores->isEmpty())
                    <p class="border-b border-accent-100 px-5 py-3 text-center text-sm text-neutral-600">
                        No hay preceptores en el nivel. Cargue el rol Preceptor en Legajos del docente.
                    </p>
                @endif

                <div class="w-full overflow-x-auto px-4 py-2 se-grid-angosta-wrap">
                    <table class="se-grid-pocos-campos w-auto text-sm">
                        <thead>
                            <tr class="bg-accent-50/80 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                                <th scope="col" class="py-2 text-left">Curso</th>
                                <th scope="col" class="py-2 text-left">Preceptor</th>
                                <th scope="col" class="py-2 text-left">Asignar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-100 bg-white">
                            @forelse ($cursos as $curso)
                                @php
                                    $idCurso = (int) $curso->Id;
                                    $asignados = $asignaciones[$idCurso] ?? [];
                                    $idsAsignados = collect($asignados)->pluck('idProfesor')->map(fn ($id) => (int) $id)->all();
                                @endphp
                                <tr class="hover:bg-accent-50/60" wire:key="preceptor-curso-{{ $idCurso }}">
                                    <td class="py-2 align-middle font-medium text-neutral-800">
                                        {{ $curso->nombreParaListado() }}
                                    </td>
                                    <td class="py-2 align-middle">
                                        @if ($asignados === [])
                                            <span class="text-neutral-400">Sin asignar</span>
                                        @else
                                            <ul class="flex flex-col gap-1.5">
                                                @foreach ($asignados as $asig)
                                                    <li class="flex items-center gap-2">
                                                        <span class="se-pill">{{ $asig['nombre'] }}</span>
                                                        <button type="button"
                                                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-accent-200 bg-white text-red-600 transition hover:bg-red-50"
                                                                title="Quitar"
                                                                x-on:click="window.seSwalConfirmar('¿Quitar este preceptor del curso?', 'Quitar asignación', { confirmButtonText: 'Sí, quitar' }).then((ok) => { if (ok) $wire.quitar({{ $idCurso }}, {{ (int) $asig['idProfesor'] }}); })">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                      d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td class="py-2 align-middle">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                            <label for="se-ppc-nuevo-{{ $idCurso }}" class="sr-only">Preceptor para {{ $curso->nombreParaListado() }}</label>
                                            <select id="se-ppc-nuevo-{{ $idCurso }}"
                                                    wire:model="nuevoPreceptorId.{{ $idCurso }}"
                                                    class="form-select min-w-[12rem] @error('nuevoPreceptorId.'.$idCurso) ring-2 ring-red-400 @enderror"
                                                    @disabled($preceptores->isEmpty())>
                                                <option value="">— Elegir preceptor —</option>
                                                @foreach ($preceptores as $p)
                                                    @if (! in_array((int) $p->id, $idsAsignados, true))
                                                        <option value="{{ (int) $p->id }}">
                                                            {{ trim(((string) $p->apellido).', '.((string) $p->nombre)) }}
                                                        </option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            <button type="button"
                                                    wire:click="asignar({{ $idCurso }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="asignar({{ $idCurso }})"
                                                    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60"
                                                    @disabled($preceptores->isEmpty())>
                                                Asignar
                                            </button>
                                        </div>
                                        @error('nuevoPreceptorId.'.$idCurso)
                                            <div class="mt-1 text-[11px] text-red-700">{{ $message }}</div>
                                        @enderror
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-5 py-10 text-center text-sm text-neutral-500">
                                        @if (trim($search) !== '')
                                            No hay cursos que coincidan con la búsqueda.
                                        @else
                                            No hay cursos en este nivel y ciclo lectivo.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    @script
    <script>
        function payloadDeEvento(event) {
            if (event && typeof event === 'object' && ! Array.isArray(event) && event.mensaje != null) {
                return event;
            }
            if (Array.isArray(event) && event[0] && typeof event[0] === 'object') {
                return event[0];
            }
            return event?.detail && typeof event.detail === 'object' ? event.detail : {};
        }
        $wire.on('se-swal-exito', (e) => {
            const msg = payloadDeEvento(e)?.mensaje ?? 'Listo.';
            if (typeof window.seSwalExito === 'function') window.seSwalExito(msg);
        });
        $wire.on('se-swal-error', (e) => {
            const msg = payloadDeEvento(e)?.mensaje ?? 'Error.';
            if (typeof window.seSwalError === 'function') window.seSwalError(msg);
        });
    </script>
    @endscript
</div>
