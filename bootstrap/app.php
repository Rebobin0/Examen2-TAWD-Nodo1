<?php

use App\Exceptions\SupabaseException;
use App\Http\Middleware\SupabaseAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['supabase.auth' => SupabaseAuth::class]);
        // Render y otros hostings sirven la app detrás de un proxy HTTPS
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport(SupabaseException::class);

        $exceptions->render(function (SupabaseException $e, Request $request) {
            if ($e->sesionExpirada()) {
                $request->session()->forget('sb');

                return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión de nuevo.']);
            }

            // En formularios se regresa con el error; en páginas de consulta se muestra una pantalla de error
            if (! $request->isMethod('GET')) {
                return back()->withInput()->withErrors(['supabase' => $e->mensaje()]);
            }

            return response()->view('error', ['mensaje' => $e->mensaje()], 502);
        });
    })->create();
