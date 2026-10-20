<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Payloads\V1\VerifyEmailPayload;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Subgroup;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Email Verification')]
#[Endpoint(
    title: 'Verify Email',
    description: 'Mark the user\'s email as verified using the signed link from the verification email. Clients do not build this URL; they follow the one in the email, which expires. Returns a confirmation message; a link that is invalid, expired or altered is a 403.',
)]
#[Unauthenticated]
#[UrlParam('id', type: 'string', description: 'User ULID from the signed verification link.', required: true, example: '01HZX3W3T4J8Q57XNZD5BPHJ92')]
#[UrlParam('hash', type: 'string', description: 'SHA-1 hash of the email from the signed verification link.', required: true, example: '8c4f4370e5db9b5be6d0f4c95495f49f998fa32a')]
#[Response(content: ['message' => 'Email verified successfully.'], status: 200, description: 'Email was verified.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'Invalid verification link.',
    ],
    status: 403,
    description: 'Signed URL was invalid, expired, or mismatched.',
)]
final class VerifyEmailController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = VerifyEmailPayload::fromRequest($request);

        $user = User::query()->findOrFail($payload->id);

        if ( ! hash_equals(sha1($user->getEmailForVerification()), $payload->hash)) {
            SecurityAudit::log('auth.email_verification.failed', [
                'user_id' => $user->id,
                'reason' => 'hash_mismatch',
            ]);

            abort(403, __('api.auth.invalid_verification_link'));
        }

        $wasAlreadyVerified = $user->hasVerifiedEmail();

        if ( ! $wasAlreadyVerified && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        SecurityAudit::log('auth.email_verification.succeeded', [
            'user_id' => $user->id,
            'already_verified' => $wasAlreadyVerified,
        ]);

        return new JsonResponse([
            'message' => __('api.auth.email_verified'),
        ]);
    }
}
