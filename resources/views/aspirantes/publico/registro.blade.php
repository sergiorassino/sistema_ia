@extends('layouts.aspirantes-publico', ['pageTitle' => $instancia->titulo ?: 'Registro de aspirante'])

@section('content')
    <livewire:aspirantes.registro-aspirante-form :token="$token" />
@endsection
