@extends('layouts.app')

@section('titulo', 'Inicio')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Resumen del banco</h1>
            <p>Estado actual de la red y operaciones de hoy.</p>
        </div>
        <div class="acciones">
            <a class="btn" href="{{ route('nodos.create') }}">Nueva sucursal o cajero</a>
        </div>
    </div>

    <div class="tarjetas">
        <div class="tarjeta">
            <div class="etiqueta">Sucursales</div>
            <div class="valor">{{ $sistema['sucursales'] }}</div>
        </div>
        <div class="tarjeta">
            <div class="etiqueta">Cajeros</div>
            <div class="valor">{{ $sistema['cajeros'] }}</div>
            <div class="nota">{{ $sistema['nodos_activos'] }} nodos activos en total</div>
        </div>
        <div class="tarjeta">
            <div class="etiqueta">Efectivo en la red</div>
            <div class="valor">@dinero($sistema['efectivo_total'])</div>
        </div>
        <div class="tarjeta">
            <div class="etiqueta">Cuentas de clientes</div>
            <div class="valor">{{ $sistema['cuentas'] }}</div>
            <div class="nota">Saldo total @dinero($sistema['saldo_total'])</div>
        </div>
        <div class="tarjeta">
            <div class="etiqueta">Operaciones hoy</div>
            <div class="valor">{{ $hoy['operaciones'] }}</div>
            <div class="nota">{{ $hoy['retiros'] }} retiros · {{ $hoy['depositos'] }} depósitos · {{ $hoy['transferencias'] }} transf.</div>
        </div>
    </div>

    <div class="panel sin-relleno">
        <h2>Últimas transacciones</h2>
        @include('transacciones._tabla', ['filas' => $ultimas])
        <div class="paginacion">
            <span></span>
            <a href="{{ route('transacciones.index') }}">Ver historial completo</a>
        </div>
    </div>
@endsection
