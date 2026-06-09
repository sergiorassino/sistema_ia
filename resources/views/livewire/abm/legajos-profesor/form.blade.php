<div class="se-page se-legajo-form">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif
    @if (session('warning'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
             class="se-soft-card flex items-center gap-3 border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ session('warning') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="se-soft-card border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            <p class="font-semibold">No se pudo guardar. Revise los campos indicados.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Legajos del docente</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $id ? ($puedeEditar ? 'Editar legajo' : 'Consultar legajo') : 'Nuevo legajo' }}</h2>
                    <p class="mt-2 text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Un registro por nivel
                        @if ($id)<span class="text-white/45"> · </span> ID {{ $id }}@endif
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap justify-start gap-2 sm:justify-end">
                <a href="{{ route('abm.legajos-profesor', ['focus' => $id]) }}"
                   class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/15">{{ $puedeEditar ? 'Cancelar' : 'Volver al listado' }}</a>
                @if ($puedeEditar)
                    <button type="button" wire:click="save" wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Guardar legajo</span>
                        <span wire:loading wire:target="save">Guardando…</span>
                    </button>
                @endif
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white">
            <nav class="se-form-tabs">
                @foreach ($tabsVisibles as $tab => $label)
                    <button type="button"
                            wire:click="setTab('{{ $tab }}')"
                            @class(['se-form-tab', 'se-form-tab-active' => $activeTab === $tab, 'se-form-tab-idle' => $activeTab !== $tab])>
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        <fieldset @disabled(! $puedeEditar) class="min-w-0 border-0 p-0 m-0">
        <div class="space-y-5 px-5 py-5 sm:px-6" wire:key="prof-tab-{{ $activeTab }}">
            @if($activeTab === 'docente')
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="form-label">Apellido *</label>
                        <input wire:model="apellido" type="text" maxlength="50" class="form-input @error('apellido') border-red-400 @enderror">
                        @error('apellido') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Nombre *</label>
                        <input wire:model="nombre" type="text" maxlength="50" class="form-input @error('nombre') border-red-400 @enderror">
                        @error('nombre') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">DNI *</label>
                        <input wire:model="dni" type="text" inputmode="numeric" maxlength="11" class="form-input @error('dni') border-red-400 @enderror">
                        @error('dni') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Rol *</label>
                        <select wire:model="IdTipoProf" class="form-select @error('IdTipoProf') border-red-400 @enderror">
                            <option value="">— Seleccionar —</option>
                            @foreach ($roles as $r)
                                <option value="{{ $r->id }}">{{ $r->tipo }}</option>
                            @endforeach
                        </select>
                        @error('IdTipoProf') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    @if($modoParametrizado)
                        @foreach($columnasPorSolapaSlug['docente'] ?? [] as $campo)
                            @include('livewire.abm.legajos-profesor.partials.profesor-campo-dinamico', ['campo' => $campo, 'sexosOpciones' => $sexosOpciones, 'estadosCivilesOpciones' => $estadosCivilesOpciones])
                        @endforeach
                    @else
                        @php
                            $todosCampos = ['cuil','sexo','email','emailInsti','callenum','barrio','telefono','celular','nacion','estacivi','legJunta','legEscuela','fechnaci','titulo','numreg','apto','incapac','escalafonD','escalafonE','cargo','obs'];
                        @endphp
                        @foreach($todosCampos as $col)
                            @include('livewire.abm.legajos-profesor.partials.profesor-campo-dinamico', ['campo' => ['columna' => $col, 'etiqueta' => null], 'sexosOpciones' => $sexosOpciones, 'estadosCivilesOpciones' => $estadosCivilesOpciones])
                        @endforeach
                    @endif
                </div>
            @elseif($modoParametrizado)
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @forelse(($columnasPorSolapaSlug[$activeTab] ?? []) as $campo)
                        @include('livewire.abm.legajos-profesor.partials.profesor-campo-dinamico', ['campo' => $campo, 'sexosOpciones' => $sexosOpciones, 'estadosCivilesOpciones' => $estadosCivilesOpciones])
                    @empty
                        <p class="text-sm text-neutral-500 sm:col-span-2">No hay campos asignados a esta solapa en Campos activos.</p>
                    @endforelse
                </div>
            @else
                <p class="text-sm text-neutral-500">Configure campos activos para mostrar contenido en esta solapa.</p>
            @endif
        </div>
        </fieldset>
    </div>
</div>
