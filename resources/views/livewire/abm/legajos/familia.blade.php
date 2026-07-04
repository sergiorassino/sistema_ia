<div class="se-page">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if (session('warning'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
             class="se-soft-card flex items-center gap-3 border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Legajos de estudiantes</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Familia del estudiante</h2>
                    <p class="mt-2 text-sm text-white/80">
                        {{ $legajo->apellido }}, {{ $legajo->nombre }}
                        <span class="text-white/45"> · </span> DNI {{ $legajo->dni }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-start gap-2 sm:justify-end">
                <x-nav-contexto-estudiante
                    destino="abm.legajos.edit"
                    :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::LEGAJO_ABM"
                    :id-legajos="$idLegajo"
                    tag="a">
                    <span class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/15">
                        Volver al legajo
                    </span>
                </x-nav-contexto-estudiante>
            </div>
        </div>
    </section>

    @if (! $puedeEditar)
        <div class="se-soft-card border-accent-200 bg-accent-50/80 px-4 py-3 text-sm text-neutral-700">
            Modo consulta: puede ver la familia asignada y los hermanos vinculados. Para crear, editar, eliminar o reasignar familias necesita el permiso de gestión de familias.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Familia vinculada --}}
        <section class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/60 px-5 py-4 sm:px-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-600">Familia asignada</h3>
            </div>
            <div class="space-y-4 px-5 py-5 sm:px-6">
                @if ($tieneAsignacion && $familia)
                    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="form-label">Apellido</dt>
                            <dd class="text-sm font-medium text-neutral-900">{{ $familia->apellido }}</dd>
                        </div>
                        <div>
                            <dt class="form-label">Responsable</dt>
                            <dd class="text-sm text-neutral-800">{{ $familia->responsable ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="form-label">DNI responsable</dt>
                            <dd class="text-sm text-neutral-800 tabular-nums">
                                @if (filled($familia->dniResp))
                                    {{ \App\Support\Cuotas\CuotasFormato::formatearDni($familia->dniResp) }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="form-label">Email</dt>
                            <dd class="text-sm text-neutral-800 break-all">{{ $familia->email ?: '—' }}</dd>
                        </div>
                    </dl>

                    @if ($puedeEditar)
                        <div class="flex flex-wrap gap-2 border-t border-accent-200 pt-4">
                            <button type="button" wire:click="openEditFamilia" class="btn-secondary btn-sm">Editar familia</button>
                            <button type="button" wire:click="confirmDeleteFamilia" class="btn-danger btn-sm">Eliminar familia</button>
                            <button type="button" wire:click="confirmQuitarAsignacion" class="btn-secondary btn-sm">Quitar asignación de este estudiante</button>
                        </div>
                    @endif
                @elseif ($tieneAsignacion && ! $familia)
                    <p class="text-sm text-amber-800">
                        El legajo referencia la familia ID {{ $legajo->idFamilias }}, pero ese registro no existe en la base de datos.
                    </p>
                    @if ($puedeEditar)
                        <button type="button" wire:click="confirmQuitarAsignacion" class="btn-secondary btn-sm">Quitar asignación incorrecta</button>
                    @endif
                @else
                    <p class="text-sm text-neutral-600">Este estudiante no tiene un grupo familiar asignado.</p>
                @endif
            </div>
        </section>

        {{-- Hermanos --}}
        <section class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/60 px-5 py-4 sm:px-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-600">Hermanos en la misma familia</h3>
            </div>
            <div class="px-5 py-5 sm:px-6">
                @if ($tieneAsignacion && $hermanos->isNotEmpty())
                    <ul class="divide-y divide-accent-200 rounded-xl border border-accent-200 bg-white">
                        @foreach ($hermanos as $h)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-neutral-900">{{ $h->apellido }}, {{ $h->nombre }}</p>
                                    <p class="text-xs text-neutral-500">DNI {{ $h->dni }}@if($h->legajo) · Leg. {{ $h->legajo }}@endif</p>
                                </div>
                                <x-nav-contexto-estudiante
                                    destino="abm.legajos.edit"
                                    :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::LEGAJO_ABM"
                                    :id-legajos="$h->id"
                                    tag="a">
                                    <span class="btn-secondary btn-sm">Ver legajo</span>
                                </x-nav-contexto-estudiante>
                            </li>
                        @endforeach
                    </ul>
                @elseif ($tieneAsignacion)
                    <p class="text-sm text-neutral-500">No hay otros estudiantes vinculados a esta familia.</p>
                @else
                    <p class="text-sm text-neutral-500">Asigne una familia para ver posibles hermanos vinculados.</p>
                @endif
            </div>
        </section>
    </div>

    @if ($puedeEditar)
        {{-- Asignar familia existente --}}
        <section class="se-card mt-6 overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/60 px-5 py-4 sm:px-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-600">Asignar familia existente</h3>
                <p class="mt-1 text-xs text-neutral-500">Para estudiantes sin familia o para corregir una asignación equivocada.</p>
            </div>
            <div class="space-y-4 px-5 py-5 sm:px-6">
                <div>
                    <label class="form-label" for="filtro-familias">Buscar familia</label>
                    <input wire:model.live.debounce.300ms="filtroFamilias" id="filtro-familias" type="search"
                           placeholder="Apellido, responsable o email…"
                           class="form-input"
                           autocomplete="off">
                    <p class="mt-1.5 text-xs text-neutral-500">
                        Escriba al menos {{ $minCharsBusquedaFamilia }} caracteres. Las coincidencias se listan abajo; haga clic en una fila para seleccionarla.
                    </p>
                </div>

                @if ($familiaSeleccionada)
                    <div class="rounded-xl border border-primary-300 bg-primary-50/60 px-4 py-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-primary-700">Familia seleccionada</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">
                            {{ $familiaSeleccionada->apellido }}{{ $familiaSeleccionada->responsable ? ' – ' . $familiaSeleccionada->responsable : '' }}
                        </p>
                        @if ($familiaSeleccionada->email)
                            <p class="mt-0.5 text-xs text-neutral-600 break-all">{{ $familiaSeleccionada->email }}</p>
                        @endif
                    </div>
                @endif

                <div class="overflow-hidden rounded-xl border border-accent-200 bg-white" wire:key="resultados-familias-{{ md5($filtroFamilias) }}">
                    @php $filtroBusqueda = trim($filtroFamilias); @endphp
                    @if (mb_strlen($filtroBusqueda) < $minCharsBusquedaFamilia)
                        <p class="px-4 py-8 text-center text-sm text-neutral-500">
                            Ingrese al menos {{ $minCharsBusquedaFamilia }} caracteres para ver familias.
                        </p>
                    @elseif ($familiasParaAsignar->isEmpty())
                        <p class="px-4 py-8 text-center text-sm text-neutral-500">
                            No hay familias que coincidan con «{{ $filtroBusqueda }}».
                        </p>
                    @else
                        <p class="border-b border-accent-200 bg-accent-50/80 px-4 py-2 text-xs text-neutral-600">
                            {{ $familiasParaAsignar->count() }} coincidencia{{ $familiasParaAsignar->count() === 1 ? '' : 's' }}
                            @if ($familiasParaAsignar->count() >= 50)
                                <span class="text-neutral-400">(máximo 50; refine la búsqueda si falta alguna)</span>
                            @endif
                        </p>
                        <ul class="max-h-64 divide-y divide-accent-200 overflow-y-auto">
                            @foreach ($familiasParaAsignar as $f)
                                <li>
                                    <button type="button"
                                            wire:click="seleccionarFamiliaParaAsignar({{ $f->id }})"
                                            @class([
                                                'flex w-full flex-col items-start gap-0.5 px-4 py-3 text-left transition',
                                                'bg-primary-50 ring-2 ring-inset ring-primary-400' => (int) $asignarFamiliaId === (int) $f->id,
                                                'hover:bg-accent-50' => (int) $asignarFamiliaId !== (int) $f->id,
                                            ])>
                                        <span class="text-sm font-medium text-neutral-900">
                                            {{ $f->apellido }}{{ $f->responsable ? ' – ' . $f->responsable : '' }}
                                        </span>
                                        @if ($f->email)
                                            <span class="text-xs text-neutral-500 break-all">{{ $f->email }}</span>
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button"
                            wire:click="asignarFamiliaSeleccionada"
                            wire:loading.attr="disabled"
                            @disabled(! $familiaSeleccionada)
                            @class([
                                'btn-primary',
                                'opacity-50 cursor-not-allowed' => ! $familiaSeleccionada,
                            ])>
                        <span wire:loading.remove wire:target="asignarFamiliaSeleccionada">Asignar al estudiante</span>
                        <span wire:loading wire:target="asignarFamiliaSeleccionada">Asignando…</span>
                    </button>
                    <button type="button" wire:click="openCreateFamilia" class="btn-secondary">Crear familia nueva</button>
                </div>
            </div>
        </section>
    @endif

    @if ($showModalFamilia)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4 backdrop-blur-sm"
             x-data x-init="$el.querySelector('input')?.focus()">
            <div class="w-full max-w-md rounded-2xl border border-accent-200 bg-white shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-accent-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">
                        {{ $editFamiliaId ? 'Editar familia' : 'Nueva familia' }}
                    </h3>
                    <button type="button" wire:click="$set('showModalFamilia', false)" class="text-neutral-400 transition hover:text-neutral-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4 px-6 py-4">
                    <div>
                        <label class="form-label" for="familia-apellido">Apellido *</label>
                        <input wire:model="familiaApellido" id="familia-apellido" type="text" maxlength="50"
                               class="form-input @error('familiaApellido') border-red-400 @enderror">
                        @error('familiaApellido') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label" for="familia-responsable">Responsable</label>
                        <input wire:model="familiaResponsable" id="familia-responsable" type="text" maxlength="50"
                               class="form-input @error('familiaResponsable') border-red-400 @enderror">
                        @error('familiaResponsable') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label" for="familia-dni-resp">DNI del responsable</label>
                        <input wire:model="familiaDniResp" id="familia-dni-resp" type="text"
                               inputmode="numeric" maxlength="11" autocomplete="off"
                               placeholder="Solo números"
                               class="form-input tabular-nums @error('familiaDniResp') border-red-400 @enderror">
                        @error('familiaDniResp') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label" for="familia-email">Email</label>
                        <input wire:model="familiaEmail" id="familia-email" type="email" maxlength="100" autocomplete="email"
                               class="form-input @error('familiaEmail') border-red-400 @enderror">
                        @error('familiaEmail') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-3 border-t border-accent-200 bg-accent-50/60 px-6 py-4">
                    <button type="button" wire:click="$set('showModalFamilia', false)" class="btn-secondary">Cancelar</button>
                    @if ($editFamiliaId)
                        <button type="button" wire:click="saveFamilia" wire:loading.attr="disabled" class="btn-primary">
                            <span wire:loading.remove wire:target="saveFamilia">Guardar</span>
                            <span wire:loading wire:target="saveFamilia">Guardando…</span>
                        </button>
                    @else
                        <button type="button" wire:click="saveFamilia" wire:loading.attr="disabled" class="btn-secondary">
                            <span wire:loading.remove wire:target="saveFamilia">Solo crear</span>
                            <span wire:loading wire:target="saveFamilia">Guardando…</span>
                        </button>
                        <button type="button" wire:click="saveFamiliaYAsignar" wire:loading.attr="disabled" class="btn-primary">
                            <span wire:loading.remove wire:target="saveFamiliaYAsignar">Crear y asignar</span>
                            <span wire:loading wire:target="saveFamiliaYAsignar">Guardando…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($showConfirmQuitarAsignacion)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm rounded-2xl border border-accent-200 bg-white shadow-xl" @click.stop>
                <div class="px-6 py-5">
                    <h3 class="mb-2 text-base font-semibold text-neutral-900">Quitar asignación</h3>
                    <p class="text-sm text-neutral-600">
                        ¿Quitar la familia asignada a {{ $legajo->apellido }}, {{ $legajo->nombre }}?
                        El registro de familia en la base de datos no se elimina; solo se desvincula este estudiante.
                    </p>
                </div>
                <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/60 px-6 py-4">
                    <button type="button" wire:click="$set('showConfirmQuitarAsignacion', false)" class="btn-secondary">Cancelar</button>
                    <button type="button" wire:click="quitarAsignacion" wire:loading.attr="disabled" class="btn-danger">
                        <span wire:loading.remove wire:target="quitarAsignacion">Quitar asignación</span>
                        <span wire:loading wire:target="quitarAsignacion">Procesando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showConfirmDeleteFamilia)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm rounded-2xl border border-accent-200 bg-white shadow-xl" @click.stop>
                <div class="px-6 py-5">
                    <h3 class="mb-2 text-base font-semibold text-neutral-900">
                        {{ $deleteFamiliaId ? 'Confirmar eliminación' : 'No se puede eliminar' }}
                    </h3>
                    <p class="text-sm text-neutral-600">{{ $deleteFamiliaInfo }}</p>
                </div>
                <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/60 px-6 py-4">
                    <button type="button" wire:click="$set('showConfirmDeleteFamilia', false)" class="btn-secondary">
                        {{ $deleteFamiliaId ? 'Cancelar' : 'Cerrar' }}
                    </button>
                    @if ($deleteFamiliaId)
                        <button type="button" wire:click="deleteFamilia" wire:loading.attr="disabled" class="btn-danger">
                            <span wire:loading.remove wire:target="deleteFamilia">Eliminar</span>
                            <span wire:loading wire:target="deleteFamilia">Eliminando…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
