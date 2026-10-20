<?php

declare(strict_types=1);

namespace App\Webhooks;

final class DnsHostResolver implements HostResolver
{
    /**
     * @return list<string>
     */
    public function addresses(string $host): array
    {
        if (false !== filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
