<?php

declare(strict_types=1);

return [
    'auth' => [
        'invalid_credentials' => 'Las credenciales proporcionadas son incorrectas.',
        'verification_link_sent' => 'Enlace de verificacion enviado.',
        'email_already_verified' => 'El correo electronico ya esta verificado.',
        'email_verified' => 'Correo electronico verificado correctamente.',
        'invalid_verification_link' => 'Enlace de verificacion invalido.',
        'password_reset_link_sent' => 'Si la cuenta existe, se ha enviado un enlace para restablecer la contrasena.',
        'password_reset_success' => 'Tu contrasena ha sido restablecida.',
        'password_reset_invalid_token' => 'Este token para restablecer la contrasena no es valido.',
        'password_reset_invalid_user' => 'No encontramos un usuario con esa direccion de correo electronico.',
        'password_reset_throttled' => 'Espera antes de volver a intentarlo.',
        'password_reset_failed' => 'No se pudo restablecer la contrasena con los datos proporcionados.',
        'token_not_found' => 'Token no encontrado.',
    ],
    'errors' => [
        'unauthenticated' => 'No autenticado.',
        'forbidden' => 'Prohibido.',
        'too_many_requests' => 'Demasiadas solicitudes. Intentalo de nuevo mas tarde.',
        'validation_failed' => 'Los datos proporcionados no son validos.',
        'https_required' => 'HTTPS es obligatorio para este endpoint.',
        'unsupported_media_type' => 'Tipo de contenido no compatible. Usa cuerpos de solicitud application/json.',
        'idempotency_key_invalid' => 'Formato de cabecera Idempotency-Key invalido.',
        'idempotency_key_conflict' => 'Idempotency-Key ya fue usado con un payload diferente.',
    ],
    'sunset' => [
        'endpoint_unavailable' => 'Este endpoint ha sido retirado y ya no esta disponible.',
    ],
];
