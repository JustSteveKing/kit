<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Payloads\RouteParameter;
use App\Http\Resources\WebhookSubscriptionResource;
use App\Models\User;
use App\Models\WebhookSubscription;
use App\Support\SecurityAudit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Webhooks')]
#[Endpoint(
    title: 'Rotate Webhook Secret',
    description: 'Replace a webhook subscription\'s signing secret. Use it when the secret may have leaked; deliveries are signed with the new secret from the next attempt, so update the receiver straight away. Returns the subscription with the new secret in meta.secret, shown only this once.',
)]
#[Authenticated]
#[UrlParam('webhook', type: 'string', description: 'The webhook subscription ID.', required: true, example: '01jb8x2m4k5n6p7q8r9s0t1v2w')]
#[Response(content: ['data' => [
    'id' => '01jb8x2m4k5n6p7q8r9s0t1v2w',
    'url' => 'https://hooks.example.com/kit',
    'description' => 'Production receiver',
    'events' => ['*'],
    'enabled' => true,
    'validated_at' => '2026-10-20T12:00:05+00:00',
    'allowed_rate' => null,
    'disabled_at' => null,
    'disabled_reason' => null,
    'created_at' => '2026-10-20T12:00:00+00:00',
    'updated_at' => '2026-10-20T12:00:00+00:00',
], 'meta' => ['secret' => 'whsec_2b9e...']], status: 200, description: 'Secret rotated. meta.secret is the only time the new secret is returned.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'No webhook with this ID belongs to the authenticated user.',
)]
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
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'Forbidden.',
    ],
    status: 403,
    description: 'Token is missing the webhooks:write ability.',
)]
#[ResponseField('data.description', 'string', 'The label given to the subscription.', nullable: true)]
#[ResponseField('data.validated_at', 'string', 'When the endpoint consented to deliveries, or null if it has not.', nullable: true)]
#[ResponseField('data.allowed_rate', 'integer', 'Deliveries a minute the endpoint granted, or null for no limit.', nullable: true)]
#[ResponseField('data.disabled_at', 'string', 'When the subscription was disabled, or null while it is enabled.', nullable: true)]
#[ResponseField('data.disabled_reason', 'string', 'Why it was disabled, or null.', nullable: true)]
final class RotateWebhookSecretController
{
    public function __invoke(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $subscription = $user->webhookSubscriptions()->findOrFail(RouteParameter::string($request, 'webhook'));
        $secret = WebhookSubscription::newSecret();
        $subscription->update(['secret' => $secret]);

        SecurityAudit::log('webhooks.secret_rotated', [
            'user_id' => $user->id,
            'webhook_id' => $subscription->id,
        ]);

        return WebhookSubscriptionResource::make($subscription)
            ->additional(['meta' => ['secret' => $secret]])
            ->response();
    }
}
