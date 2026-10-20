<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * Looks up the addresses a host name resolves to. A contract so tests can answer without DNS.
 */
interface HostResolver
{
    /**
     * @return list<string>
     */
    public function addresses(string $host): array;
}
