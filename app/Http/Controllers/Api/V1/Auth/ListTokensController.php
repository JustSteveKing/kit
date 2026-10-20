<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\Pagination\CursorPage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Subgroup;
use Laravel\Sanctum\PersonalAccessToken;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Token Authentication')]
#[Endpoint(
    title: 'List Tokens',
    description: 'List the authenticated user\'s personal access tokens, newest first, 25 to a page. Use it to show signed-in devices or to find a token ID to revoke. Returns an array of tokens, with is_current marking the one making the request; follow the Link header\'s rel="next" for the next page.',
)]
#[Authenticated]
#[QueryParam('cursor', type: 'string', description: 'Opaque cursor from a Link header. Omit it for the first page.', required: false, example: null)]
#[Response(
    content: [[
        'id' => '1',
        'name' => 'ios-app',
        'abilities' => ['auth:me', 'auth:logout'],
        'last_used_at' => null,
        'expires_at' => '2026-02-24T12:00:00+00:00',
        'created_at' => '2026-02-23T12:00:00+00:00',
        'updated_at' => '2026-02-23T12:00:00+00:00',
        'is_current' => true,
    ]],
    status: 200,
    description: 'One page of tokens, newest first. Navigation is in RFC 8288 Link headers: rel="next" and rel="prev" when there is a page that way, and rel="first".',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'Forbidden.',
    ],
    status: 403,
    description: 'Token is missing required ability.',
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
final class ListTokensController
{
    public const int PER_PAGE = 25;

    /**
     * @return CursorPage<int, PersonalAccessToken>
     */
    public function __invoke(Request $request, #[CurrentUser] User $user): CursorPage
    {
        $tokens = $user->tokens()
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);

        SecurityAudit::log('auth.tokens.listed', [
            'user_id' => $user->id,
            'count' => count($tokens->items()),
        ]);

        return CursorPage::of($tokens, PersonalAccessTokenResource::class);
    }
}
