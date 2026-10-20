<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JustSteveKing\Toolbox\Contracts\RequestIdentifier;

final class SecurityAudit
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $event, array $context = []): void
    {
        Log::info('security.audit', array_merge(
            [
                'event' => $event,
                'occurred_at' => now()->toAtomString(),
            ],
            self::requestContext(),
            $context,
        ));
    }

    public static function hashEmail(string $email): string
    {
        return hash('sha256', mb_strtolower(mb_trim($email)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function requestContext(): array
    {
        if ( ! app()->bound('request')) {
            return [];
        }

        /** @var Request $request */
        $request = app('request');

        return [
            'request_id' => app()->bound(RequestIdentifier::class) ? app(RequestIdentifier::class)->current() : null,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
        ];
    }
}
