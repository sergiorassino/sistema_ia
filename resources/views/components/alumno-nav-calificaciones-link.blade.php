@props([
    'url',
    'titulo',
    'bloqueada' => false,
    'mensaje' => '',
])

<a href="{{ $url }}"
   @if ($bloqueada)
       @click.prevent="window.seSwalAviso(@js($mensaje), @js(\App\Support\EntoVerNotasOff::TITULO_AVISO_CONSULTA))"
   @else
       target="_blank"
       rel="noopener noreferrer"
   @endif
   {{ $attributes->merge(['class' => 'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors']) }}
   title="{{ $titulo }}{{ $bloqueada ? '' : ' (se abre en una nueva pestaña)' }}">
    {{ $slot }}
    <span x-show="!sidebarCollapsed" x-cloak class="truncate">{{ $titulo }}</span>
</a>
