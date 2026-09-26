<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">{{ $eyebrow }}</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                        {{ $actividadId ? ($soloLectura ? 'Ver proyecto' : 'Editar proyecto') : 'Nuevo proyecto' }}
                    </h2>
                    <p class="text-sm text-white/80">Presentación a dirección · {{ schoolCtx()->nivelNombre() }}</p>
                </div>
                <a href="{{ route($rutaListado) }}"
                   class="inline-flex items-center justify-center rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/25">
                    Volver al listado
                </a>
            </div>
        </section>

        <form novalidate class="space-y-6"
              onsubmit="event.preventDefault(); const root = this.querySelector('[data-ext-fechas]'); if (root &amp;&amp; window.Alpine) { window.Alpine.$data(root).presentar(); } return false;">
            @if ($errors->any())
                <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    <p class="font-semibold">No se pudo presentar el proyecto</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="se-card space-y-5 p-5 sm:p-6">
                <div>
                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Tipo de registro</label>
                    <input type="text" value="{{ $tipoRegistroNombre }}" readonly
                           class="form-input w-full bg-accent-50 text-neutral-700">
                </div>

                <div>
                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Actividad</label>
                    <input type="text" wire:model="nombre" maxlength="255"
                           @disabled($soloLectura)
                           class="form-input w-full" autocomplete="off">
                    @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @error('fechas') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                <div wire:ignore
                     data-ext-fechas
                     x-data="{
                        dias: {{ \Illuminate\Support\Js::from($fechasAlpine) }},
                        soloLectura: {{ $soloLectura ? 'true' : 'false' }},
                        guardando: false,
                        nuevaClave() {
                            if (window.crypto &amp;&amp; window.crypto.randomUUID) {
                                return 'd' + window.crypto.randomUUID().replace(/-/g, '');
                            }
                            return 'd' + Date.now().toString(16) + Math.random().toString(16).slice(2, 10);
                        },
                        filaVacia() {
                            const hoy = new Date();
                            const y = hoy.getFullYear();
                            const m = String(hoy.getMonth() + 1).padStart(2, '0');
                            const d = String(hoy.getDate()).padStart(2, '0');
                            return { clave: this.nuevaClave(), fecha: y + '-' + m + '-' + d, hora_inicio: '08:00', hora_fin: '12:00' };
                        },
                        agregar() {
                            if (this.soloLectura) return;
                            if (this.dias.length >= 40) {
                                window.seSwalError &amp;&amp; window.seSwalError('Puede cargar hasta 40 días.');
                                return;
                            }
                            this.dias.push(this.filaVacia());
                        },
                        quitar(clave) {
                            if (this.soloLectura) return;
                            if (this.dias.length <= 1) {
                                window.seSwalError &amp;&amp; window.seSwalError('Tiene que quedar al menos un día.');
                                return;
                            }
                            this.dias = this.dias.filter((dia) => dia.clave !== clave);
                        },
                        async presentar() {
                            if (this.soloLectura || this.guardando) return;
                            const form = this.$el.closest('form');
                            const btn = form ? form.querySelector('button[type=submit]') : null;
                            const label = form ? form.querySelector('.ext-btn-guardar-label') : null;
                            const hint = form ? form.querySelector('.ext-btn-guardando') : null;
                            const labelOriginal = label ? label.textContent : '';
                            this.guardando = true;
                            if (btn) btn.disabled = true;
                            if (label) label.textContent = 'Guardando…';
                            if (hint) hint.classList.remove('hidden');
                            try {
                                const json = JSON.stringify(this.dias);
                                if (typeof window.extProyectoGuardar === 'function') {
                                    await window.extProyectoGuardar(json);
                                } else {
                                    const host = this.$el.closest('[wire\\:id]');
                                    const id = host ? host.getAttribute('wire:id') : null;
                                    const wire = id &amp;&amp; window.Livewire ? window.Livewire.find(id) : null;
                                    if (!wire) {
                                        window.seSwalError &amp;&amp; window.seSwalError('No se pudo enviar el formulario. Recargue la página.');
                                        return;
                                    }
                                    await wire.guardar(json);
                                }
                            } catch (e) {
                            } finally {
                                this.guardando = false;
                                if (btn) btn.disabled = false;
                                if (label) label.textContent = labelOriginal;
                                if (hint) hint.classList.add('hidden');
                            }
                        }
                     }">
                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Fechas y horarios</p>
                    <p class="mb-3 text-xs text-neutral-500">Un renglón por cada día. Puede agregar más de uno.</p>
                    <div class="space-y-3">
                        <template x-for="dia in dias" :key="dia.clave">
                            <div class="grid gap-2 rounded-2xl border border-accent-200 bg-accent-50/40 p-3 sm:grid-cols-[1fr_7rem_7rem_auto]">
                                <div>
                                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Fecha</label>
                                    <input type="date" x-model="dia.fecha" :disabled="soloLectura" class="form-input w-full">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Inicio</label>
                                    <input type="time" x-model="dia.hora_inicio" step="60" :disabled="soloLectura" class="form-input w-full">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Fin</label>
                                    <input type="time" x-model="dia.hora_fin" step="60" :disabled="soloLectura" class="form-input w-full">
                                </div>
                                <div class="flex items-end" x-show="!soloLectura">
                                    <button type="button" @click="quitar(dia.clave)"
                                            class="inline-flex h-10 items-center rounded-xl bg-white px-3 text-xs font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                                        Quitar
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" x-show="!soloLectura" @click="agregar()"
                            class="mt-3 inline-flex items-center rounded-xl bg-white px-3 py-2 text-xs font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50 cursor-pointer">
                        Agregar día
                    </button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Lugar</label>
                        <input type="text" wire:model="lugar" maxlength="255" @disabled($soloLectura) class="form-input w-full" autocomplete="off">
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Horario (resumen)</label>
                        <input type="text" wire:model="horario" maxlength="255" @disabled($soloLectura)
                               class="form-input w-full" placeholder="Se completa con los horarios de cada día si lo deja vacío">
                    </div>
                </div>
            </div>

            <div class="se-card space-y-4 p-5 sm:p-6">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Grupo involucrado</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="$set('tipo_grupo', 'cursos')" @disabled($soloLectura)
                            @class([
                                'rounded-xl px-4 py-2 text-sm font-semibold transition',
                                'bg-primary-600 text-white shadow-sm' => $tipo_grupo === 'cursos',
                                'bg-white text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50' => $tipo_grupo !== 'cursos',
                            ])>
                        Cursos
                    </button>
                    <button type="button" wire:click="$set('tipo_grupo', 'alumnos')" @disabled($soloLectura)
                            @class([
                                'rounded-xl px-4 py-2 text-sm font-semibold transition',
                                'bg-primary-600 text-white shadow-sm' => $tipo_grupo === 'alumnos',
                                'bg-white text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50' => $tipo_grupo !== 'alumnos',
                            ])>
                        Alumnos
                    </button>
                </div>

                @if ($tipo_grupo === 'cursos')
                    @error('idsCursos') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @if (! $soloLectura)
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="seleccionarTodosCursos()"
                                    class="rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50 cursor-pointer">
                                Todos los cursos
                            </button>
                            <button type="button" wire:click="limpiarCursos()"
                                    class="rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-neutral-600 ring-1 ring-accent-200 hover:bg-accent-50 cursor-pointer">
                                Limpiar
                            </button>
                        </div>
                    @endif
                    <div class="grid max-h-64 gap-2 overflow-y-auto rounded-2xl border border-accent-200 p-3 sm:grid-cols-2">
                        @foreach ($cursos as $curso)
                            @php $cid = (int) $curso->Id; @endphp
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-accent-50" wire:key="curso-{{ $cid }}">
                                <input type="checkbox"
                                       value="{{ $cid }}"
                                       wire:model="idsCursos"
                                       @disabled($soloLectura)
                                       class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-neutral-800">{{ $curso->nombreParaListado() }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    @error('idsAlumnos') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    <div class="flex flex-wrap gap-2">
                        @foreach ($alumnosElegidos as $al)
                            <span class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-3 py-1 text-xs font-semibold text-neutral-800 ring-1 ring-accent-200" wire:key="al-{{ $al['id'] }}">
                                {{ $al['label'] }}
                                @if (! $soloLectura)
                                    <button type="button" wire:click="quitarAlumno({{ $al['id'] }})" class="text-neutral-500 hover:text-red-700" aria-label="Quitar">×</button>
                                @endif
                            </span>
                        @endforeach
                    </div>
                    @if (! $soloLectura)
                        <button type="button" wire:click="abrirModalAlumnos()"
                                class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50 cursor-pointer">
                            Elegir alumnos
                        </button>
                    @endif
                @endif
            </div>

            <div class="se-card space-y-4 p-5 sm:p-6" x-data="{ q: '' }">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Docentes</p>
                <div class="relative max-w-sm">
                    <input type="search" x-model="q"
                           placeholder="Filtrar docentes…" class="form-input w-full" autocomplete="off" @disabled($soloLectura)>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Docente a cargo</p>
                        @error('idsDocentesACargo') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                        <div class="max-h-56 space-y-1 overflow-y-auto rounded-2xl border border-accent-200 p-2">
                            @foreach ($docentes as $d)
                                @php $did = (int) $d['id']; @endphp
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-accent-50"
                                       wire:key="doc-ac-{{ $did }}"
                                       x-show="!q.trim() || {{ \Illuminate\Support\Js::from(mb_strtolower($d['label'].' '.$d['dni'])) }}.includes(q.toLowerCase())">
                                    <input type="checkbox" value="{{ $did }}"
                                           wire:model="idsDocentesACargo"
                                           @disabled($soloLectura)
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                    <span class="text-sm">{{ $d['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Otros docentes</p>
                        <div class="max-h-56 space-y-1 overflow-y-auto rounded-2xl border border-accent-200 p-2">
                            @foreach ($docentes as $d)
                                @php $did = (int) $d['id']; @endphp
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-accent-50"
                                       wire:key="doc-ot-{{ $did }}"
                                       x-show="!q.trim() || {{ \Illuminate\Support\Js::from(mb_strtolower($d['label'].' '.$d['dni'])) }}.includes(q.toLowerCase())">
                                    <input type="checkbox" value="{{ $did }}"
                                           wire:model="idsOtrosDocentes"
                                           @disabled($soloLectura)
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                    <span class="text-sm">{{ $d['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="se-card space-y-4 p-5 sm:p-6">
                <div>
                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Breve descripción</label>
                    <textarea wire:model="descripcion" rows="10" @disabled($soloLectura)
                              class="form-input w-full leading-relaxed"></textarea>
                    @error('descripcion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Evaluación</label>
                    <textarea wire:model="evaluacion" rows="4" @disabled($soloLectura)
                              class="form-input w-full leading-relaxed"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Presupuesto de la actividad</label>
                    <textarea wire:model="presupuesto" rows="4" maxlength="4000" @disabled($soloLectura)
                              class="form-input w-full leading-relaxed"></textarea>
                    @error('presupuesto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @if (! $soloLectura)
                <div class="relative z-20 flex flex-col items-end gap-2">
                    @if ($mensajeForm !== '' || $errors->any())
                        <div class="w-full rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                            <p class="font-semibold">{{ $mensajeForm !== '' ? $mensajeForm : 'No se pudo presentar el proyecto' }}</p>
                            @if ($errors->any())
                                <ul class="mt-1 list-disc pl-5">
                                    @foreach ($errors->all() as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <a href="{{ route($rutaListado) }}"
                           class="btn-secondary">
                            Cancelar
                        </a>
                        <button type="submit" class="btn-primary">
                            <span class="ext-btn-guardar-label">{{ $actividadId ? 'Guardar cambios' : 'Presentar a dirección' }}</span>
                        </button>
                    </div>
                    <p class="ext-btn-guardando hidden text-xs font-semibold text-primary-700">
                        Presentando el proyecto a dirección…
                    </p>
                </div>
            @endif
        </form>
    </div>

    @teleport('body')
        @if ($modalAlumnos)
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog" aria-modal="true" aria-labelledby="ext-alumnos-title">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalAlumnos"></div>
                <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                        <h3 id="ext-alumnos-title" class="text-lg font-bold text-neutral-900">Elegir alumnos</h3>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                        <input type="search" wire:model.live.debounce.300ms="filtroAlumno"
                               placeholder="Apellido, nombre o DNI…" class="form-input mb-3 w-full" autocomplete="off">
                        <div class="space-y-1">
                            @forelse ($alumnosBusqueda as $al)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-accent-50">
                                    <input type="checkbox" wire:click="toggleAlumno({{ $al['id'] }})"
                                           @checked(in_array((string) (int) $al['id'], $idsAlumnos, true))
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                    <span class="text-sm">{{ $al['label'] }}</span>
                                    @if (($al['dni'] ?? '') !== '')
                                        <span class="text-xs tabular-nums text-neutral-500">{{ $al['dni'] }}</span>
                                    @endif
                                </label>
                            @empty
                                <p class="py-6 text-center text-sm text-neutral-500">Escriba para buscar alumnos regulares del ciclo.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="shrink-0 border-t border-accent-200 bg-accent-50 px-5 py-3 text-right">
                        <button type="button" wire:click="cerrarModalAlumnos()"
                                class="btn-primary">
                            Listo
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endteleport

    @script
    <script>
        window.extProyectoGuardar = (json) => $wire.guardar(json);

        $wire.on('se-swal-exito', (event) => {
            window.seSwalExito?.(event?.mensaje ?? event?.detail?.mensaje ?? 'Guardado.');
        });
        $wire.on('se-swal-error', (event) => {
            window.seSwalError?.(event?.mensaje ?? event?.detail?.mensaje ?? 'Error.');
        });
    </script>
    @endscript
</div>
