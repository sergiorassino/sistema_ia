@php
    $uploadProps = [
        \App\Support\MatriculaWeb\MatriculaWebDocumentos::COMPROMISO => 'archivoCompromiso',
        \App\Support\MatriculaWeb\MatriculaWebDocumentos::AEC => 'archivoAec',
        \App\Support\MatriculaWeb\MatriculaWebDocumentos::NORMAS => 'archivoNormas',
        \App\Support\MatriculaWeb\MatriculaWebDocumentos::TRASLADO => 'archivoTraslado',
    ];
    $quitarProps = [
        \App\Support\MatriculaWeb\MatriculaWebDocumentos::COMPROMISO => 'quitarCompromiso',
        \App\Support\MatriculaWeb\MatriculaWebDocumentos::AEC => 'quitarAec',
        \App\Support\MatriculaWeb\MatriculaWebDocumentos::NORMAS => 'quitarNormas',
        \App\Support\MatriculaWeb\MatriculaWebDocumentos::TRASLADO => 'quitarTraslado',
    ];
@endphp

<div class="se-page max-w-4xl">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="se-soft-card mb-4 flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Matrícula web</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Documentos de aceptación</h2>
                <p class="text-sm text-white/80">
                    Nivel activo:
                    <span class="font-semibold">{{ $nivelNombre !== '' ? $nivelNombre : '—' }}</span>
                    · Cambie el nivel en el selector del menú lateral si debe cargar otro.
                </p>
                <p class="text-xs text-white/70 max-w-xl">
                    Cada nivel tiene sus cuatro PDF. El nombre de cada archivo se guarda en
                    <span class="font-mono">ento.documAcept1</span> … <span class="font-mono">documAcept4</span>
                    (puede cambiar cada año lectivo). Los archivos quedan en el repositorio del sistema por nivel.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                    Volver
                </a>
                <button type="button" wire:click="save" wire:loading.attr="disabled"
                        wire:target="save,archivoCompromiso,archivoAec,archivoNormas,archivoTraslado"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save,archivoCompromiso,archivoAec,archivoNormas,archivoTraslado">Guardar</span>
                    <span wire:loading wire:target="save">Guardando…</span>
                    <span wire:loading wire:target="archivoCompromiso,archivoAec,archivoNormas,archivoTraslado">Subiendo PDF…</span>
                </button>
            </div>
        </div>
    </section>

    <div class="space-y-4">
        @foreach ($definiciones as $clave => $def)
            @php
                $estado = $estadoActual[$clave] ?? ['nombre' => null, 'path' => null, 'existe' => false];
                $propUpload = $uploadProps[$clave];
                $propQuitar = $quitarProps[$clave];
                $nombreBd = $estado['nombre'] ?? null;
                $archivoEnDisco = (bool) ($estado['existe'] ?? false);
                $colDocum = $def['docum_column'];
            @endphp
            <section class="se-card overflow-hidden p-5 sm:p-6" wire:key="doc-{{ $clave }}">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-jet-900">{{ $def['label'] }}</h3>
                        <p class="mt-1 text-xs text-neutral-500">
                            <span class="font-mono">ento.{{ $colDocum }}</span>
                            · matrícula <span class="font-mono">{{ $def['acept_matricula'] }}</span>
                        </p>
                    </div>
                    @if ($archivoEnDisco)
                        <a href="{{ route('matricula-web.documentos.archivo', ['tipo' => $clave]) }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-accent-200 bg-white px-3 py-2 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Ver PDF actual
                        </a>
                    @endif
                </div>

                @if ($nombreBd && $archivoEnDisco)
                    <p class="mt-3 text-sm text-neutral-700">
                        Archivo vigente (<span class="font-mono">ento.{{ $colDocum }}</span>):
                        <span class="font-medium">{{ $nombreBd }}</span>
                    </p>
                @elseif ($nombreBd)
                    <p class="mt-3 text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                        En base de datos figura «{{ $nombreBd }}», pero el PDF no está en el repositorio de este servidor.
                        Suba el archivo nuevamente o copie el PDF al directorio del nivel.
                    </p>
                @else
                    <p class="mt-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                        Sin documento para este nivel. Las familias no podrán aceptar «{{ $def['titulo_corto'] }}» hasta que suba el PDF.
                    </p>
                @endif

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="form-label">Subir PDF (reemplaza el anterior)</label>
                        <input wire:model="{{ $propUpload }}" type="file" accept="application/pdf,.pdf"
                               class="form-input mt-1.5 @error($propUpload) border-red-400 @enderror">
                        <p wire:loading wire:target="{{ $propUpload }}" class="mt-1 text-xs font-medium text-primary-700">
                            Subiendo archivo…
                        </p>
                        @error($propUpload)
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-neutral-500">PDF · máx. 15&nbsp;MB</p>
                    </div>

                    <div class="flex items-end">
                        @if ($nombreBd)
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-accent-200 bg-accent-50 px-3 py-2.5">
                                <input type="checkbox" wire:model.live="{{ $propQuitar }}"
                                       class="rounded border-accent-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-xs font-medium text-neutral-700">Quitar documento de este nivel</span>
                            </label>
                        @endif
                    </div>
                </div>
            </section>
        @endforeach
    </div>
</div>
