<div class="se-page max-w-4xl mx-auto space-y-4">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="se-eyebrow">Matrícula web</p>
                <h2 class="text-lg font-bold sm:text-xl">{{ $def['label'] ?? 'Documento' }}</h2>
            </div>
            <a href="{{ route('alumnos.actualizacion-datos') }}"
               class="inline-flex items-center gap-1 rounded-xl border border-white/25 bg-white/10 px-3 py-2 text-sm font-semibold text-white hover:bg-white/20">
                Volver al formulario
            </a>
        </div>
    </section>

    @if ($textoCompromiso)
        <p class="text-xs text-neutral-600 leading-relaxed px-1">{{ $textoCompromiso }}</p>
    @endif

    @if ($pdfUrl)
        <style>
            .doc-aceptacion-pdf-frame {
                display: block;
                width: 100%;
                min-height: 32rem;
                height: calc(100dvh - 13rem);
                border-radius: 0.75rem;
                border: 1px solid #C1D7DA;
                background: #fff;
            }
            @media (min-width: 768px) {
                .doc-aceptacion-pdf-frame {
                    height: calc(100dvh - 8.5rem);
                    min-height: 36rem;
                }
            }
            .doc-aceptacion-pdf-frame--con-compromiso {
                height: calc(100dvh - 15.5rem);
            }
            @media (min-width: 768px) {
                .doc-aceptacion-pdf-frame--con-compromiso {
                    height: calc(100dvh - 10.5rem);
                }
            }
        </style>
        <div class="se-card overflow-hidden p-2">
            <iframe src="{{ $pdfUrl }}#toolbar=1"
                    title="Documento PDF"
                    class="doc-aceptacion-pdf-frame {{ $textoCompromiso ? 'doc-aceptacion-pdf-frame--con-compromiso' : '' }}"></iframe>
        </div>
    @else
        <div class="se-soft-card text-sm text-amber-800">No se pudo cargar el documento.</div>
    @endif

    <div class="pb-2">
        <button type="button"
                wire:click="aceptar"
                wire:loading.attr="disabled"
                class="w-full rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="aceptar">Acepto</span>
            <span wire:loading wire:target="aceptar">Procesando…</span>
        </button>
    </div>
</div>
