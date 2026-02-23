<?php

declare(strict_types=1);

return [
    'auth' => [
        'invalid_credentials' => 'The provided credentials are incorrect.',
        'verification_link_sent' => 'Verification link sent.',
        'email_already_verified' => 'Email is already verified.',
        'email_verified' => 'Email verified successfully.',
        'invalid_verification_link' => 'Invalid verification link.',
        'password_reset_link_sent' => 'If the account exists, a password reset link has been sent.',
        'password_reset_success' => 'Your password has been reset.',
        'password_reset_invalid_token' => 'This password reset token is invalid.',
        'password_reset_invalid_user' => 'We can\'t find a user with that email address.',
        'password_reset_throttled' => 'Please wait before retrying.',
        'password_reset_failed' => 'Unable to reset password with the provided details.',
        'token_not_found' => 'Token not found.',
    ],
    'errors' => [
        'unauthenticated' => 'Unauthenticated.',
        'forbidden' => 'Forbidden.',
        'too_many_requests' => 'Too many requests. Please try again later.',
        'validation_failed' => 'The given data was invalid.',
        'https_required' => 'HTTPS is required for this endpoint.',
        'unsupported_media_type' => 'Unsupported media type. Use application/json request bodies.',
        'idempotency_key_invalid' => 'Invalid Idempotency-Key header format.',
        'idempotency_key_conflict' => 'Idempotency-Key was already used with a different request payload.',
    ],
    'sunset' => [
        'endpoint_unavailable' => 'This endpoint has been sunset and is no longer available.',
    ],
];
