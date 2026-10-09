<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · Banco Central</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="login">
        <h1>Banco Central</h1>
        <p class="sub">Panel de administración</p>

        @if ($errors->any())
            <div class="aviso error">{{ $errors->first() }}</div>
        @endif

        <form class="panel" method="POST" action="{{ url('/login') }}">
            @csrf
            <div class="campo">
                <label for="email">Correo</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <div class="campo">
                <label for="password">Contraseña</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <button class="btn" type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
