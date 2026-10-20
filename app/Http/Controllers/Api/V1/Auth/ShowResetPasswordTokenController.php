<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Payloads\V1\ShowResetPasswordTokenPayload;
use App\Http\Requests\Auth\ShowResetPasswordTokenRequest;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Subgroup;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Password Reset')]
#[Endpoint(
    title: 'Read Reset Token Payload',
    description: 'Return the token and email from a password reset link, for a client that opens the link and shows its own reset form. It does not check the token; Reset Password does. Returns the token and the email, if the link carried one.',
)]
#[Unauthenticated]
#[UrlParam('token', type: 'string', description: 'Password reset token from reset link.', required: true, example: 'reset-token-value')]
#[QueryParam('email', type: 'string', description: 'Account email from the reset link.', required: false, example: 'jane@example.com')]
#[Response(content: ['token' => 'reset-token-value', 'email' => 'jane@example.com'], status: 200, description: 'Reset payload data.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'The email field must be a valid email address.',
        'errors' => ['email' => ['The email field must be a valid email address.']],
    ],
    status: 422,
    description: 'The email query parameter was not an email address.',
)]
final class ShowResetPasswordTokenController
{
    public function __invoke(ShowResetPasswordTokenRequest $request): JsonResponse
    {
        $payload = ShowResetPasswordTokenPayload::fromRequest($request);

        return new JsonResponse([
            'token' => $payload->token,
            'email' => $payload->email,
        ]);
    }
}
