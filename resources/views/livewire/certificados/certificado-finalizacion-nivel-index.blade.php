{{-- Certificado Jardín / Sexto Grado: cursos → alumnos → datos comunes → PDF. --}}
<div class="mx-auto w-full max-w-5xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Certificados</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $titulo }}</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al panel
            </a>
        </div>
    </section>

    <nav class="flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-500" aria-label="Pasos">
        <span @class(['rounded-full px-3 py-1', 'bg-primary-600 text-white' => $paso === 'cursos', 'bg-accent-100 text-neutral-600' => $paso !== 'cursos'])>1. Cursos</span>
        <span @class(['rounded-full px-3 py-1', 'bg-primary-600 text-white' => $paso === 'alumnos', 'bg-accent-100 text-neutral-600' => $paso !== 'alumnos'])>2. Estudiantes</span>
        <span @class(['rounded-full px-3 py-1', 'bg-primary-600 text-white' => $paso === 'formulario', 'bg-accent-100 text-neutral-600' => $paso !== 'formulario'])>3. Datos e impresión</span>
    </nav>

    @error('guardar')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror
    @error('matriculasSeleccionadas')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    @if ($paso === 'cursos')
        <div class="se-card overflow-hidden p-0">
            <div class="border-b border-accent-200 bg-accent-50 px-5 py-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ $etiquetaCursos }}</p>
                <p class="text-sm text-neutral-600">Elija el curso para listar los estudiantes.</p>
            </div>
            <div class="w-full overflow-x-auto px-4 py-4 se-grid-angosta-wrap">
                <table class="se-grid-pocos-campos w-auto text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Curso</th>
                            <th scope="col" class="py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cursos as $c)
                            <tr wire:key="cert-fin-curso-{{ $c->Id }}" class="hover:bg-accent-50/60">
                                <td class="py-2.5 font-medium text-neutral-800">{{ $c->nombreParaListado() }}</td>
                                <td class="py-2.5">
                                    <button type="button"
                                            wire:click="elegirCurso({{ (int) $c->Id }})"
                                            class="rounded-xl bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700">
                                        Estudiantes
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-2 py-10 text-center text-sm text-neutral-500">
                                    No hay {{ mb_strtolower($etiquetaCursos) }} en el ciclo lectivo activo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($paso === 'alumnos')
        <div class="se-card overflow-hidden p-0">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-accent-200 bg-accent-50 px-5 py-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Estudiantes</p>
                    <p class="text-sm text-neutral-600">{{ $cursoNombre !== '' ? $cursoNombre : 'Curso' }} · marque uno, varios o todos.</p>
                </div>
                <button type="button"
                        wire:click="volverACursos"
                        class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-300 hover:bg-accent-50">
                    Volver a cursos
                </button>
            </div>

            @if ($hayMatriculas)
                <div class="se-toolbar-pocos-campos border-b border-accent-100 px-5 py-3">
                    <button type="button"
                            wire:click="seleccionarTodasMatriculas"
                            class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-300 hover:bg-accent-50">
                        Marcar todos
                    </button>
                    <button type="button"
                            wire:click="quitarTodasMatriculas"
                            class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-600 shadow-sm transition hover:border-accent-300 hover:bg-accent-50">
                        Desmarcar todos
                    </button>
                    @if ($cantidadSeleccionados > 0)
                        <span class="se-pill">{{ $cantidadSeleccionados }} seleccionado{{ $cantidadSeleccionados === 1 ? '' : 's' }}</span>
                    @endif
                    <button type="button"
                            wire:click="continuarAFormulario"
                            @disabled(! $this->puedeContinuarAlumnos())
                            class="rounded-xl bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50">
                        Continuar
                    </button>
                </div>
            @endif

            <div class="w-full overflow-x-auto px-4 pb-4 pt-1 se-grid-angosta-wrap">
                <table class="w-max max-w-full table-auto divide-y divide-accent-200 text-sm se-grid-pocos-campos">
                    <thead class="bg-white">
                        <tr>
                            <th scope="col" class="w-10 py-3 pl-5 pr-1 text-center">
                                @if ($hayMatriculas)
                                    <input type="checkbox"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                           title="Marcar o desmarcar todos"
                                           @checked($todasMarcadas)
                                           wire:click="toggleSeleccionTodas">
                                @endif
                            </th>
                            <th scope="col" class="py-3 pl-2 pr-5 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Apellido y nombre</th>
                            <th scope="col" class="py-3 pl-2 pr-5 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">DNI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white">
                        @forelse ($matriculas as $mat)
                            <tr class="hover:bg-accent-50/60" wire:key="cert-fin-mat-{{ $mat->id }}">
                                <td class="py-3 pl-5 pr-1 text-center align-middle">
                                    <input type="checkbox"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                           wire:model.live="matriculasSeleccionadas"
                                           value="{{ $mat->id }}">
                                </td>
                                <td class="py-3 pl-2 pr-5 align-middle font-medium text-neutral-800">
                                    {{ trim((string) ($mat->legajo?->nombre_completo ?? '')) === '' ? '—' : $mat->legajo->nombre_completo }}
                                </td>
                                <td class="py-3 pl-2 pr-5 align-middle text-neutral-600">
                                    {{ trim((string) ($mat->legajo?->dni ?? '')) !== '' ? $mat->legajo->dni : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-10 text-center text-sm text-neutral-500">
                                    No hay matrículas regulares en este curso para el ciclo lectivo actual.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($paso === 'formulario')
        <div class="se-card p-5 sm:p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Datos comunes</p>
                    <p class="text-sm text-neutral-600">
                        {{ $cursoNombre }} · {{ $cantidadSeleccionados }} estudiante{{ $cantidadSeleccionados === 1 ? '' : 's' }}
                    </p>
                </div>
                <button type="button"
                        wire:click="volverAAlumnos"
                        class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-300 hover:bg-accent-50">
                    Volver a estudiantes
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="cert-fin-serie" class="form-label">Serie</label>
                    <input id="cert-fin-serie" type="text" wire:model="serie" class="form-input mt-1.5" maxlength="50">
                    @error('serie') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="cert-fin-mes-apro" class="form-label">Mes de aprobación</label>
                    <input id="cert-fin-mes-apro" type="text" wire:model="mesApro" class="form-input mt-1.5" maxlength="40" placeholder="diciembre">
                    @error('mesApro') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="cert-fin-ano-apro" class="form-label">Año de aprobación</label>
                    <input id="cert-fin-ano-apro" type="text" wire:model="anoApro" class="form-input mt-1.5" maxlength="20">
                    @error('anoApro') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="cert-fin-dia-em" class="form-label">Día de emisión</label>
                    <input id="cert-fin-dia-em" type="text" wire:model="diaEmision" class="form-input mt-1.5" maxlength="40">
                    @error('diaEmision') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="cert-fin-mes-em" class="form-label">Mes de emisión</label>
                    <input id="cert-fin-mes-em" type="text" wire:model="mesEmision" class="form-input mt-1.5" maxlength="40">
                    @error('mesEmision') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="cert-fin-ano-em" class="form-label">Año de emisión</label>
                    <input id="cert-fin-ano-em" type="text" wire:model="anoEmision" class="form-input mt-1.5" maxlength="20">
                    @error('anoEmision') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="cert-fin-ppi" class="form-label">Observaciones</label>
                    <textarea id="cert-fin-ppi" wire:model="ppi" rows="3" class="form-input mt-1.5 leading-relaxed" maxlength="500"></textarea>
                    @error('ppi') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button type="button"
                        wire:click="guardarDatos"
                        class="rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:border-primary-300 hover:bg-accent-50">
                    Guardar
                </button>
                <button type="button"
                        wire:click="imprimir"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    Imprimir
                </button>
            </div>
        </div>
    @endif

    @script
    <script>
        $wire.on('se-swal-exito', (e) => window.seSwalExito(e.mensaje));
        $wire.on('se-swal-error', (e) => window.seSwalError(e.mensaje));
    </script>
    @endscript
</div>
