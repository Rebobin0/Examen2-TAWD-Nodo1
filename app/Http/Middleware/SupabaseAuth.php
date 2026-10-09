<?php

namespace App\Http\Middleware;

use App\Exceptions\SupabaseException;
use App\Services\Supabase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Exige sesión de administrador y renueva el token de Supabase antes de que caduque. */
class SupabaseAuth
{
    public function __construct(private readonly Supabase $supabase)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $sesion = $request->session()->get('sb');

        if (! $sesion) {
            return redirect()->route('login');
        }

        if ($sesion['expires_at'] - 60 <= time()) {
            try {
                $nueva = $this->supabase->refrescar($sesion['refresh_token']);
                $nueva['email'] = $nueva['email'] ?: $sesion['email'];
                $request->session()->put('sb', $nueva);
            } catch (SupabaseException) {
                $request->session()->forget('sb');

                return redirect()->route('login')->withErrors(['email' => 'Tu sesión expiró. Inicia sesión de nuevo.']);
            }
        }

        return $next($request);
    }
}
