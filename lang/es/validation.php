<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'in' => 'El valor de :attribute no es válido.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'uuid' => 'El campo :attribute no es válido.',
    'after_or_equal' => 'La fecha :attribute debe ser igual o posterior a :date.',
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'max' => [
        'numeric' => 'El campo :attribute no puede ser mayor a :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],

    'attributes' => [
        'email' => 'correo',
        'password' => 'contraseña',
        'nombre' => 'nombre',
        'tipo' => 'tipo',
        'responsable' => 'responsable',
        'efectivo' => 'efectivo',
        'estado' => 'estado',
        'desde' => 'desde',
        'hasta' => 'hasta',
    ],
];
