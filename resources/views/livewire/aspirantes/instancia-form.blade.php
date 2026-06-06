<div class="se-page max-w-5xl" x-data="{ copiado: false }">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Aspirantes</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ $esNueva ? 'Nueva instancia de registro' : 'Editar instancia de registro' }}
                </h2>
                <p class="max-w-2xl text-sm text-white/80">
                    Completá las secciones en orden: contexto, textos del formulario público, ventana de inscripción,
                    cursos habilitados y enlace para publicar.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <button type="submit" form="form-instancia-aspirantes" class="btn-primary">
                    <span wire:loading.remove wire:target="guardar">Guardar instancia</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
                <a href="{{ route('aspirantes.instancia') }}" class="btn-secondary">Volver al listado</a>
                @if (! $esNueva && $instanciaId)
                    <a href="{{ route('aspirantes.listado', ['instancia' => $instanciaId]) }}" class="btn-secondary">
                        Ver aspirantes registrados
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if (session('status'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit.prevent="guardar" id="form-instancia-aspirantes" class="space-y-6">

        {{-- 1. Contexto --}}
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <p class="se-section-title">1. Contexto</p>
                <p class="mt-1 text-sm text-neutral-600">
                    Nivel fijo según el contexto activo en el menú superior. El ciclo lectivo define a qué año pertenece esta ventana de inscripción.
                </p>
            </div>
            <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                <div>
                    <label class="se-label">Nivel</label>
                    <input type="text" readonly value="{{ $nivelNombre }}"
                           class="form-input w-full bg-accent-50 text-neutral-700">
                </div>
                <div>
                    <label class="se-label">Ciclo lectivo</label>
                    <select wire:model.defer="idTerlec" class="form-select w-full">
                        <option value="">— Elegir año —</option>
                        @foreach ($terlecs as $t)
                            <option value="{{ $t->id }}">{{ $t->ano }}</option>
                        @endforeach
                    </select>
                    @error('idTerlec')<p class="form-error">{{ $message }}</p>@enderror
                    @if ($terlecActual && (int) $terlecActual->id === (int) schoolCtx()->idTerlec)
                        <p class="mt-1 text-xs text-primary-700">Coincide con el ciclo lectivo activo en el contexto superior.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- 2. Textos del formulario público --}}
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <p class="se-section-title">2. Textos del formulario público</p>
                <p class="mt-1 text-sm text-neutral-600">
                    Lo que ve la familia al abrir el enlace: encabezado y mensaje de bienvenida o instrucciones.
                </p>
            </div>
            <div class="grid gap-4 px-5 py-5">
                @if ($tieneInsti)
                <div>
                    <label class="se-label">Nombre de la institución</label>
                    <input type="text" wire:model.defer="insti" maxlength="120"
                           placeholder="p. ej. Escuela Secundaria N.º 123"
                           class="form-input w-full">
                    <p class="mt-1 text-xs text-neutral-500">Primera línea del encabezado público (nombre del colegio). En alta se sugiere el de parámetros del sistema.</p>
                    @error('insti')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                @endif
                <div>
                    <label class="se-label">Título</label>
                    <input type="text" wire:model.defer="titulo" maxlength="150"
                           placeholder="p. ej. Inscripción Sala de 4 — 2027"
                           class="form-input w-full">
                    <p class="mt-1 text-xs text-neutral-500">Segunda línea del encabezado. Si queda vacío, se muestra «Registro de aspirante».</p>
                    @error('titulo')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                @if ($tieneTitulo3)
                <div>
                    <label class="se-label">Título 3</label>
                    <input type="text" wire:model.defer="titulo3" maxlength="150"
                           placeholder="p. ej. Ciclo lectivo 2027 · Nivel inicial"
                           class="form-input w-full">
                    <p class="mt-1 text-xs text-neutral-500">Tercera línea del encabezado (subtítulo o aclaración opcional).</p>
                    @error('titulo3')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                @endif
                <div>
                    <label class="se-label">Mensaje para la familia</label>
                    <textarea wire:model.defer="mensaje_publico" rows="4" maxlength="2000"
                              placeholder="Instrucciones, documentación a presentar, contacto del colegio, etc."
                              class="form-textarea w-full leading-relaxed"></textarea>
                    <p class="mt-1 text-xs text-neutral-500">Texto opcional debajo del título en el formulario público.</p>
                    @error('mensaje_publico')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- 3. Ventana y estado --}}
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <p class="se-section-title">3. Ventana y estado de inscripción</p>
                <p class="mt-1 text-sm text-neutral-600">
                    Las fechas limitan cuándo se aceptan registros. «Activa» habilita la URL; fuera del rango de fechas el formulario muestra aviso aunque esté activa.
                </p>
            </div>
            <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                <div>
                    <label class="se-label">Fecha desde</label>
                    <input type="date" wire:model.defer="fechdesde" class="form-input w-full">
                    @error('fechdesde')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="se-label">Fecha hasta</label>
                    <input type="date" wire:model.defer="fechhasta" class="form-input w-full">
                    @error('fechhasta')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2 rounded-xl border border-accent-200 bg-accent-50/50 px-4 py-3">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" id="activo" wire:model.defer="activo"
                               class="form-checkbox mt-0.5 h-4 w-4 shrink-0 text-primary-600">
                        <span class="text-sm text-neutral-800">
                            <span class="font-semibold">Instancia activa</span>
                            <span class="mt-0.5 block text-neutral-600">
                                Si está desmarcada, la URL pública informa que la inscripción está cerrada aunque esté dentro de las fechas.
                            </span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        {{-- 4. Cursos --}}
        @if ($cursosModelo->isNotEmpty())
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <p class="se-section-title">4. Cursos disponibles para inscripción</p>
                <p class="mt-1 text-sm text-neutral-600">
                    La familia elige solo entre los cursos modelo marcados (sin sección).
                    <a href="{{ route('aspirantes.cursos-modelo') }}" class="text-primary-700 underline">Gestionar cursos modelo</a>.
                </p>
            </div>
            <div class="grid gap-2 px-5 py-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cursosModelo as $m)
                    <label class="flex items-center gap-2 rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm hover:border-primary-400 transition-colors">
                        <input type="checkbox"
                               wire:model.defer="cursosSeleccionados.{{ $m->id }}"
                               class="form-checkbox h-4 w-4 text-primary-600">
                        <span class="truncate">{{ $m->nombre }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        @else
            <div class="se-soft-card px-5 py-4 text-sm text-neutral-600">
                <p class="font-semibold text-neutral-800">4. Cursos disponibles</p>
                <p class="mt-1">
                    Todavía no cargaste cursos modelo para este nivel.
                    <a href="{{ route('aspirantes.cursos-modelo') }}" class="text-primary-700 underline">Cargá el listado</a>
                    antes de habilitar inscripciones.
                </p>
            </div>
        @endif

        {{-- 5. Enlace público --}}
        @if ($this->urlPublica)
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <p class="se-section-title">5. Enlace público</p>
                <p class="mt-1 text-sm text-neutral-600">
                    Publicá esta URL en la web del colegio. El enlace es único e inmutable para esta instancia.
                </p>
            </div>
            <div class="space-y-3 px-5 py-4">
                <div>
                    <label class="se-label">URL para familias</label>
                    <div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-center">
                        <input type="text" readonly value="{{ $this->urlPublica }}" id="se-aspirantes-url"
                               class="form-input w-full font-mono text-sm">
                        <button type="button"
                                x-on:click="navigator.clipboard.writeText(document.getElementById('se-aspirantes-url').value); copiado = true; setTimeout(() => copiado = false, 2000);"
                                class="btn-secondary btn-sm shrink-0">
                            <span x-show="!copiado">Copiar</span>
                            <span x-show="copiado" x-cloak>¡Copiado!</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @else
            <div class="se-soft-card px-5 py-4 text-sm text-neutral-600">
                <p class="font-semibold text-neutral-800">5. Enlace público</p>
                <p class="mt-1">
                    @if ($esNueva)
                        Al guardar por primera vez se generará automáticamente la URL para publicar en la web del colegio.
                    @else
                        Guardá la instancia para generar la URL pública.
                    @endif
                </p>
            </div>
        @endif
    </form>
</div>
