@extends($layout ?? 'layouts.app')

@section('pageTitle', 'Notificaciones push')

@section('content')
    @include('push._suscribir-panel')
@endsection
