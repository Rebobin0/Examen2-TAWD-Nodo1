@extends('layouts.app')

@section('titulo', 'Editar '.$nodo['nombre'])

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Editar {{ $nodo['tipo'] === 'sucursal' ? 'sucursal' : 'cajero' }}</h1>
            <p>{{ $nodo['nombre'] }} · API Key <span class="mono">{{ $nodo['api_key_prefijo'] }}…</span></p>
        </div>
    </div>

    <form class="panel" method="POST" action="{{ route('nodos.update', $nodo['id']) }}">
        @csrf
        @method('PUT')
        <div class="campos">
            <div class="campo">
                <label for="nombre">Nombre</label>
                <input id="nombre" type="text" name="nombre" value="{{ old('nombre', $nodo['nombre']) }}" maxlength="80" required>
            </div>
            <div class="campo">
                <label for="responsable">Responsable</label>
                <input id="responsable" type="text" name="responsable" value="{{ old('responsable', $nodo['responsable']) }}" maxlength="80" placeholder="Sin asignar">
            </div>
            <div class="campo">
                <label for="efectivo">Efectivo disponible ($)</label>
                <input id="efectivo" type="number" name="efectivo" value="{{ old('efectivo', $nodo['efectivo_disponible']) }}" min="0" step="0.01" required>
                <div class="ayuda">Efectivo real en el nodo. Los retiros y depósitos lo actualizan solos.</div>
            </div>
            <div class="campo">
                <label for="estado">Estado</label>
                <select id="estado" name="estado" required>
                    <option value="activo" @selected(old('estado', $nodo['estado']) === 'activo')>Activo</option>
                    <option value="inactivo" @selected(old('estado', $nodo['estado']) === 'inactivo')>Inactivo (no puede operar)</option>
                </select>
            </div>
        </div>
        <div class="pie-form">
            <button class="btn" type="submit">Guardar cambios</button>
            <a class="btn sec" href="{{ route('nodos.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
