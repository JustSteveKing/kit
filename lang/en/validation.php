<?php

declare(strict_types=1);

return [
    'accepted' => 'The :attribute field must be accepted.',
    'confirmed' => 'The :attribute field confirmation does not match.',
    'email' => 'The :attribute field must be a valid email address.',
    'exists' => 'The selected :attribute is invalid.',
    'lowercase' => 'The :attribute field must be lowercase.',
    'max' => [
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'min' => [
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'password' => [
        'letters' => 'The :attribute field must contain at least one letter.',
        'mixed' => 'The :attribute field must contain at least one uppercase and one lowercase letter.',
        'numbers' => 'The :attribute field must contain at least one number.',
        'symbols' => 'The :attribute field must contain at least one symbol.',
        'uncompromised' => 'The given :attribute has appeared in a data leak. Please choose a different :attribute.',
    ],
    'required' => 'The :attribute field is required.',
    'size' => [
        'string' => 'The :attribute field must be :size characters.',
    ],
    'string' => 'The :attribute field must be a string.',
    'ulid' => 'The :attribute field must be a valid ULID.',
    'unique' => 'The :attribute has already been taken.',

    'attributes' => [
        'device_name' => 'device name',
        'email' => 'email',
        'hash' => 'hash',
        'id' => 'id',
        'name' => 'name',
        'password' => 'password',
        'password_confirmation' => 'password confirmation',
        'token' => 'token',
    ],
];
