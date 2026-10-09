<?php

namespace App\Exceptions;

use RuntimeException;

class SupabaseException extends RuntimeException
{
    private const MENSAJES = [
        'NO_AUTORIZADO' => 'Tu usuario no es administrador del banco.',
        'NODO_NO_ENCONTRADO' => 'La sucursal o cajero ya no existe.',
        'NOMBRE_REQUERIDO' => 'El nombre es obligatorio.',
        'MONTO_INVALIDO' => 'El monto de efectivo no es válido.',
        'TIPO_INVALIDO' => 'El tipo debe ser sucursal o cajero.',
        'ESTADO_INVALIDO' => 'El estado debe ser activo o inactivo.',
        'SIN_CONEXION' => 'No se pudo conectar con Supabase. Revisa SUPABASE_URL y tu conexión.',
        'SIN_CONFIGURAR' => 'Faltan SUPABASE_URL o SUPABASE_ANON_KEY en el archivo .env.',
        'invalid_credentials' => 'Correo o contraseña incorrectos.',
        'email_not_confirmed' => 'El correo aún no está confirmado en Supabase.',
    ];

    public function __construct(public readonly int $status, public readonly string $codigo)
    {
        parent::__construct($codigo, $status);
    }

    /** La sesión ya no sirve: hay que volver a iniciar sesión. */
    public function sesionExpirada(): bool
    {
        return $this->status === 401;
    }

    public function mensaje(): string
    {
        return self::MENSAJES[$this->codigo] ?? "Supabase respondió con un error ({$this->status}): {$this->codigo}";
    }
}
