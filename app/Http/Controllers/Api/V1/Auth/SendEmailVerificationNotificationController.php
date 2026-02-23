<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Subgroup;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Email Verification')]
#[Endpoint(title: 'Send Verification Email', description: 'Send (or resend) an email verification link to the authenticated user.')]
#[Authenticated]
#[Response(content: ['message' => 'Verification link sent.'], status: 200, description: 'Verification email queued/sent.')]
#[Response(content: ['message' => 'Email is already verified.'], status: 200, description: 'User already verified.')]
#[Response(content: ['message' => 'Unauthenticated.'], status: 401, description: 'Authentication failed.')]
final class SendEmailVerificationNotificationController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            SecurityAudit::log('auth.email_verification.already_verified', [
                'user_id' => (string) $user->getKey(),
            ]);

            return new JsonResponse([
                'message' => __('api.auth.email_already_verified'),
            ]);
        }

        $user->sendEmailVerificationNotification();

        SecurityAudit::log('auth.email_verification.notification_sent', [
            'user_id' => (string) $user->getKey(),
        ]);

        return new JsonResponse([
            'message' => __('api.auth.verification_link_sent'),
        ]);
    }
}
