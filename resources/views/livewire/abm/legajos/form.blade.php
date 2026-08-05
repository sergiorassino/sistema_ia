<div class="se-page se-legajo-form">
    {{-- Flash --}}
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if (session('warning'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
             class="se-soft-card flex items-center gap-3 border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <svg class="h-4 w-4 flex-shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
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
            <p class="se-eyebrow">Legajos de estudiantes</p>
            <div>
                @if ($id)
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl" title="ID {{ $id }}">{{ trim($apellido) !== '' || trim($nombre) !== '' ? "{$apellido}, {$nombre}" : ($puedeEditar ? 'Editar legajo' : 'Consultar legajo') }}</h2>
                    <p class="mt-2 text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                        <span class="text-white/45"> · </span> {{ $puedeEditar ? 'Editar legajo' : 'Consultar legajo' }}
                    </p>
                @else
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Nuevo legajo</h2>
                    <p class="mt-2 text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-2 sm:justify-end">
            @if ($id)
                <div class="mr-2 w-full sm:mr-6 sm:w-auto sm:border-r sm:border-white/20 sm:pr-6">
                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <button type="button" wire:click="openMatriculas"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-white/25 bg-white/5 px-4 py-2.5 text-sm font-bold tracking-wide text-white shadow-sm transition hover:bg-white/15 sm:w-auto">
                            MATRÍCULAS
                        </button>
                        <x-nav-contexto-estudiante
                            destino="abm.legajos.familia"
                            :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::LEGAJO_ABM"
                            :id-legajos="$id"
                            tag="a">
                            <span class="inline-flex w-full items-center justify-center rounded-xl border border-white/25 bg-white/5 px-4 py-2.5 text-sm font-bold tracking-wide text-white shadow-sm transition hover:bg-white/15 sm:w-auto">
                                FAMILIA
                            </span>
                        </x-nav-contexto-estudiante>
                    </div>
                </div>
            @endif

            <a href="{{ route('abm.legajos', ['focus' => $id]) }}" class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/15">{{ $puedeEditar ? 'Cancelar' : 'Volver al listado' }}</a>

            @if ($puedeEditar)
                <button wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Guardar legajo</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </button>
            @endif
        </div>
        </div>
    </section>

    <div class="se-card overflow-hidden"
         x-data="{
             localFotoPreview: null,
             revokeLocalFotoPreview() {
                 if (this.localFotoPreview) {
                     URL.revokeObjectURL(this.localFotoPreview);
                     this.localFotoPreview = null;
                 }
             }
         }">
        {{-- Tabs (fuera del fieldset: en modo consulta deben poder cambiar de solapa) --}}
        <div class="border-b border-accent-200 bg-white">
            <nav class="se-form-tabs">
                @foreach ($tabsVisibles as $tab => $label)
                    <button type="button"
                            wire:click="setTab('{{ $tab }}')"
                            @class([
                                'se-form-tab',
                                'se-form-tab-active' => $activeTab === $tab,
                                'se-form-tab-idle' => $activeTab !== $tab,
                            ])>
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        <div @class([
            'lg:flex lg:items-start' => $mostrarFotoSticky ?? false,
        ])>
        <div class="min-w-0 flex-1">
        <fieldset @disabled(! $puedeEditar) class="min-w-0 border-0 p-0 m-0">
        {{-- Contenido de solapas: parametrizado = orden desde campos_legajo; sin param = plantillas legacy --}}
        @if($modoParametrizadoLegajo)
            <div class="space-y-5 px-5 py-5 sm:px-6" wire:key="legajo-tab-{{ $activeTab }}">
                @if($activeTab === 'alumno')
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
                        @foreach($columnasPorSolapaSlug['alumno'] ?? [] as $campo)
                            @include('livewire.abm.legajos.partials.legajo-campo-dinamico', ['campo' => $campo, 'familias' => $familias, 'sexosOpciones' => $sexosOpciones])
                        @endforeach
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @forelse(($columnasPorSolapaSlug[$activeTab] ?? []) as $campo)
                            @include('livewire.abm.legajos.partials.legajo-campo-dinamico', ['campo' => $campo, 'familias' => $familias, 'sexosOpciones' => $sexosOpciones])
                        @empty
                            <p class="text-sm text-neutral-500 sm:col-span-2">No hay campos asignados a esta solapa en Campos activos.</p>
                        @endforelse
                    </div>
                @endif
            </div>
        @else
        @php($activePanel = $tabSlugToPanel[$activeTab] ?? $activeTab)
        <div class="space-y-5 px-5 py-5 sm:px-6" wire:key="legajo-tab-{{ $activeTab }}">

            {{-- ── TAB ALUMNO ── --}}
            @if ($activePanel === 'alumno')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Trío obligatorio: solo en la solapa cuyo slug es «alumno» (no en plantilla alumno reutilizada) --}}
                    @if ($activeTab === 'alumno')
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
                    @endif

                    @include('livewire.abm.legajos.partials.campos-opcionales-alumno-panel')
                </div>
            @endif

            {{-- ── TAB DOMICILIO ── --}}
            @if ($activePanel === 'domicilio')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @if($showFieldEnTab('callenum'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Calle y número</label>
                        <input wire:model="callenum" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('barrio'))
                    <div>
                        <label class="form-label">Barrio</label>
                        <input wire:model="barrio" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('localidad'))
                    <div>
                        <label class="form-label">Localidad</label>
                        <input wire:model="localidad" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('codpos'))
                    <div>
                        <label class="form-label">Código postal</label>
                        <input wire:model="codpos" type="text" maxlength="10" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('telefono'))
                    <div>
                        <label class="form-label">Teléfono</label>
                        <input wire:model="telefono" type="text" maxlength="60" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('email'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Email</label>
                        <input wire:model="email" type="text" maxlength="100" class="form-input @error('email') border-red-400 @enderror">
                        @error('email') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    @if($showFieldEnTab('ln_ciudad') || $showFieldEnTab('ln_depto') || $showFieldEnTab('ln_provincia') || $showFieldEnTab('ln_pais'))
                    <p class="sm:col-span-2 text-xs font-medium text-gray-500 pt-1">Lugar de nacimiento</p>
                    @if($showFieldEnTab('ln_ciudad'))
                    <div>
                        <label class="form-label">Ciudad</label>
                        <input wire:model="ln_ciudad" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif
                    @if($showFieldEnTab('ln_depto'))
                    <div>
                        <label class="form-label">Departamento</label>
                        <input wire:model="ln_depto" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif
                    @if($showFieldEnTab('ln_provincia'))
                    <div>
                        <label class="form-label">Provincia</label>
                        <input wire:model="ln_provincia" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif
                    @if($showFieldEnTab('ln_pais'))
                    <div>
                        <label class="form-label">País</label>
                        <input wire:model="ln_pais" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif
                    @endif
                </div>
            @endif

            {{-- ── TAB MADRE ── --}}
            @if ($activePanel === 'madre')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @if($showFieldEnTab('nombremad'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Nombre completo</label>
                        <input wire:model="nombremad" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('dnimad'))
                    <div>
                        <label class="form-label">DNI</label>
                        <input wire:model="dnimad" type="text" maxlength="10" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('fechnacmad'))
                    <div>
                        <label class="form-label">Fecha de nacimiento</label>
                        <input wire:model="fechnacmad" type="date" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('nacionmad'))
                    <div>
                        <label class="form-label">Nacionalidad</label>
                        <input wire:model="nacionmad" type="text" maxlength="20" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('estacivimad'))
                    <div>
                        <label class="form-label">Estado civil</label>
                        <input wire:model="estacivimad" type="text" maxlength="20" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('vivemad'))
                    <div>
                        <label class="form-label">¿Vive con el alumno?</label>
                        <select wire:model="vivemad" class="form-select">
                            <option value="">—</option>
                            <option value="si">Sí</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                    @endif

                    @if($showFieldEnTab('ocupacmad'))
                    <div>
                        <label class="form-label">Ocupación</label>
                        <input wire:model="ocupacmad" type="text" maxlength="30" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('domimad'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Domicilio</label>
                        <input wire:model="domimad" type="text" maxlength="100" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('telemad'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Teléfono</label>
                        <input wire:model="telemad" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('telecelmad'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Celular</label>
                        <input wire:model="telecelmad" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('emailmad'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Email</label>
                        <input wire:model="emailmad" type="text" maxlength="50" class="form-input @error('emailmad') border-red-400 @enderror">
                        @error('emailmad') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    @endif
                </div>
            @endif

            {{-- ── TAB PADRE ── --}}
            @if ($activePanel === 'padre')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @if($showFieldEnTab('nombrepad'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Nombre completo</label>
                        <input wire:model="nombrepad" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('dnipad'))
                    <div>
                        <label class="form-label">DNI</label>
                        <input wire:model="dnipad" type="text" maxlength="10" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('fechnacpad'))
                    <div>
                        <label class="form-label">Fecha de nacimiento</label>
                        <input wire:model="fechnacpad" type="date" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('nacionpad'))
                    <div>
                        <label class="form-label">Nacionalidad</label>
                        <input wire:model="nacionpad" type="text" maxlength="20" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('estacivipad'))
                    <div>
                        <label class="form-label">Estado civil</label>
                        <input wire:model="estacivipad" type="text" maxlength="20" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('vivepad'))
                    <div>
                        <label class="form-label">¿Vive con el alumno?</label>
                        <select wire:model="vivepad" class="form-select">
                            <option value="">—</option>
                            <option value="si">Sí</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                    @endif

                    @if($showFieldEnTab('ocupacpad'))
                    <div>
                        <label class="form-label">Ocupación</label>
                        <input wire:model="ocupacpad" type="text" maxlength="30" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('domipad'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Domicilio</label>
                        <input wire:model="domipad" type="text" maxlength="100" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('telepad'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Teléfono</label>
                        <input wire:model="telepad" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('telecelpad'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Celular</label>
                        <input wire:model="telecelpad" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('emailpad'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Email</label>
                        <input wire:model="emailpad" type="text" maxlength="50" class="form-input @error('emailpad') border-red-400 @enderror">
                        @error('emailpad') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    @endif
                </div>
            @endif

            {{-- ── TAB TUTOR ── --}}
            @if ($activePanel === 'tutor')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @if($showFieldEnTab('nombretut') || $showFieldEnTab('dnitut') || $showFieldEnTab('teletut') || $showFieldEnTab('emailtut'))
                    <p class="sm:col-span-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Tutor / Referente</p>
                    @if($showFieldEnTab('nombretut'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Nombre</label>
                        <input wire:model="nombretut" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif
                    @if($showFieldEnTab('dnitut'))
                    <div>
                        <label class="form-label">DNI</label>
                        <input wire:model="dnitut" type="text" inputmode="numeric" class="form-input">
                    </div>
                    @endif
                    @if($showFieldEnTab('teletut'))
                    <div>
                        <label class="form-label">Teléfono</label>
                        <input wire:model="teletut" type="text" maxlength="20" class="form-input">
                    </div>
                    @endif
                    @if($showFieldEnTab('emailtut'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Email</label>
                        <input wire:model="emailtut" type="text" maxlength="50" class="form-input @error('emailtut') border-red-400 @enderror">
                        @error('emailtut') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    @endif
                    @endif

                    @if($showFieldEnTab('respAdmiNom') || $showFieldEnTab('respAdmiDni'))
                    <p class="sm:col-span-2 text-xs font-semibold text-gray-500 uppercase tracking-wide pt-2">Responsable administrativo</p>
                    @if($showFieldEnTab('respAdmiNom'))
                    <div>
                        <label class="form-label">Nombre</label>
                        <input wire:model="respAdmiNom" type="text" maxlength="100" class="form-input">
                    </div>
                    @endif
                    @if($showFieldEnTab('respAdmiDni'))
                    <div>
                        <label class="form-label">DNI</label>
                        <input wire:model="respAdmiDni" type="text" inputmode="numeric" class="form-input">
                    </div>
                    @endif
                    @endif
                </div>
            @endif

            {{-- ── TAB ESCOLARIDAD ── --}}
            @if ($activePanel === 'escolar')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Campos típicos de solapa «Alumno» si se asignaron a Otros / escolar --}}
                    @include('livewire.abm.legajos.partials.campos-opcionales-alumno-panel')

                    @if($showFieldEnTab('escori'))
                    <div>
                        <label class="form-label">Escuela de origen</label>
                        <input wire:model="escori" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('destino'))
                    <div>
                        <label class="form-label">Destino</label>
                        <input wire:model="destino" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('parroquia'))
                    <div>
                        <label class="form-label">Parroquia</label>
                        <input wire:model="parroquia" type="text" maxlength="50" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('ec_padres'))
                    <div>
                        <label class="form-label">Estado civil de los padres</label>
                        <input wire:model="ec_padres" type="text" maxlength="30" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('vivecon'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Vive con</label>
                        <input wire:model="vivecon" type="text" maxlength="200" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('hermanos'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Hermanos</label>
                        <textarea wire:model="hermanos" rows="2" class="form-input resize-y"></textarea>
                    </div>
                    @endif

                    @if($showFieldEnTab('needes'))
                    <div>
                        <label class="form-label">¿Necesidades especiales?</label>
                        <select wire:model="needes" class="form-select">
                            <option value="">No</option>
                            <option value="si">Sí</option>
                        </select>
                    </div>
                    @endif

                    @if($showFieldEnTab('certDisc'))
                    <div>
                        <label class="form-label">Certif. discapacidad</label>
                        <input wire:model="certDisc" type="text" maxlength="100" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('needes') && $showFieldEnTab('needes_detalle') && $needes === 'si')
                        <div class="sm:col-span-2">
                            <label class="form-label">Detalle necesidades especiales</label>
                            <textarea wire:model="needes_detalle" rows="2" class="form-input resize-y"></textarea>
                        </div>
                    @endif

                    @if($showFieldEnTab('identif'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Identificación (CUIL u otro)</label>
                        <input wire:model="identif" type="text" maxlength="100" class="form-input">
                    </div>
                    @endif

                    @if($showFieldEnTab('retira'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Personas autorizadas a retirar</label>
                        <textarea wire:model="retira" rows="2" class="form-input resize-y"></textarea>
                    </div>
                    @endif

                    @if($showFieldEnTab('emeravis'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Contacto de emergencia</label>
                        <textarea wire:model="emeravis" rows="2" class="form-input resize-y"></textarea>
                    </div>
                    @endif

                    @if($showFieldEnTab('obs'))
                    <div class="sm:col-span-2">
                        <label class="form-label">Observaciones</label>
                        <textarea wire:model="obs" rows="3" class="form-input resize-y"></textarea>
                    </div>
                    @endif
                </div>
            @endif

        </div>
        @endif
        </fieldset>
        </div>{{-- /flex-1 --}}

        @if ($mostrarFotoSticky ?? false)
            @include('livewire.abm.legajos.partials.foto-carnet-sticky')
        @endif
        </div>{{-- /lg:flex --}}

        {{-- Footer --}}
        <div class="border-t border-accent-200 bg-accent-50/70 px-5 py-3 sm:px-6">
            <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-500">
                <span>Datos del estudiante</span>
                <span class="text-accent-300">/</span>
                <span>Campos obligatorios marcados con *</span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════ MATRICULAS MODAL ═══════════════════ --}}
    @if ($showMatriculasModal)
        @teleport('body')
        <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4" role="dialog" aria-modal="true"
             aria-labelledby="legajo-modal-matriculas-titulo">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="dismissMatriculasModal"></div>

            <div class="relative z-10 my-auto flex w-full max-w-5xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),42rem)]" @click.stop>
                <div class="flex shrink-0 items-center justify-between border-b border-accent-200 bg-white px-6 py-4">
                    <div>
                        @if ($showMatriculaForm)
                            <h3 id="legajo-modal-matriculas-titulo" class="text-base font-bold text-neutral-900">
                                {{ $matriculaEditId ? 'Editar matrícula' : 'Nueva matrícula' }}
                            </h3>
                        @else
                            <h3 id="legajo-modal-matriculas-titulo" class="text-base font-bold text-neutral-900">Matrículas del estudiante</h3>
                        @endif
                        <p class="mt-0.5 text-xs font-medium text-neutral-500">Nivel: {{ schoolCtx()->nivelNombre() }} · Año activo: {{ schoolCtx()->terlecAno() }}</p>
                    </div>
                    <button wire:click="dismissMatriculasModal" class="text-gray-400 hover:text-gray-600" type="button"
                            aria-label="{{ $showMatriculaForm ? 'Volver al listado' : 'Cerrar' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                @unless ($showMatriculaForm)
                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 px-6 py-4">
                        <div class="se-pill">{{ $matriculasAlumno->count() }} registro(s)</div>
                        @if ($puedeEditar)
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                @if ($matriculaAnioActivo)
                                    <button
                                        wire:click="openCambioCurso({{ $matriculaAnioActivo->id }})"
                                        type="button"
                                        class="btn-primary btn-sm"
                                    >
                                        Cambio de curso
                                    </button>
                                @endif
                                <button wire:click="openNuevaMatricula" type="button" class="btn-secondary btn-sm">Nueva matrícula</button>
                            </div>
                        @endif
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto px-6 pb-6">
                        <div class="overflow-x-auto rounded-2xl border border-accent-200">
                            <table class="min-w-full border-collapse">
                                <thead class="bg-accent-50">
                                    <tr>
                                        <th class="table-header w-24">Año</th>
                                        <th class="table-header">Curso y sección</th>
                                        <th class="table-header w-40">Condición</th>
                                        <th class="table-header w-32">N°</th>
                                        <th class="table-header w-36">F. matrícula</th>
                                        <th class="table-header w-36">F. baja</th>
                                        <th class="table-header w-28">Bloq. ped.</th>
                                        <th class="table-header w-28">Bloq. adm.</th>
                                        <th class="table-header text-right w-36">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    @forelse ($matriculasAlumno as $m)
                                        <tr class="hover:bg-gray-50 transition-colors" title="ID {{ $m->id }}">
                                            <td class="table-cell font-mono">{{ $m->terlec?->ano ?? '—' }}</td>
                                            <td class="table-cell">{{ $m->curso?->cursec ? trim($m->curso->cursec) : '—' }}</td>
                                            <td class="table-cell">{{ $m->condicion?->condicion ?? '—' }}</td>
                                            <td class="table-cell font-mono">{{ $m->nroMatricula ?? '—' }}</td>
                                            <td class="table-cell font-mono">{{ $m->fechaMatricula?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="table-cell font-mono">{{ $m->fechaBaja?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="table-cell text-center font-mono">{{ ($m->bloqmatr ?? false) ? '1' : '0' }}</td>
                                            <td class="table-cell text-center font-mono">{{ ($m->bloqadmi ?? false) ? '1' : '0' }}</td>
                                            <td class="table-cell text-right whitespace-nowrap">
                                                @if ($puedeEditar)
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button wire:click="openEditMatricula({{ $m->id }})" type="button" class="btn-secondary btn-sm">Editar</button>
                                                        <button wire:click="confirmDeleteMatricula({{ $m->id }})" type="button" class="btn-danger btn-sm">Borrar</button>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="table-cell text-center text-gray-400 py-10">Sin matrículas cargadas.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endunless

                {{-- Matricula form (create/edit) --}}
                @if ($showMatriculaForm)
                    <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                        @if ($matriculaEditFueraDeAnioActivo)
                            <div class="mb-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                </svg>
                                <p>
                                    Las matrículas deben editarse con el sistema en el año de la matrícula a editar.
                                    @if ($m_terlec_ano !== '')
                                        <span class="mt-1 block font-semibold">Año de esta matrícula: {{ $m_terlec_ano }} · Año activo en el sistema: {{ schoolCtx()->terlecAno() ?? '—' }}</span>
                                    @endif
                                </p>
                            </div>
                        @endif

                        <fieldset @disabled($matriculaEditFueraDeAnioActivo) class="min-w-0 border-0 p-0 m-0 disabled:opacity-60">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div class="lg:col-span-2">
                                    <label class="form-label">Curso y sección *</label>
                                    @if ($matriculaEditFueraDeAnioActivo)
                                        <input type="text" class="form-input bg-gray-100" readonly value="{{ $matriculaCursoEtiqueta }}">
                                    @else
                                        <select
                                            wire:model.live="m_idCursos"
                                            wire:change="evaluarCambioCursoMatriculaDesdeUi"
                                            class="form-select @error('m_idCursos') border-red-400 @enderror"
                                        >
                                            <option value="">— Seleccione —</option>
                                            @foreach ($cursos as $c)
                                                <option value="{{ $c->Id }}">{{ trim($c->cursec) }}</option>
                                            @endforeach
                                        </select>
                                        @error('m_idCursos') <p class="form-error">{{ $message }}</p> @enderror
                                    @endif
                                </div>

                                <div>
                                    <label class="form-label">Condición *</label>
                                    <select wire:model="m_idCondiciones" class="form-select @error('m_idCondiciones') border-red-400 @enderror">
                                        <option value="">— Seleccione —</option>
                                        @foreach ($condiciones as $cnd)
                                            <option value="{{ $cnd->id }}">{{ $cnd->condicion }}</option>
                                        @endforeach
                                    </select>
                                    @error('m_idCondiciones') <p class="form-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="form-label">Año lectivo</label>
                                    <input wire:model="m_terlec_ano" type="text" class="form-input bg-gray-100" readonly>
                                    <input wire:model="m_idTerlec" type="hidden">
                                </div>

                                <div>
                                    <label class="form-label">Nivel</label>
                                    <input wire:model="m_nivel_nombre" type="text" class="form-input bg-gray-100" readonly>
                                    <input wire:model="m_idNivel" type="hidden">
                                </div>

                                <div>
                                    <label class="form-label">Número de matrícula</label>
                                    <input wire:model="m_nroMatricula" type="text" maxlength="20" class="form-input">
                                </div>

                                <div>
                                    <label class="form-label">Fecha de matrícula</label>
                                    <input wire:model="m_fechaMatricula" type="date" class="form-input @error('m_fechaMatricula') border-red-400 @enderror">
                                    @error('m_fechaMatricula') <p class="form-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="form-label">Fecha de baja</label>
                                    <input wire:model="m_fechaBaja" type="date" class="form-input @error('m_fechaBaja') border-red-400 @enderror">
                                    @error('m_fechaBaja') <p class="form-error">{{ $message }}</p> @enderror
                                </div>

                                <div class="flex flex-col justify-end gap-3 sm:col-span-2 lg:col-span-3">
                                    <label class="inline-flex items-center gap-2 text-sm text-neutral-700 cursor-pointer">
                                        <input type="checkbox" wire:model="m_bloqmatr" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                        <span>Bloqueo pedagógico</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-neutral-700 cursor-pointer">
                                        <input type="checkbox" wire:model="m_bloqadmi" class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                        <span>Bloqueo administrativo</span>
                                    </label>
                                </div>
                            </div>
                        </fieldset>

                        <div class="mt-4 flex flex-wrap justify-end gap-3">
                            <button wire:click="cancelMatriculaForm" type="button" class="btn-secondary">Volver al listado</button>
                            @unless ($matriculaEditFueraDeAnioActivo)
                                <button wire:click="saveMatricula" type="button" wire:loading.attr="disabled" class="btn-primary">
                                    <span wire:loading.remove wire:target="saveMatricula">Guardar matrícula</span>
                                    <span wire:loading wire:target="saveMatricula">Guardando…</span>
                                </button>
                            @endunless
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @endteleport
    @endif

    {{-- ═══════════════════ CONFIRM CAMBIO DE PLAN (MATRÍCULA) ═══════════════════ --}}
    @if ($showMatriculaPlanConfirm)
        @teleport('body')
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-neutral-900/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-100">
                            <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="mb-1 text-base font-semibold text-gray-800">Cambio de plan de estudio</h3>
                            <p class="text-sm leading-relaxed text-gray-600">{{ $matriculaPlanConfirmInfo }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 bg-accent-50 px-6 pb-5">
                    <button type="button" wire:click="cancelMatriculaPlanChange" class="btn-secondary">Cancelar</button>
                    <button type="button" wire:click="confirmMatriculaPlanChange" class="btn-danger">Continuar</button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- ═══════════════════ CAMBIO DE CURSO ═══════════════════ --}}
    @if ($showCambioCursoModal)
        @teleport('body')
        <div class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
             role="dialog" aria-modal="true" aria-labelledby="legajo-modal-cambio-curso-titulo">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="closeCambioCurso"></div>
            <div class="relative z-10 my-auto flex w-full max-w-md max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5"
                 @click.stop>
                <div class="flex shrink-0 items-center justify-between border-b border-accent-200 px-6 py-4">
                    <div>
                        <h3 id="legajo-modal-cambio-curso-titulo" class="text-base font-bold text-neutral-900">Cambio de curso</h3>
                        <p class="mt-0.5 text-xs font-medium text-neutral-500">
                            Matrícula del año activo · {{ schoolCtx()->terlecAno() }}
                        </p>
                    </div>
                    <button wire:click="closeCambioCurso" type="button" class="text-gray-400 hover:text-gray-600" aria-label="Cerrar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5 space-y-4">
                    <div>
                        <label class="form-label">Curso actual</label>
                        <input type="text" class="form-input bg-gray-100" readonly value="{{ $cambioCursoOrigenLabel }}">
                    </div>
                    <div>
                        <label class="form-label">Curso de destino *</label>
                        <select wire:model="cambioCursoDestinoId" class="form-select @error('cambioCursoDestinoId') border-red-400 @enderror">
                            <option value="">— Seleccione —</option>
                            @foreach ($cursos as $c)
                                @if ((int) $c->Id !== (int) $cambioCursoOrigenId)
                                    <option value="{{ $c->Id }}">{{ trim($c->cursec) }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error('cambioCursoDestinoId') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs leading-relaxed text-neutral-500">
                        Se actualiza la matrícula del año, las cuotas generadas del ciclo y las calificaciones.
                        Inasistencias y seguimiento disciplinario permanecen vinculados a la misma matrícula.
                    </p>
                </div>

                <div class="flex shrink-0 justify-end gap-3 border-t border-accent-200 bg-accent-50 px-6 py-4">
                    <button type="button" wire:click="closeCambioCurso" class="btn-secondary">Cancelar</button>
                    <button type="button" wire:click="confirmCambioCurso" wire:loading.attr="disabled" class="btn-primary">
                        <span wire:loading.remove wire:target="confirmCambioCurso">Confirmar cambio</span>
                        <span wire:loading wire:target="confirmCambioCurso">Procesando…</span>
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- ═══════════════════ CONFIRM CAMBIO DE PLAN (CAMBIO DE CURSO) ═══════════════════ --}}
    @if ($showCambioCursoPlanConfirm)
        @teleport('body')
        <div class="fixed inset-0 z-[110] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
             role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-neutral-900/60 backdrop-blur-sm" wire:click="cancelCambioCursoPlan"></div>
            <div class="relative z-10 my-auto w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5" @click.stop>
                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-100">
                            <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="mb-1 text-base font-semibold text-gray-800">Cambio de plan de estudio</h3>
                            <p class="text-sm leading-relaxed text-gray-600">{{ $cambioCursoPlanConfirmInfo }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 bg-accent-50 px-6 pb-5">
                    <button type="button" wire:click="cancelCambioCursoPlan" class="btn-secondary">Cancelar</button>
                    <button type="button" wire:click="confirmCambioCursoPlan" class="btn-danger">Continuar</button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- ═══════════════════ CONFIRM DELETE MATRICULA ═══════════════════ --}}
    @if ($showMatriculaConfirm)
        @teleport('body')
        <div class="fixed inset-0 z-[95] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="$set('showMatriculaConfirm', false)"></div>
            <div class="relative z-10 my-auto w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5" @click.stop>
                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div @class([
                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                            'bg-red-100' => $matriculaPuedeEliminar,
                            'bg-amber-100' => ! $matriculaPuedeEliminar,
                        ])>
                            <svg @class([
                                'h-5 w-5',
                                'text-red-600' => $matriculaPuedeEliminar,
                                'text-amber-700' => ! $matriculaPuedeEliminar,
                            ]) fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="mb-1 text-base font-semibold text-gray-800">
                                {{ $matriculaPuedeEliminar ? 'Confirmar eliminación' : 'No se puede eliminar' }}
                            </h3>
                            <p class="text-sm leading-relaxed text-gray-600">{{ $matriculaDeleteInfo }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 pb-5">
                    <button wire:click="$set('showMatriculaConfirm', false)" class="btn-secondary">
                        {{ $matriculaPuedeEliminar ? 'Cancelar' : 'Cerrar' }}
                    </button>
                    @if ($matriculaPuedeEliminar)
                        <button wire:click="deleteMatricula" wire:loading.attr="disabled" class="btn-danger">
                            <span wire:loading.remove wire:target="deleteMatricula">Eliminar</span>
                            <span wire:loading wire:target="deleteMatricula">Eliminando…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
        @endteleport
    @endif

    @script
    <script>
        $wire.on('se-swal-exito', (e) => {
            window.seSwalExito?.(e.mensaje ?? e[0]?.mensaje ?? '');
        });
        $wire.on('se-swal-error', (e) => {
            window.seSwalError?.(e.mensaje ?? e[0]?.mensaje ?? '');
        });
    </script>
    @endscript
</div>
