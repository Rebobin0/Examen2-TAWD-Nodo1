@extends('layouts.app')

@section('titulo', 'Historial')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Historial global de transacciones</h1>
            <p>Todas las operaciones de la red. Filtra por sucursal o cajero.</p>
        </div>
        <div class="acciones">
            <a class="btn sec" href="{{ route('transacciones.csv', request()->except('pagina')) }}">Descargar CSV</a>
        </div>
    </div>

    <form class="panel" method="GET" action="{{ route('transacciones.index') }}">
        <div class="campos">
            <div class="campo">
                <label for="nodo">Sucursal o cajero</label>
                <select id="nodo" name="nodo">
                    <option value="">Todos</option>
                    @foreach ($nodos->groupBy('tipo') as $tipo => $grupo)
                        <optgroup label="{{ $tipo === 'sucursal' ? 'Sucursales' : 'Cajeros' }}">
                            @foreach ($grupo as $nodo)
                                <option value="{{ $nodo['id'] }}" @selected($filtros['nodo'] === $nodo['id'])>{{ $nodo['nombre'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="campo">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo">
                    <option value="">Todos</option>
                    @foreach (['retiro' => 'Retiro', 'deposito' => 'Depósito', 'transferencia' => 'Transferencia'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected($filtros['tipo'] === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="campo">
                <label for="cuenta">Número de cuenta</label>
                <input id="cuenta" type="text" name="cuenta" value="{{ $filtros['cuenta'] }}" maxlength="20">
            </div>
            <div class="campo">
                <label for="desde">Desde</label>
                <input id="desde" type="date" name="desde" value="{{ $filtros['desde'] }}">
            </div>
            <div class="campo">
                <label for="hasta">Hasta</label>
                <input id="hasta" type="date" name="hasta" value="{{ $filtros['hasta'] }}">
            </div>
        </div>
        <div class="pie-form">
            <button class="btn" type="submit">Filtrar</button>
            <a class="btn sec" href="{{ route('transacciones.index') }}">Limpiar</a>
        </div>
    </form>

    <div class="panel sin-relleno">
        <h2>{{ $total }} {{ $total == 1 ? 'transacción' : 'transacciones' }} · @dinero($montoTotal) en total</h2>
        @include('transacciones._tabla', ['filas' => $filas])
        <div class="paginacion">
            <span>Página {{ $pagina }} de {{ $paginas }}</span>
            <span class="acciones">
                @if ($pagina > 1)
                    <a class="btn sec chico" href="{{ request()->fullUrlWithQuery(['pagina' => $pagina - 1]) }}">Anterior</a>
                @endif
                @if ($pagina < $paginas)
                    <a class="btn sec chico" href="{{ request()->fullUrlWithQuery(['pagina' => $pagina + 1]) }}">Siguiente</a>
                @endif
            </span>
        </div>
    </div>
@endsection
