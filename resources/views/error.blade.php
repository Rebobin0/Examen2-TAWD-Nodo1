@extends('layouts.app')

@section('titulo', 'Error')

@section('contenido')
    <div class="encabezado">
        <h1>No se pudo completar la consulta</h1>
    </div>
    <div class="aviso error">{{ $mensaje }}</div>
    <a class="btn sec" href="{{ route('dashboard') }}">Volver al inicio</a>
@endsection
