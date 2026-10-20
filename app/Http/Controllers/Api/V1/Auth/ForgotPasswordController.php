<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Payloads\V1\ForgotPasswordPayload;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Subgroup;
use Knuckles\Scribe\Attributes\Unauthenticated;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Password Reset')]
#[Endpoint(
    title: 'Request Password Reset',
    description: 'Email a password reset link to an address, if an account uses it. Use it from a forgot-password form. The response is the same whether or not the account exists, so it cannot be used to discover accounts; send an Idempotency-Key so a retry does not send a second email.',
)]
#[Unauthenticated]
#[BodyParam('email', type: 'string', description: 'Account email address.', required: true, example: 'jane@example.com')]
#[Response(
    content: ['message' => 'If the account exists, a password reset link has been sent.'],
    status: 200,
    description: 'Password reset request accepted.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'The email field must be a valid email address.',
        'errors' => ['email' => ['The email field must be a valid email address.']],
    ],
    status: 422,
    description: 'The email was missing or not an email address.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/rate-limit-exceeded',
        'title' => 'Rate Limit Exceeded',
        'status' => 429,
        'detail' => 'Too many requests. Please try again later.',
    ],
    status: 429,
    description: 'Too many reset requests for this address; retry after the Retry-After header.',
)]
final class ForgotPasswordController
{
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $payload = ForgotPasswordPayload::fromRequest($request);

        $status = Password::broker()->sendResetLink([
            'email' => $payload->email,
        ]);

        SecurityAudit::log('auth.password_reset.requested', [
            'email_hash' => SecurityAudit::hashEmail($payload->email),
            'status' => $status,
        ]);

        return new JsonResponse([
            'message' => __('api.auth.password_reset_link_sent'),
        ]);
    }
}
