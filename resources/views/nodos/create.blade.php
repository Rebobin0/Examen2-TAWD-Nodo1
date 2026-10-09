@extends('layouts.app')

@section('titulo', 'Nuevo nodo')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Nueva sucursal o cajero</h1>
            <p>Al guardar se genera el API Key con el que el nodo se conectará al Banco Central.</p>
        </div>
    </div>

    <form class="panel" method="POST" action="{{ route('nodos.store') }}">
        @csrf
        <div class="campos">
            <div class="campo">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" required>
                    <option value="sucursal" @selected(old('tipo', $tipo) === 'sucursal')>Sucursal</option>
                    <option value="cajero" @selected(old('tipo', $tipo) === 'cajero')>Cajero automático</option>
                </select>
            </div>
            <div class="campo">
                <label for="nombre">Nombre</label>
                <input id="nombre" type="text" name="nombre" value="{{ old('nombre') }}" maxlength="80" required placeholder="Sucursal Centro">
            </div>
            <div class="campo">
                <label for="responsable">Responsable</label>
                <input id="responsable" type="text" name="responsable" value="{{ old('responsable') }}" maxlength="80" placeholder="Opcional">
            </div>
            <div class="campo">
                <label for="efectivo">Efectivo inicial ($)</label>
                <input id="efectivo" type="number" name="efectivo" value="{{ old('efectivo', '0') }}" min="0" step="0.01" required>
                <div class="ayuda">Dinero físico con el que arranca el nodo.</div>
            </div>
        </div>
        <div class="pie-form">
            <button class="btn" type="submit">Crear y generar API Key</button>
            <a class="btn sec" href="{{ route('nodos.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
