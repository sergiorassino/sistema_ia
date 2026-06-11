@props([
    'wireModel' => 'formTexto',
    'label' => 'Descripción',
    'minHeight' => '12rem',
    'value' => '',
])

<div>
    <label class="form-label">{{ $label }}</label>

    {{-- wire:ignore completo: si Livewire morfea el contenteditable, borra el HTML cargado por Alpine. --}}
    <div class="se-html-editor mt-1.5"
         wire:ignore
         x-data="seHtmlEditor({
             wireModel: @js($wireModel),
             initialHtml: @js($value),
         })"
         x-on:se-html-editor-reset.window="resetEditor($event.detail?.html ?? '')"
         x-init="init()">
        <div class="se-html-editor__toolbar flex flex-wrap items-center gap-1 rounded-t-xl border border-b-0 border-accent-200 bg-accent-50 px-2 py-1.5">
            <select class="se-html-editor__select form-select !w-auto !py-1 !text-xs"
                    title="Tipo de letra"
                    x-on:change="setFontFamily($event.target.value); $event.target.value = ''">
                <option value="">Fuente</option>
                <option value="Arial, sans-serif">Arial</option>
                <option value="Georgia, serif">Georgia</option>
                <option value="'Times New Roman', serif">Times New Roman</option>
                <option value="Verdana, sans-serif">Verdana</option>
                <option value="Tahoma, sans-serif">Tahoma</option>
            </select>

            <select class="se-html-editor__select form-select !w-auto !py-1 !text-xs"
                    title="Tamaño"
                    x-on:change="setFontSize($event.target.value); $event.target.value = ''">
                <option value="">Tamaño</option>
                <option value="2">Pequeño</option>
                <option value="3">Normal</option>
                <option value="4">Mediano</option>
                <option value="5">Grande</option>
                <option value="6">Muy grande</option>
            </select>

            <span class="mx-0.5 h-5 w-px bg-accent-300" aria-hidden="true"></span>

            <button type="button" class="se-html-editor__btn" title="Negrita" x-on:click.prevent="cmd('bold')"><strong>B</strong></button>
            <button type="button" class="se-html-editor__btn" title="Cursiva" x-on:click.prevent="cmd('italic')"><em>I</em></button>
            <button type="button" class="se-html-editor__btn" title="Subrayado" x-on:click.prevent="cmd('underline')"><u>U</u></button>

            <span class="mx-0.5 h-5 w-px bg-accent-300" aria-hidden="true"></span>

            <button type="button" class="se-html-editor__btn" title="Viñetas" x-on:click.prevent="cmd('insertUnorderedList')">• Lista</button>
            <button type="button" class="se-html-editor__btn" title="Lista numerada" x-on:click.prevent="cmd('insertOrderedList')">1. Lista</button>

            <span class="mx-0.5 h-5 w-px bg-accent-300" aria-hidden="true"></span>

            <button type="button" class="se-html-editor__btn" title="Enlace" x-on:click.prevent="insertLink()">Enlace</button>
            <button type="button" class="se-html-editor__btn" title="Quitar enlace" x-on:click.prevent="cmd('unlink')">Sin enlace</button>
        </div>

        <div x-ref="editor"
             contenteditable="true"
             class="se-html-editor__area form-input rounded-t-none border-t-0 leading-relaxed"
             style="min-height: {{ $minHeight }}"
             x-on:input="sync()"
             x-on:blur="sync()"
             x-on:paste="onPaste($event)"></div>
    </div>
</div>
