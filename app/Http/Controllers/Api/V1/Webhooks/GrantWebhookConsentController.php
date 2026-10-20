<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Webhooks\GrantConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Webhooks')]
#[Endpoint(
    title: 'Grant Webhook Consent',
    description: 'Consent to deliveries for a webhook endpoint by POSTing to the WebHook-Request-Callback URL sent with its validation request (CloudEvents webhook specification, section 4.1.3), with no body. Use it from a delivery target that grants consent after the OPTIONS request rather than in its response; nobody builds this URL by hand. Returns a confirmation message; a link that is invalid or expired is a 403, and one issued before the subscription\'s URL changed is a 409.',
)]
#[Unauthenticated]
#[UrlParam('webhook', type: 'string', description: 'The webhook subscription ID, from the callback URL.', required: true, example: '01jb8x2m4k5n6p7q8r9s0t1v2w')]
#[QueryParam('url_hash', type: 'string', description: 'SHA-256 of the URL consent was asked for, from the callback URL.', required: true, example: '3a7bd3e2360a3d29eea436fcfb7e44c735d117c42d1c1835420b6b9942dd4f1b')]
#[QueryParam('request', type: 'string', description: 'The validation request this consent answers, from the callback URL.', required: true, example: '9f86d081884c7d659a2feaa0c55ad015')]
#[QueryParam('expires', type: 'integer', description: 'When the callback URL expires, from the callback URL.', required: true, example: 1761220800)]
#[QueryParam('signature', type: 'string', description: 'The callback URL\'s signature, from the callback URL.', required: true, example: '0c7f4e0b2d6a9e1f5b3c8d7a6e5f4c3b2a1d0e9f8c7b6a5d4e3f2c1b0a9d8e7f')]
#[Response(content: ['message' => 'Deliveries to this webhook endpoint are allowed.'], status: 200, description: 'Consent recorded.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'This consent link is invalid or has expired. Validate the webhook again for a new one.',
    ],
    status: 403,
    description: 'The callback URL was altered or has expired.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'The subscription has been deleted.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-conflict',
        'title' => 'Resource Conflict',
        'status' => 409,
        'detail' => 'The webhook\'s URL has changed since this consent link was sent.',
    ],
    status: 409,
    description: 'Consent was asked for a URL the subscription no longer uses, or a newer validation request replaced this one.',
)]
final class GrantWebhookConsentController
{
    public function __invoke(Request $request, GrantConsent $grant): JsonResponse
    {
        return $grant($request);
    }
}
