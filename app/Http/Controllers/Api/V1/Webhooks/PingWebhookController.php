<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Payloads\RouteParameter;
use App\Http\Resources\WebhookDeliveryResource;
use App\Models\User;
use App\Webhooks\Ping;
use App\Webhooks\Webhooks;
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
    title: 'Ping Webhook',
    description: 'Send a webhook.ping event to a subscription, whatever events it asked for. Use it to check a new receiver gets and verifies deliveries; it is limited to six a minute. Returns 202 with the queued delivery, whose progress List Webhook Deliveries shows.',
)]
#[Authenticated]
#[UrlParam('webhook', type: 'string', description: 'The webhook subscription ID.', required: true, example: '01jb8x2m4k5n6p7q8r9s0t1v2w')]
#[Response(content: ['data' => [
    'id' => '01jb8x3a4b5c6d7e8f9g0h1j2k',
    'event' => ['id' => '01jb8x3a1b2c3d4e5f6g7h8j9k', 'type' => 'webhook.ping'],
    'state' => 'retrying',
    'next_attempt_at' => '2026-10-20T12:01:10+00:00',
    'created_at' => '2026-10-20T12:01:00+00:00',
    'attempts' => [[
        'attempt' => 1,
        'response_status' => 503,
        'response_body' => 'Service Unavailable',
        'error' => null,
        'duration_ms' => 182,
        'attempted_at' => '2026-10-20T12:01:00+00:00',
    ]],
]], status: 202, description: 'Ping queued.')]
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
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-conflict',
        'title' => 'Resource Conflict',
        'status' => 409,
        'detail' => 'The webhook endpoint has not consented to deliveries yet.',
    ],
    status: 409,
    description: 'The subscription is disabled, or its endpoint has not been validated.',
)]
#[ResponseField('data.next_attempt_at', 'string', 'When the next attempt is due, or null when none is.', nullable: true)]
#[ResponseField('data.attempts[].response_status', 'integer', 'The endpoint\'s status code, or null when no response arrived.', nullable: true)]
#[ResponseField('data.attempts[].response_body', 'string', 'The start of the response body, or null.', nullable: true)]
#[ResponseField('data.attempts[].error', 'string', 'Why the attempt failed without a response, or null.', nullable: true)]
#[ResponseField('data.attempts[].duration_ms', 'integer', 'How long the attempt took, or null.', nullable: true)]
final class PingWebhookController
{
    public function __invoke(Request $request, #[CurrentUser] User $user, Webhooks $webhooks): JsonResponse
    {
        $subscription = $user->webhookSubscriptions()->findOrFail(RouteParameter::string($request, 'webhook'));
        if ( ! $subscription->receivesDeliveries()) {
            abort(409, __($subscription->isEnabled() ? 'api.webhooks.not_validated' : 'api.webhooks.disabled'));
        }

        $delivery = $webhooks->deliverTo($subscription, new Ping($subscription));

        return WebhookDeliveryResource::make($delivery->load(['event', 'attempts']))->response()->setStatusCode(202);
    }
}
