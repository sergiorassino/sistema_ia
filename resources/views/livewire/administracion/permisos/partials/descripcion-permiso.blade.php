{{-- Descripción de un permiso del catálogo; aviso destacado si está reservado al administrador. --}}
@php
    $ordenPerm = (int) ($orden ?? 0);
    $textoPerm = trim((string) ($descripcion ?? ''));
    $reservado = \App\Support\PermisosIaCatalog::esReservadoAdministrador($ordenPerm);
    $aviso = \App\Support\PermisosIaCatalog::AVISO_NO_OTORGAR_ADMIN;
    $base = $reservado
        ? trim(str_replace($aviso, '', $textoPerm), " \t.")
        : $textoPerm;
    $titulo = $titulo ?? false;
@endphp
@if ($titulo)
    <p class="text-sm font-semibold text-neutral-900">{{ $base !== '' ? $base : $textoPerm }}</p>
@else
    <span class="mt-0.5 block text-xs text-neutral-600">{{ $base !== '' ? $base : $textoPerm }}</span>
@endif
@if ($reservado)
    <span class="mt-1 inline-flex max-w-full rounded-md bg-amber-200 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-950">
        {{ $aviso }}
    </span>
@endif
