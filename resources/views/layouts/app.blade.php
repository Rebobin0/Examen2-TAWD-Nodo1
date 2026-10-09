<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Panel') · Banco Central</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="barra">
        <div class="barra-int">
            <a class="logo" href="{{ route('dashboard') }}">Banco Central</a>
            <nav class="nav">
                <a href="{{ route('dashboard') }}" @class(['activo' => request()->routeIs('dashboard')])>Inicio</a>
                <a href="{{ route('nodos.index') }}" @class(['activo' => request()->routeIs('nodos.*')])>Sucursales y cajeros</a>
                <a href="{{ route('transacciones.index') }}" @class(['activo' => request()->routeIs('transacciones.*')])>Historial</a>
                <a href="{{ route('reportes.index') }}" @class(['activo' => request()->routeIs('reportes.*')])>Reportes</a>
            </nav>
            <div class="usuario">
                <span>{{ session('sb.email') }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Salir</button>
                </form>
            </div>
        </div>
    </header>

    <main>
        @if (session('ok'))
            <div class="aviso ok no-imprimir">{{ session('ok') }}</div>
        @endif

        @if ($errors->any())
            <div class="aviso error no-imprimir">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('contenido')
    </main>
</body>
</html>
