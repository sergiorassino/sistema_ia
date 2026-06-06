@props([
    'action',
    'matricula',
    'fields' => [],
    'buttonClass' => 'inline-flex max-w-full flex-wrap items-center justify-end gap-1.5 whitespace-normal rounded-xl border border-accent-200 bg-white px-3 py-2 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-300 hover:bg-accent-50',
])

<x-pdf-post :action="$action" :fields="array_merge(['matricula' => (int) $matricula], $fields)" :button-class="$buttonClass" {{ $attributes }}>
    {{ $slot }}
</x-pdf-post>
