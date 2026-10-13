<div>
    <style>
        .ext-proy-form[data-ext-tipo="2"] .ext-proy-solo-completo,
        .ext-proy-form[data-ext-tipo="3"] .ext-proy-solo-completo { display: none; }
        .ext-proy-form[data-ext-tipo="1"] .ext-proy-label-reducido { display: none; }
        .ext-proy-form[data-ext-tipo="2"] .ext-proy-label-completo,
        .ext-proy-form[data-ext-tipo="3"] .ext-proy-label-completo { display: none; }
        @media (min-width: 1024px) {
            .ext-proy-form[data-ext-tipo="1"] .ext-proy-docentes-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">{{ $eyebrow }}</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                        {{ $actividadId ? ($soloLectura ? 'Ver actividad' : 'Editar actividad') : 'Nueva actividad' }}
                    </h2>
                    <p class="text-sm text-white/80">Presentación a dirección · {{ schoolCtx()->nivelNombre() }}</p>
                </div>
                <a href="{{ route($rutaListado) }}"
                   class="inline-flex items-center justify-center rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/25">
                    Volver al listado
                </a>
            </div>
        </section>

        <form novalidate class="ext-proy-form space-y-6" data-ext-tipo="{{ (int) $idTipoRegistro }}"
              onsubmit="event.preventDefault(); const root = this.querySelector('[data-ext-fechas]'); if (root &amp;&amp; window.Alpine) { window.Alpine.$data(root).presentar(); } return false;">
            @if ($errors->any())
                <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    <p class="font-semibold">No se pudo presentar la actividad</p>
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
                    @if ($soloLectura)
                        <input type="text" value="{{ $tipoRegistroNombre }}" readonly
                               class="form-input w-full bg-accent-50 text-neutral-700">
                    @else
                        <select wire:model="idTipoRegistro"
                                onchange="window.extAjustarTipoRegistro && window.extAjustarTipoRegistro(this)"
                                class="form-input w-full">
                            @foreach ($tiposRegistro as $tipo)
                                <option value="{{ (int) $tipo->id }}">{{ $tipo->nombre }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('idTipoRegistro') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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
                                    <button type="button" @click.stop.prevent="quitar(dia.clave)"
                                            class="inline-flex h-10 items-center rounded-xl bg-white px-3 text-xs font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                                        Quitar
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" x-show="!soloLectura" onclick="extAgregarDia(event, this)"
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

            <div class="se-card space-y-4 p-5 sm:p-6" data-ext-grupo data-grupo="{{ $tipo_grupo }}">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Grupo involucrado</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-ext-btn="cursos" onclick="extElegirGrupo(event, this, 'cursos')" @disabled($soloLectura)
                            @class([
                                'rounded-xl px-4 py-2 text-sm font-semibold transition',
                                'bg-primary-600 text-white shadow-sm' => $tipo_grupo === 'cursos',
                                'bg-white text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50' => $tipo_grupo !== 'cursos',
                            ])>
                        Cursos
                    </button>
                    <button type="button" data-ext-btn="alumnos" onclick="extElegirGrupo(event, this, 'alumnos')" @disabled($soloLectura)
                            @class([
                                'rounded-xl px-4 py-2 text-sm font-semibold transition',
                                'bg-primary-600 text-white shadow-sm' => $tipo_grupo === 'alumnos',
                                'bg-white text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50' => $tipo_grupo !== 'alumnos',
                            ])>
                        Alumnos
                    </button>
                </div>

                @error('idsCursos') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                @error('idsAlumnos') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                <div data-ext-panel="cursos" wire:ignore @class(['hidden' => $tipo_grupo !== 'cursos'])>
                    @php $idsCursosMarcados = array_map('strval', $idsCursos); @endphp
                    @if (! $soloLectura)
                        <div class="mb-2 flex flex-wrap gap-2">
                            <button type="button" onclick="extTodosCursos(event, this)"
                                    class="rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50 cursor-pointer">
                                Todos los cursos
                            </button>
                            <button type="button" onclick="extLimpiarCursos(event, this)"
                                    class="rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-neutral-600 ring-1 ring-accent-200 hover:bg-accent-50 cursor-pointer">
                                Limpiar
                            </button>
                        </div>
                    @endif
                    <div class="grid max-h-64 gap-2 overflow-y-auto rounded-2xl border border-accent-200 p-3 sm:grid-cols-2">
                        @foreach ($cursos as $curso)
                            @php
                                $cid = (int) $curso->Id;
                                $cursoMarcado = in_array((string) $cid, $idsCursosMarcados, true);
                            @endphp
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-accent-50"
                                   @unless ($soloLectura) onclick="extToggleCurso(event, this)" @endunless>
                                <input type="checkbox" data-ext-curso-id="{{ $cid }}"
                                       @checked($cursoMarcado)
                                       @disabled($soloLectura)
                                       class="pointer-events-none rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-neutral-800">{{ $curso->nombreParaListado() }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div data-ext-panel="alumnos" @class(['hidden' => $tipo_grupo !== 'alumnos'])>
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
                        <button type="button" onclick="extAbrirAlumnos(event, this)"
                                class="mt-2 rounded-xl bg-white px-3 py-2 text-xs font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50 cursor-pointer">
                            Elegir alumnos
                        </button>
                    @endif
                </div>
            </div>

            <div class="se-card space-y-4 p-5 sm:p-6" x-data="{ q: '' }">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Docentes</p>
                <div class="relative max-w-sm">
                    <input type="search" x-model="q"
                           placeholder="Filtrar docentes…" class="form-input w-full" autocomplete="off" @disabled($soloLectura)>
                </div>
                <div class="ext-proy-docentes-grid grid gap-4">
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
                    <div class="ext-proy-solo-completo">
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
            </div>

            <div class="se-card space-y-4 p-5 sm:p-6">
                <div>
                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">
                        <span class="ext-proy-label-completo">Breve descripción</span><span class="ext-proy-label-reducido">Descripción</span>
                    </label>
                    <textarea wire:model="descripcion" data-ext-descripcion rows="8" @disabled($soloLectura)
                              class="form-input w-full leading-relaxed"></textarea>
                    @error('descripcion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="ext-proy-solo-completo space-y-4">
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
            </div>

            @if (! $soloLectura)
                <div class="relative z-20 flex flex-col items-end gap-2">
                    @if ($mensajeForm !== '' || $errors->any())
                        <div class="w-full rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                            <p class="font-semibold">{{ $mensajeForm !== '' ? $mensajeForm : 'No se pudo presentar la actividad' }}</p>
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
                        Presentando la actividad a dirección…
                    </p>
                </div>
            @endif
        </form>
    </div>

    @teleport('body')
            <div data-ext-modal-alumnos
                 class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4 {{ $modalAlumnos ? '' : 'hidden' }}"
                 role="dialog" aria-modal="true" aria-labelledby="ext-alumnos-title">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" onclick="extCerrarAlumnos(event, this)"></div>
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
                        <button type="button" onclick="extCerrarAlumnos(event, this)"
                                class="btn-primary">
                            Listo
                        </button>
                    </div>
                </div>
            </div>
    @endteleport

    @script
    <script>
        window.extPlantillaDescripcion = @js(\App\Support\ProyectosExtracurriculares\ExtActividadesService::DESCRIPCION_PLANTILLA);
        window.extAjustarTipoRegistro = (select) => {
            const form = select.closest('form');
            if (!form) return;
            const tipo = String(select.value || '1');
            form.dataset.extTipo = tipo;
            const ta = form.querySelector('[data-ext-descripcion]');
            const plantilla = String(window.extPlantillaDescripcion || '');
            if (!ta) return;
            if (tipo === '1') {
                if (ta.value.trim() === '') {
                    ta.value = plantilla;
                    ta.dispatchEvent(new Event('input', { bubbles: true }));
                }
                return;
            }
            if (ta.value.trim() === plantilla.trim()) {
                ta.value = '';
                ta.dispatchEvent(new Event('input', { bubbles: true }));
            }
        };
        const extWireDe = (el) => {
            const host = el.closest('[wire\\:id]');
            if (!host || !window.Livewire) return null;
            return window.Livewire.find(host.getAttribute('wire:id'));
        };
        const extSyncCursos = (el) => {
            const root = el.closest('[data-ext-panel="cursos"]');
            if (!root) return;
            const ids = [...root.querySelectorAll('input[data-ext-curso-id]')]
                .filter((input) => input.checked)
                .map((input) => input.getAttribute('data-ext-curso-id'));
            const wire = extWireDe(el);
            if (wire) wire.set('idsCursos', ids, false);
        };
        window.extAgregarDia = (event, btn) => {
            event.preventDefault();
            event.stopPropagation();
            const root = btn.closest('[data-ext-fechas]');
            if (!root || !window.Alpine) return;
            const data = window.Alpine.$data(root);
            if (data && typeof data.agregar === 'function') data.agregar();
        };
        window.extElegirGrupo = (event, btn, grupo) => {
            event.preventDefault();
            event.stopPropagation();
            const box = btn.closest('[data-ext-grupo]');
            if (!box) return;
            const valor = grupo === 'cursos' ? 'cursos' : 'alumnos';
            box.dataset.grupo = valor;
            box.querySelectorAll('[data-ext-panel]').forEach((panel) => {
                panel.classList.toggle('hidden', panel.getAttribute('data-ext-panel') !== valor);
            });
            box.querySelectorAll('[data-ext-btn]').forEach((el) => {
                const activo = el.getAttribute('data-ext-btn') === valor;
                el.classList.toggle('bg-primary-600', activo);
                el.classList.toggle('text-white', activo);
                el.classList.toggle('shadow-sm', activo);
                el.classList.toggle('bg-white', !activo);
                el.classList.toggle('text-primary-700', !activo);
                el.classList.toggle('ring-1', !activo);
                el.classList.toggle('ring-accent-200', !activo);
            });
            const wire = extWireDe(btn);
            if (wire) wire.set('tipo_grupo', valor, false);
        };
        window.extToggleCurso = (event, label) => {
            event.preventDefault();
            event.stopPropagation();
            const input = label.querySelector('input[data-ext-curso-id]');
            if (!input || input.disabled) return;
            input.checked = !input.checked;
            extSyncCursos(label);
        };
        window.extTodosCursos = (event, btn) => {
            event.preventDefault();
            event.stopPropagation();
            const root = btn.closest('[data-ext-panel="cursos"]');
            if (!root) return;
            root.querySelectorAll('input[data-ext-curso-id]').forEach((input) => { input.checked = true; });
            extSyncCursos(btn);
        };
        window.extAbrirAlumnos = (event, btn) => {
            event.preventDefault();
            event.stopPropagation();
            document.querySelectorAll('[data-ext-modal-alumnos]').forEach((el) => el.classList.remove('hidden'));
            const wire = extWireDe(btn);
            if (wire) wire.abrirModalAlumnos();
        };
        window.extCerrarAlumnos = (event) => {
            event.preventDefault();
            event.stopPropagation();
            document.querySelectorAll('[data-ext-modal-alumnos]').forEach((el) => el.classList.add('hidden'));
            const ancla = document.querySelector('[data-ext-grupo]');
            const wire = ancla ? extWireDe(ancla) : null;
            if (wire) wire.cerrarModalAlumnos();
        };
        window.extLimpiarCursos = (event, btn) => {
            event.preventDefault();
            event.stopPropagation();
            const root = btn.closest('[data-ext-panel="cursos"]');
            if (!root) return;
            root.querySelectorAll('input[data-ext-curso-id]').forEach((input) => { input.checked = false; });
            extSyncCursos(btn);
        };
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
