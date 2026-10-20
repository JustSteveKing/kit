<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Resources\WebhookSubscriptionResource;
use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Container\Attributes\CurrentUser;
use JustSteveKing\Toolbox\Pagination\CursorPage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;

#[Group(name: 'Webhooks')]
#[Endpoint(
    title: 'List Webhooks',
    description: 'List the authenticated user\'s webhook subscriptions, newest first, 25 to a page. Use it to see where events are sent and which subscriptions were disabled after failing. Returns an array of subscriptions; follow the Link header\'s rel="next" for the next page.',
)]
#[Authenticated]
#[QueryParam('cursor', type: 'string', description: 'Opaque cursor from a Link header. Omit it for the first page.', required: false, example: null)]
#[Response(content: [[
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
]], status: 200, description: 'One page of subscriptions.')]
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
#[ResponseField('[].description', 'string', 'The label given to the subscription.', nullable: true)]
#[ResponseField('[].validated_at', 'string', 'When the endpoint consented to deliveries, or null if it has not.', nullable: true)]
#[ResponseField('[].allowed_rate', 'integer', 'Deliveries a minute the endpoint granted, or null for no limit.', nullable: true)]
#[ResponseField('[].disabled_at', 'string', 'When the subscription was disabled, or null while it is enabled.', nullable: true)]
#[ResponseField('[].disabled_reason', 'string', 'Why it was disabled, or null.', nullable: true)]
final class ListWebhooksController
{
    /**
     * @return CursorPage<int, WebhookSubscription>
     */
    public function __invoke(#[CurrentUser] User $user): CursorPage
    {
        return CursorPage::of(
            $user->webhookSubscriptions()->orderByDesc('id')->cursorPaginate(25),
            WebhookSubscriptionResource::class,
        );
    }
}
