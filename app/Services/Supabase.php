<?php

namespace App\Services;

use App\Exceptions\SupabaseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente del Banco Central en Supabase.
 * - Auth: inicio de sesión y renovación del token del administrador.
 * - RPC: todas las operaciones del panel son funciones admin_* de PostgreSQL.
 */
class Supabase
{
    public function __construct(private readonly string $url, private readonly string $anonKey)
    {
    }

    /** @return array{access_token:string,refresh_token:string,expires_at:int,email:string} */
    public function iniciarSesion(string $email, string $password): array
    {
        return $this->pedirToken('password', ['email' => $email, 'password' => $password]);
    }

    /** @return array{access_token:string,refresh_token:string,expires_at:int,email:string} */
    public function refrescar(string $refreshToken): array
    {
        return $this->pedirToken('refresh_token', ['refresh_token' => $refreshToken]);
    }

    /**
     * Llama a una función de la base de datos: POST /rest/v1/rpc/{funcion}.
     * Los parámetros en null se omiten para que apliquen los valores por defecto.
     */
    public function rpc(string $funcion, array $parametros = [], ?string $token = null): mixed
    {
        $token ??= session('sb.access_token');
        $parametros = array_filter($parametros, fn ($valor) => $valor !== null);

        $respuesta = $this->enviar(
            fn () => $this->cliente($token)
                // (object) para que un arreglo vacío viaje como {} y no como []
                ->withBody(json_encode((object) $parametros), 'application/json')
                ->post("/rest/v1/rpc/{$funcion}")
        );

        if ($respuesta->failed()) {
            throw new SupabaseException($respuesta->status(), (string) ($respuesta->json('message') ?? 'ERROR_DESCONOCIDO'));
        }

        return $respuesta->json();
    }

    private function pedirToken(string $grant, array $datos): array
    {
        $respuesta = $this->enviar(
            fn () => $this->cliente(null)->post("/auth/v1/token?grant_type={$grant}", $datos)
        );

        if ($respuesta->failed()) {
            $codigo = $respuesta->json('error_code') ?? $respuesta->json('msg') ?? 'ERROR_DESCONOCIDO';

            throw new SupabaseException($respuesta->status(), (string) $codigo);
        }

        return [
            'access_token' => $respuesta->json('access_token'),
            'refresh_token' => $respuesta->json('refresh_token'),
            'expires_at' => time() + (int) $respuesta->json('expires_in', 3600),
            'email' => (string) $respuesta->json('user.email', ''),
        ];
    }

    private function cliente(?string $token): PendingRequest
    {
        if ($this->url === '' || $this->anonKey === '') {
            throw new SupabaseException(500, 'SIN_CONFIGURAR');
        }

        return Http::baseUrl($this->url)
            ->withHeaders(['apikey' => $this->anonKey])
            ->withToken($token ?? $this->anonKey)
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    private function enviar(callable $peticion): Response
    {
        try {
            return $peticion();
        } catch (ConnectionException) {
            throw new SupabaseException(503, 'SIN_CONEXION');
        }
    }
}
