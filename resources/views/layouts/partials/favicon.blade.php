{{-- Favicon de solapa: mismo criterio que SILAVET (círculo blanco + marca), letras SE en gris oscuro. --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ \App\Support\Pwa\PwaIdentity::iconAbsoluto('favicon-32.png') }}">
@include('layouts.partials.pwa')
