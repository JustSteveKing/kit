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
        'ability_not_grantable' => 'You can only grant abilities your current token holds.',
    ],
    'errors' => [
        'not_found' => 'The resource was not found.',
        'unauthenticated' => 'Unauthenticated.',
        'forbidden' => 'Forbidden.',
        'too_many_requests' => 'Too many requests. Please try again later.',
        'https_required' => 'HTTPS is required for this endpoint.',
    ],
    'webhooks' => [
        'target_not_https' => 'Webhook URLs must use https.',
        'target_unresolvable' => 'The webhook URL\'s host does not resolve.',
        'target_private' => 'Webhook URLs must not point at a private, loopback or reserved address.',
        'disabled_after_failures' => 'Disabled after :count deliveries in a row failed.',
        'disabled_gone' => 'Disabled because the endpoint answered 410 Gone.',
        'disabled' => 'The webhook subscription is disabled.',
        'not_validated' => 'The webhook endpoint has not consented to deliveries yet.',
        'invalid_consent_link' => 'This consent link is invalid or has expired. Validate the webhook again for a new one.',
        'consent_url_changed' => 'The webhook\'s URL has changed since this consent link was sent.',
        'consent_superseded' => 'A newer validation request replaced the one this consent link was sent with.',
        'consent_granted' => 'Deliveries to this webhook endpoint are allowed.',
    ],
    'sunset' => [
        'endpoint_unavailable' => 'This endpoint has been sunset and is no longer available.',
    ],
];
