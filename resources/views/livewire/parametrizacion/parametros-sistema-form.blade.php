<div class="se-page max-w-4xl">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
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
                <p class="se-eyebrow">Configuración</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Parámetros del sistema</h2>
                <p class="text-sm text-white/80">
                    Nivel: <span class="font-semibold">{{ $nivelNombre !== '' ? $nivelNombre : '—' }}</span>
                    · {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                    Volver
                </a>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,logo"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save,logo">Guardar</span>
                    <span wire:loading wire:target="save">Guardando…</span>
                    <span wire:loading wire:target="logo">Subiendo logo…</span>
                </button>
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="border-b border-accent-200 bg-white">
            <nav class="se-form-tabs">
                <button type="button"
                        wire:click="setTab('institucion')"
                        @class(['se-form-tab', 'se-form-tab-active' => $activeTab === 'institucion', 'se-form-tab-idle' => $activeTab !== 'institucion'])>
                    DATOS DE LA INSTITUCIÓN
                </button>
                <button type="button"
                        wire:click="setTab('parametros')"
                        @class(['se-form-tab', 'se-form-tab-active' => $activeTab === 'parametros', 'se-form-tab-idle' => $activeTab !== 'parametros'])>
                    PARÁMETROS
                </button>
            </nav>
        </div>

        @if ($activeTab === 'institucion')
        <div class="p-6 sm:p-7" wire:key="param-tab-institucion">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="form-label">Institución</label>
                <input wire:model="insti" type="text" maxlength="120" class="form-input mt-1.5 @error('insti') border-red-400 @enderror">
                @error('insti') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">CUE</label>
                <input wire:model="cue" type="text" maxlength="30" class="form-input mt-1.5 font-mono @error('cue') border-red-400 @enderror">
                @error('cue') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">EE</label>
                <input wire:model="ee" type="text" maxlength="30" class="form-input mt-1.5 font-mono @error('ee') border-red-400 @enderror">
                @error('ee') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">CUIT</label>
                <input wire:model="cuit" type="text" maxlength="20" class="form-input mt-1.5 font-mono @error('cuit') border-red-400 @enderror">
                @error('cuit') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Categoría</label>
                <input wire:model="categoria" type="text" maxlength="80" class="form-input mt-1.5 @error('categoria') border-red-400 @enderror">
                @error('categoria') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-8 border-t border-accent-200 pt-6">
            <p class="se-section-title mb-1">Facturación AFIP</p>
            <p class="mb-4 text-xs text-neutral-500">
                Datos del emisor para comprobantes electrónicos. La condición frente al IVA del destinatario se aplica por defecto en cada factura.
            </p>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label class="form-label">Punto de venta</label>
                    <input wire:model="ptoVta" type="number" min="1" max="9999" step="1"
                           class="form-input mt-1.5 font-mono @error('ptoVta') border-red-400 @enderror"
                           placeholder="Ej. 5">
                    @error('ptoVta') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Ingresos brutos</label>
                    <input wire:model="ingresosBrutos" type="text" maxlength="40"
                           class="form-input mt-1.5 @error('ingresosBrutos') border-red-400 @enderror"
                           placeholder="Ej. Exento">
                    @error('ingresosBrutos') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Condición frente al IVA (institución)</label>
                    <input wire:model="condIvaInst" type="text" maxlength="40"
                           class="form-input mt-1.5 @error('condIvaInst') border-red-400 @enderror"
                           placeholder="Ej. Responsable Monotributo">
                    @error('condIvaInst') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Aporte estatal</label>
                    <input wire:model="aporteEstatal" type="text" maxlength="10"
                           class="form-input mt-1.5 font-mono @error('aporteEstatal') border-red-400 @enderror"
                           placeholder="Ej. 0,00">
                    @error('aporteEstatal') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Condición frente a IVA del destinatario</label>
                    <input wire:model="condicionIva" type="text" maxlength="80"
                           class="form-input mt-1.5 @error('condicionIva') border-red-400 @enderror"
                           placeholder="Ej. IVA Consumidor Final">
                    @error('condicionIva') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Inicio de actividades</label>
                    <input wire:model="fechaInicioAct" type="date"
                           class="form-input mt-1.5 @error('fechaInicioAct') border-red-400 @enderror">
                    @error('fechaInicioAct') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            @if ($facturacionAfipHabilitada)
                <div class="mt-6 border-t border-accent-200 pt-5">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-primary-800 mb-1">
                        Certificados digitales AFIP
                    </p>
                    <p class="mb-4 text-xs text-neutral-500">
                        Los archivos (.key y .crt) deben estar en el servidor, en
                        <span class="font-mono">afipSE/cert/<em>carpeta</em>/</span>.
                        Si el colegio usa certificados distintos al de desarrollo, indique aquí la carpeta y los nombres de archivo de Ramallo.
                    </p>
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label class="form-label">Carpeta en afipSE/cert</label>
                            <input wire:model="afipCertCarpeta" type="text" maxlength="40"
                                   class="form-input mt-1.5 font-mono @error('afipCertCarpeta') border-red-400 @enderror"
                                   placeholder="Ej. 3">
                            @error('afipCertCarpeta') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Archivo clave privada (.key)</label>
                            <input wire:model="afipCertKey" type="text" maxlength="120"
                                   class="form-input mt-1.5 font-mono @error('afipCertKey') border-red-400 @enderror"
                                   placeholder="Ej. privada_prod.key">
                            @error('afipCertKey') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label">Archivo certificado (.crt)</label>
                            <input wire:model="afipCertCrt" type="text" maxlength="120"
                                   class="form-input mt-1.5 font-mono @error('afipCertCrt') border-red-400 @enderror"
                                   placeholder="Ej. instituto_ramallo.crt">
                            @error('afipCertCrt') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            @endif
        </div>

        @if ($puedeEditarCamposSiro)
            <div class="mt-8 border-t border-accent-200 pt-6">
                <p class="se-section-title mb-1">SIRO — medios de pago por nivel</p>
                <p class="mb-4 text-xs text-neutral-500">
                    Parámetros del nivel activo ({{ $nivelNombre !== '' ? $nivelNombre : '—' }}):
                    prefijo CPE, cuenta recaudadora y mensaje en cupón / subida de base de deuda.
                    Cada nivel puede tener valores distintos.
                </p>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label class="form-label">Prefijo CPE (2 dígitos)</label>
                        <input wire:model="siroPrefijoCPE" type="text" maxlength="2" inputmode="numeric"
                               class="form-input mt-1.5 font-mono @error('siroPrefijoCPE') border-red-400 @enderror"
                               placeholder="Ej. 00, 09">
                        @error('siroPrefijoCPE') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Cuenta recaudadora SIRO</label>
                        <input wire:model="siroIdentCuenta" type="text" maxlength="20" inputmode="numeric"
                               class="form-input mt-1.5 font-mono @error('siroIdentCuenta') border-red-400 @enderror"
                               placeholder="10 dígitos del convenio">
                        @error('siroIdentCuenta') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="form-label">Mensaje en ticket / pantalla SIRO</label>
                        <input wire:model="siroMje" type="text" maxlength="40"
                               class="form-input mt-1.5 @error('siroMje') border-red-400 @enderror"
                               placeholder="Texto institucional en cupón (máx. 15 en archivo)">
                        @error('siroMje') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-8 border-t border-accent-200 pt-6">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="form-label">Dirección</label>
                <input wire:model="direccion" type="text" maxlength="150" class="form-input mt-1.5 @error('direccion') border-red-400 @enderror">
                @error('direccion') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Localidad</label>
                <input wire:model="localidad" type="text" maxlength="80" class="form-input mt-1.5 @error('localidad') border-red-400 @enderror">
                @error('localidad') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Departamento</label>
                <input wire:model="departamento" type="text" maxlength="80" class="form-input mt-1.5 @error('departamento') border-red-400 @enderror">
                @error('departamento') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Provincia</label>
                <input wire:model="provincia" type="text" maxlength="80" class="form-input mt-1.5 @error('provincia') border-red-400 @enderror">
                @error('provincia') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Teléfono</label>
                <input wire:model="telefono" type="text" maxlength="50" class="form-input mt-1.5 @error('telefono') border-red-400 @enderror">
                @error('telefono') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Mail</label>
                <input wire:model="mail" type="email" maxlength="120" class="form-input mt-1.5 @error('mail') border-red-400 @enderror">
                @error('mail') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Rep. legal</label>
                <input wire:model="replegal" type="text" maxlength="120" class="form-input mt-1.5 @error('replegal') border-red-400 @enderror">
                @error('replegal') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            </div>
        </div>

        <div class="mt-8 border-t border-accent-200 pt-6">
            <p class="se-section-title mb-4">Logo (JPG/JPEG/PNG por nivel)</p>

            <div class="grid grid-cols-1 items-start gap-6 md:grid-cols-2"
                 wire:key="logo-upload-section"
                 x-data="{
                     localPreview: null,
                     revokeLocalPreview() {
                         if (this.localPreview) {
                             URL.revokeObjectURL(this.localPreview);
                             this.localPreview = null;
                         }
                     },
                     onLogoFileChange(event) {
                         this.revokeLocalPreview();
                         const file = event.target.files?.[0];
                         if (file && /^image\/(jpe?g|png)$/i.test(file.type)) {
                             this.localPreview = URL.createObjectURL(file);
                         }
                     }
                 }"
                 x-on:parametros-logo-guardado.window="revokeLocalPreview()">
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Subir logo</label>
                        <input wire:model="logo" type="file" accept="image/jpeg,image/png"
                               class="form-input mt-1.5 @error('logo') border-red-400 @enderror"
                               x-on:change="onLogoFileChange($event)"
                               x-on:livewire-upload-error.window="
                                   if ($event.detail?.property === 'logo') {
                                       revokeLocalPreview();
                                       $wire.onLogoUploadFailed();
                                   }
                               ">
                        <p wire:loading wire:target="logo" class="mt-1 text-xs font-medium text-primary-700">
                            Subiendo archivo… espere a que termine antes de pulsar Guardar.
                        </p>
                        @error('logo') <p class="form-error">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-neutral-500">JPG/JPEG/PNG · máx. 2&nbsp;MB · se guarda para el nivel activo ({{ $nivelNombre !== '' ? $nivelNombre : '—' }}).</p>
                    </div>

                    <label class="inline-flex cursor-pointer items-center gap-2">
                        <input type="checkbox" wire:model.live="removeLogo"
                               class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                               x-on:change="if ($event.target.checked) revokeLocalPreview()">
                        <span class="text-xs text-neutral-600">Quitar logo actual</span>
                    </label>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Vista previa</p>
                    <div @class([
                        'flex min-h-[120px] items-center justify-center rounded-2xl border border-accent-200 bg-white p-4',
                        'se-logo-preview--emblema' => schoolLogoEsEmblema(),
                    ])>
                        <img x-show="localPreview" x-bind:src="localPreview" alt="Logo"
                             @class([
                                 'object-contain',
                                 'h-28 w-28' => schoolLogoEsEmblema(),
                                 'max-h-28' => ! schoolLogoEsEmblema(),
                             ])>
                        @if ($logoPreviewUrl)
                            <img x-show="! localPreview && ! $wire.removeLogo" src="{{ $logoPreviewUrl }}" alt="Logo"
                                 @class([
                                     'object-contain',
                                     'h-28 w-28' => schoolLogoEsEmblema(),
                                     'max-h-28' => ! schoolLogoEsEmblema(),
                                 ])>
                        @endif
                        <span x-show="! localPreview && ($wire.removeLogo || @js(! $logoPreviewUrl))"
                              class="text-xs text-neutral-400">Sin logo</span>
                    </div>
                </div>
            </div>
        </div>
        </div>
        @else
        <div class="space-y-8 p-6 sm:p-7" wire:key="param-tab-parametros">
            <div>
                <p class="se-section-title mb-1">Ciclos lectivos</p>
                <p class="mb-4 text-xs text-neutral-500">
                    Ciclo lectivo visible en autogestión y restricciones del Menú de Docentes.
                </p>
                <div class="max-w-md">
                    <label class="form-label">Año para la plataforma del alumno</label>
                    <livewire:components.terlec-selector wire:model="idTerlecVerNotas" input-id="param-idTerlecVerNotas" />
                    <p class="mt-1 text-xs text-neutral-500">Corresponde a <span class="font-mono">idTerlecVerNotas</span> (autogestión familia y docente).</p>
                    @error('idTerlecVerNotas') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="border-t border-accent-200 pt-6">
                <p class="se-section-title mb-4">Calificaciones — docentes</p>
                <div class="space-y-5">
                    <div class="space-y-3 rounded-2xl border border-accent-200 bg-accent-50/40 px-4 py-3">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" wire:model.live="cargaNotasOff" class="mt-1 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                            <span>
                                <span class="block text-sm font-semibold text-neutral-800">Bloquear carga de notas a docentes</span>
                                <span class="mt-0.5 block text-xs text-neutral-500">Pueden seguir entrando a consultar.</span>
                            </span>
                        </label>
                        @error('cargaNotasOff') <p class="form-error">{{ $message }}</p> @enderror

                        <div class="space-y-1.5 border-t border-accent-200/80 pt-3">
                            <label class="form-label">Mensaje ante el bloqueo</label>
                            <textarea wire:model="notasOffMensaje" rows="3" maxlength="500"
                                      class="form-input @error('notasOffMensaje') border-red-400 @enderror"
                                      placeholder="La carga de calificaciones no está habilitada en este momento."></textarea>
                            <p class="text-xs text-neutral-500">Para salto de línea use <span class="font-mono">&lt;br&gt;</span>.</p>
                            @error('notasOffMensaje') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-accent-200 pt-6">
                <p class="se-section-title mb-4">Calificaciones — alumnos</p>
                <div class="space-y-5">
                    <div class="space-y-3 rounded-2xl border border-accent-200 bg-accent-50/40 px-4 py-3">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" wire:model.live="verNotasOff" class="mt-0.5 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                            <span class="block text-sm font-semibold text-neutral-800">Bloqueo de visualización de notas a los alumnos</span>
                        </label>
                        @error('verNotasOff') <p class="form-error">{{ $message }}</p> @enderror

                        <div class="space-y-1.5 border-t border-accent-200/80 pt-3">
                            <label class="form-label">Mensaje ante el bloqueo</label>
                            <textarea wire:model="verOffMensaje" rows="3" maxlength="500"
                                      class="form-input @error('verOffMensaje') border-red-400 @enderror"></textarea>
                            <p class="text-xs text-neutral-500">Para salto de línea use <span class="font-mono">&lt;br&gt;</span>.</p>
                            @error('verOffMensaje') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="space-y-3 rounded-2xl border border-accent-200 bg-accent-50/40 px-4 py-3">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" wire:model.live="verBimesOff" class="mt-0.5 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                            <span class="block text-sm font-semibold text-neutral-800">Bloqueo de visualización por bimestre</span>
                        </label>
                        @error('verBimesOff') <p class="form-error">{{ $message }}</p> @enderror

                        <div class="space-y-1.5 border-t border-accent-200/80 pt-3">
                            <label class="form-label">Mensaje ante el bloqueo</label>
                            <textarea wire:model="bimesOffMensaje" rows="3" maxlength="500"
                                      class="form-input @error('bimesOffMensaje') border-red-400 @enderror"></textarea>
                            <p class="text-xs text-neutral-500">Para salto de línea use <span class="font-mono">&lt;br&gt;</span>.</p>
                            @error('bimesOffMensaje') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-accent-200 bg-accent-50/40 px-4 py-3">
                        <input type="checkbox" wire:model.live="imprBoleOff" class="mt-0.5 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                        <span class="block text-sm font-semibold text-neutral-800">Bloquear impresión de boletines</span>
                    </label>
                    @error('imprBoleOff') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
