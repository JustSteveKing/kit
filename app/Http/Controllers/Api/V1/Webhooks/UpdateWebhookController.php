<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Payloads\RouteParameter;
use App\Http\Payloads\V1\UpdateWebhookPayload;
use App\Http\Requests\Webhooks\UpdateWebhookRequest;
use App\Http\Resources\WebhookSubscriptionResource;
use App\Models\User;
use App\Models\WebhookSubscription;
use App\Support\SecurityAudit;
use App\Webhooks\ValidateWebhookEndpoint;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Webhooks')]
#[Endpoint(
    title: 'Update Webhook',
    description: 'Change a webhook subscription\'s URL, description or events, or enable or disable it; fields left out stay as they are. Use enabled: true to resume a subscription disabled after failing, which also resets its failure count. Returns the updated subscription.',
)]
#[Authenticated]
#[UrlParam('webhook', type: 'string', description: 'The webhook subscription ID.', required: true, example: '01jb8x2m4k5n6p7q8r9s0t1v2w')]
#[BodyParam('url', type: 'string', description: 'A new https URL.', required: false, example: 'https://hooks.example.com/kit-v2')]
#[BodyParam('description', type: 'string', description: 'A new label, or null to clear it.', required: false, example: 'Staging receiver')]
#[BodyParam('events', type: 'string[]', description: 'The event types to receive from now on.', required: false, example: ['*'])]
#[BodyParam('enabled', type: 'boolean', description: 'false to pause deliveries, true to resume them.', required: false, example: true)]
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
]], status: 200, description: 'The updated subscription.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'Webhook URLs must use https.',
        'errors' => ['url' => ['Webhook URLs must use https.']],
    ],
    status: 422,
    description: 'The URL is not https, resolves to a private address, or an event type is unknown.',
)]
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
final class UpdateWebhookController
{
    public function __invoke(UpdateWebhookRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        $subscription = $user->webhookSubscriptions()->findOrFail(RouteParameter::string($request, 'webhook'));
        $payload = UpdateWebhookPayload::fromRequest($request);

        $subscription->fill($payload->changes);

        if (true === $payload->enabled) {
            $subscription->forceFill(['disabled_at' => null, 'disabled_reason' => null, 'consecutive_failures' => 0]);
        } elseif (false === $payload->enabled && $subscription->isEnabled()) {
            $subscription->forceFill(['disabled_at' => now(), 'disabled_reason' => null]);
        }

        // Consent was given to the old URL, not the new one.
        if ($subscription->isDirty('url')) {
            $subscription->forceFill(['validated_at' => null, 'allowed_rate' => null]);
        }

        $subscription->save();

        if ($subscription->wasChanged('url') && WebhookSubscription::validationRequired()) {
            ValidateWebhookEndpoint::dispatch($subscription->id)->afterCommit();
        }

        SecurityAudit::log('webhooks.updated', [
            'user_id' => $user->id,
            'webhook_id' => $subscription->id,
            'fields' => [...array_keys($payload->changes), ...(null === $payload->enabled ? [] : ['enabled'])],
        ]);

        return WebhookSubscriptionResource::make($subscription)->response();
    }
}
