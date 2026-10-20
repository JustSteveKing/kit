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
        'ability_not_grantable' => 'Solo puedes conceder habilidades que tiene tu token actual.',
    ],
    'errors' => [
        'not_found' => 'No se encontro el recurso.',
        'unauthenticated' => 'No autenticado.',
        'forbidden' => 'Prohibido.',
        'too_many_requests' => 'Demasiadas solicitudes. Intentalo de nuevo mas tarde.',
        'https_required' => 'HTTPS es obligatorio para este endpoint.',
    ],
    'webhooks' => [
        'target_not_https' => 'Las URL de webhook deben usar https.',
        'target_unresolvable' => 'El host de la URL del webhook no se resuelve.',
        'target_private' => 'Las URL de webhook no deben apuntar a una direccion privada, de loopback o reservada.',
        'disabled_after_failures' => 'Desactivado tras fallar :count entregas seguidas.',
        'disabled_gone' => 'Desactivado porque el endpoint respondio 410 Gone.',
        'disabled' => 'La suscripcion de webhook esta desactivada.',
        'not_validated' => 'El endpoint del webhook aun no ha aceptado recibir entregas.',
        'invalid_consent_link' => 'Este enlace de consentimiento no es valido o ha caducado. Valida el webhook de nuevo para obtener otro.',
        'consent_url_changed' => 'La URL del webhook ha cambiado desde que se envio este enlace de consentimiento.',
        'consent_superseded' => 'Una solicitud de validacion mas reciente reemplazo la que envio este enlace de consentimiento.',
        'consent_granted' => 'Se permiten las entregas a este endpoint de webhook.',
    ],
    'sunset' => [
        'endpoint_unavailable' => 'Este endpoint ha sido retirado y ya no esta disponible.',
    ],
];
