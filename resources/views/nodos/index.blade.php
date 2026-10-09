@extends('layouts.app')

@section('titulo', 'Sucursales y cajeros')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Sucursales y cajeros</h1>
            <p>Alta de nodos, API Key, efectivo disponible y responsables.</p>
        </div>
        <div class="acciones">
            <a class="btn" href="{{ route('nodos.create', ['tipo' => 'sucursal']) }}">Nueva sucursal</a>
            <a class="btn sec" href="{{ route('nodos.create', ['tipo' => 'cajero']) }}">Nuevo cajero</a>
        </div>
    </div>

    @if (session('api_key'))
        @php($clave = session('api_key'))
        <div class="aviso clave">
            <strong>
                {{ $clave['nueva'] ? 'Se creó' : 'Nuevo API Key para' }}
                {{ $clave['tipo'] === 'sucursal' ? 'la sucursal' : 'el cajero' }} «{{ $clave['nombre'] }}»
            </strong>
            Copia este API Key y configúralo en el nodo. Por seguridad solo se muestra esta vez:
            en la base de datos se guarda únicamente su hash.
            @unless ($clave['nueva'])
                El API Key anterior dejó de funcionar.
            @endunless
            <div class="clave-fila">
                <input id="api-key" class="mono" type="text" readonly value="{{ $clave['key'] }}" onfocus="this.select()">
                <button class="btn" type="button" onclick="copiarClave(this)">Copiar</button>
            </div>
        </div>
        <script>
            function copiarClave(boton) {
                const campo = document.getElementById('api-key');
                campo.select();
                const listo = () => { boton.textContent = 'Copiado'; };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(campo.value).then(listo);
                } else {
                    document.execCommand('copy');
                    listo();
                }
            }
        </script>
    @endif

    @foreach (['Sucursales' => $sucursales, 'Cajeros automáticos' => $cajeros] as $titulo => $nodos)
        <div class="panel sin-relleno">
            <h2>{{ $titulo }} ({{ $nodos->count() }})</h2>
            @if ($nodos->isEmpty())
                <div class="vacio">Aún no hay registros.</div>
            @else
                <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Responsable</th>
                                <th class="num">Efectivo disponible</th>
                                <th class="num">Operaciones</th>
                                <th>API Key</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($nodos as $nodo)
                                <tr>
                                    <td>{{ $nodo['nombre'] }}</td>
                                    <td>{{ $nodo['responsable'] ?? 'Sin asignar' }}</td>
                                    <td class="num">@dinero($nodo['efectivo_disponible'])</td>
                                    <td class="num">
                                        <a href="{{ route('transacciones.index', ['nodo' => $nodo['id']]) }}">{{ $nodo['operaciones'] }}</a>
                                    </td>
                                    <td class="mono">{{ $nodo['api_key_prefijo'] }}…</td>
                                    <td>
                                        <span @class(['etq', 'ok' => $nodo['estado'] === 'activo', 'apagado' => $nodo['estado'] !== 'activo'])>
                                            {{ ucfirst($nodo['estado']) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn sec chico" href="{{ route('nodos.edit', $nodo['id']) }}">Editar</a>
                                        <form class="en-linea" method="POST" action="{{ route('nodos.estado', $nodo['id']) }}">
                                            @csrf
                                            <input type="hidden" name="estado" value="{{ $nodo['estado'] === 'activo' ? 'inactivo' : 'activo' }}">
                                            <button class="btn sec chico" type="submit">{{ $nodo['estado'] === 'activo' ? 'Desactivar' : 'Activar' }}</button>
                                        </form>
                                        <form class="en-linea" method="POST" action="{{ route('nodos.api-key', $nodo['id']) }}"
                                              onsubmit="return confirm('Se generará un API Key nuevo y el actual dejará de funcionar. ¿Continuar?')">
                                            @csrf
                                            <button class="btn peligro chico" type="submit">Regenerar API Key</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach
@endsection
