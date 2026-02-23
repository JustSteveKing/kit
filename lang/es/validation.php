<?php

declare(strict_types=1);

return [
    'accepted' => 'El campo :attribute debe ser aceptado.',
    'confirmed' => 'La confirmacion de :attribute no coincide.',
    'email' => 'El campo :attribute debe ser una direccion de correo valida.',
    'exists' => 'El campo :attribute seleccionado no es valido.',
    'lowercase' => 'El campo :attribute debe estar en minusculas.',
    'max' => [
        'string' => 'El campo :attribute no debe ser mayor que :max caracteres.',
    ],
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'password' => [
        'letters' => 'El campo :attribute debe contener al menos una letra.',
        'mixed' => 'El campo :attribute debe contener al menos una letra mayuscula y una minuscula.',
        'numbers' => 'El campo :attribute debe contener al menos un numero.',
        'symbols' => 'El campo :attribute debe contener al menos un simbolo.',
        'uncompromised' => 'El :attribute proporcionado ha aparecido en una filtracion de datos. Elige un :attribute diferente.',
    ],
    'required' => 'El campo :attribute es obligatorio.',
    'size' => [
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'ulid' => 'El campo :attribute debe ser un ULID valido.',
    'unique' => 'El campo :attribute ya ha sido registrado.',

    'attributes' => [
        'device_name' => 'nombre del dispositivo',
        'email' => 'correo electronico',
        'hash' => 'hash',
        'id' => 'id',
        'name' => 'nombre',
        'password' => 'contrasena',
        'password_confirmation' => 'confirmacion de contrasena',
        'token' => 'token',
    ],
];
