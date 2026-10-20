<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Subgroup;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Email Verification')]
#[Endpoint(
    title: 'Send Verification Email',
    description: 'Send the authenticated user a new email verification link. Use it when the first link expired or never arrived; it is limited to six requests a minute. Returns a message saying the link was sent, or that the email is already verified.',
)]
#[Authenticated]
#[Response(content: ['message' => 'Verification link sent.'], status: 200, description: 'Verification email queued/sent.')]
#[Response(content: ['message' => 'Email is already verified.'], status: 200, description: 'User already verified.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/unauthorized',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
    ],
    status: 401,
    description: 'Authentication failed.',
)]
final class SendEmailVerificationNotificationController
{
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        if ($user->hasVerifiedEmail()) {
            SecurityAudit::log('auth.email_verification.already_verified', [
                'user_id' => $user->id,
            ]);

            return new JsonResponse([
                'message' => __('api.auth.email_already_verified'),
            ]);
        }

        $user->sendEmailVerificationNotification();

        SecurityAudit::log('auth.email_verification.notification_sent', [
            'user_id' => $user->id,
        ]);

        return new JsonResponse([
            'message' => __('api.auth.verification_link_sent'),
        ]);
    }
}
