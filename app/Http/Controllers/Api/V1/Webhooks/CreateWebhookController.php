<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Payloads\V1\CreateWebhookPayload;
use App\Http\Requests\Webhooks\CreateWebhookRequest;
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
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;

#[Group(name: 'Webhooks')]
#[Endpoint(
    title: 'Create Webhook',
    description: 'Subscribe an https endpoint to events. Use it once per receiving endpoint; send an Idempotency-Key so a retry does not create a second subscription. Returns the subscription with its signing secret in meta.secret, shown only this once: deliveries are signed with it using RFC 9421 HTTP Message Signatures.',
)]
#[Authenticated]
#[Header('Idempotency-Key', '"webhook-retry-1"')]
#[BodyParam('url', type: 'string', description: 'The https URL deliveries are POSTed to. It must not resolve to a private, loopback or reserved address.', required: true, example: 'https://hooks.example.com/kit')]
#[BodyParam('description', type: 'string', description: 'A label for the subscription.', required: false, example: 'Production receiver')]
#[BodyParam('events', type: 'string[]', description: 'Event types to receive, or ["*"] for all of them.', required: true, example: ['*'])]
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
], 'meta' => ['secret' => 'whsec_8f3c...']], status: 201, description: 'Subscription created. meta.secret is the only time the secret is returned.')]
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
final class CreateWebhookController
{
    public function __invoke(CreateWebhookRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = CreateWebhookPayload::fromRequest($request);
        $secret = WebhookSubscription::newSecret();

        $subscription = $user->webhookSubscriptions()->create([
            'url' => $payload->url,
            'description' => $payload->description,
            'events' => $payload->events,
            'secret' => $secret,
        ]);

        // CloudEvents webhook handshake: the endpoint consents before anything is delivered.
        if (WebhookSubscription::validationRequired()) {
            ValidateWebhookEndpoint::dispatch($subscription->id)->afterCommit();
        }

        SecurityAudit::log('webhooks.created', [
            'user_id' => $user->id,
            'webhook_id' => $subscription->id,
            'events' => $payload->events,
        ]);

        return WebhookSubscriptionResource::make($subscription)
            ->additional(['meta' => ['secret' => $secret]])
            ->response()
            ->setStatusCode(201);
    }
}
