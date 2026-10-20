<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Payloads\RouteParameter;
use App\Http\Resources\WebhookDeliveryResource;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\Pagination\CursorPage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Webhooks')]
#[Endpoint(
    title: 'List Webhook Deliveries',
    description: 'List a webhook subscription\'s deliveries, newest first, 25 to a page, each with every attempt made. Use it to see why a receiver missed an event: a null response_status means no response arrived, as distinct from an error status. Returns an array of deliveries; follow the Link header\'s rel="next" for the next page.',
)]
#[Authenticated]
#[UrlParam('webhook', type: 'string', description: 'The webhook subscription ID.', required: true, example: '01jb8x2m4k5n6p7q8r9s0t1v2w')]
#[QueryParam('cursor', type: 'string', description: 'Opaque cursor from a Link header. Omit it for the first page.', required: false, example: null)]
#[Response(content: [[
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
]], status: 200, description: 'One page of deliveries.')]
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
    description: 'Token is missing the webhooks:read ability.',
)]
#[ResponseField('[].next_attempt_at', 'string', 'When the next attempt is due, or null when none is.', nullable: true)]
#[ResponseField('[].attempts[].response_status', 'integer', 'The endpoint\'s status code, or null when no response arrived.', nullable: true)]
#[ResponseField('[].attempts[].response_body', 'string', 'The start of the response body, or null.', nullable: true)]
#[ResponseField('[].attempts[].error', 'string', 'Why the attempt failed without a response, or null.', nullable: true)]
#[ResponseField('[].attempts[].duration_ms', 'integer', 'How long the attempt took, or null.', nullable: true)]
final class ListWebhookDeliveriesController
{
    /**
     * @return CursorPage<int, WebhookDelivery>
     */
    public function __invoke(Request $request, #[CurrentUser] User $user): CursorPage
    {
        $subscription = $user->webhookSubscriptions()->findOrFail(RouteParameter::string($request, 'webhook'));

        return CursorPage::of(
            $subscription->deliveries()->with(['event', 'attempts'])->orderByDesc('id')->cursorPaginate(25),
            WebhookDeliveryResource::class,
        );
    }
}
