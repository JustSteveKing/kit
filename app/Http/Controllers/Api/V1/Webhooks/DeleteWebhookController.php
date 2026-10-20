<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Payloads\RouteParameter;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Webhooks')]
#[Endpoint(
    title: 'Delete Webhook',
    description: 'Delete a webhook subscription and its delivery history. Use it when an endpoint is retired; to pause deliveries instead, update it with enabled: false. Returns 204 with no body.',
)]
#[Authenticated]
#[UrlParam('webhook', type: 'string', description: 'The webhook subscription ID.', required: true, example: '01jb8x2m4k5n6p7q8r9s0t1v2w')]
#[ScribeResponse(content: null, status: 204, description: 'Subscription deleted.')]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'No webhook with this ID belongs to the authenticated user.',
)]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/unauthorized',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
    ],
    status: 401,
    description: 'Authentication failed.',
)]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'Forbidden.',
    ],
    status: 403,
    description: 'Token is missing the webhooks:write ability.',
)]
final class DeleteWebhookController
{
    public function __invoke(Request $request, #[CurrentUser] User $user): Response
    {
        $subscription = $user->webhookSubscriptions()->findOrFail(RouteParameter::string($request, 'webhook'));
        $subscription->delete();

        SecurityAudit::log('webhooks.deleted', [
            'user_id' => $user->id,
            'webhook_id' => $subscription->id,
        ]);

        return response()->noContent();
    }
}
