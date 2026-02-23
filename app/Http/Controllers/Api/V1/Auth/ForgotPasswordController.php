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
#[Endpoint(title: 'Request Password Reset', description: 'Send a password reset link to the provided email if an account exists.')]
#[Unauthenticated]
#[BodyParam('email', type: 'string', description: 'Account email address.', required: true, example: 'jane@example.com')]
#[Response(
    content: ['message' => 'If the account exists, a password reset link has been sent.'],
    status: 200,
    description: 'Password reset request accepted.'
)]
final class ForgotPasswordController
{
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $payload = ForgotPasswordPayload::fromReqest($request);

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
