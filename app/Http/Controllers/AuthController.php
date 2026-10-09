<?php

namespace App\Http\Controllers;

use App\Exceptions\SupabaseException;
use App\Services\Supabase;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function formulario(Request $request)
    {
        if ($request->session()->has('sb')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function iniciar(Request $request, Supabase $supabase)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $sesion = $supabase->iniciarSesion($datos['email'], $datos['password']);

            // Tener cuenta en Supabase Auth no basta: debe estar en la tabla admins
            if ($supabase->rpc('es_admin', [], $sesion['access_token']) !== true) {
                throw new SupabaseException(403, 'NO_AUTORIZADO');
            }
        } catch (SupabaseException $e) {
            return back()->withInput($request->only('email'))->withErrors(['email' => $e->mensaje()]);
        }

        $sesion['email'] = $sesion['email'] ?: $datos['email'];
        $request->session()->regenerate();
        $request->session()->put('sb', $sesion);

        return redirect()->route('dashboard');
    }

    public function cerrar(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
