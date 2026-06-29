@props([
    'action',
    'fields' => [],
    'buttonClass' => 'inline-flex max-w-full flex-wrap items-center justify-end gap-1.5 whitespace-normal rounded-xl border border-accent-200 bg-white px-3 py-2 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-300 hover:bg-accent-50',
])

<form method="POST"
      action="{{ $action }}"
      onsubmit="event.preventDefault(); window.abrirPdfPostFromForm(this);"
      {{ $attributes->class(['inline']) }}>
    @csrf
    @foreach ($fields as $name => $value)
        @if (is_array($value))
            @foreach ($value as $item)
                <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach
    <button type="submit" class="{{ $buttonClass }}">
        {{ $slot }}
    </button>
</form>
