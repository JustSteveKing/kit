<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * Decides whether a subscription URL may be called. The URL is user input, so without this a
 * consumer could make the server request its own network (server-side request forgery).
 */
final readonly class WebhookTargets
{
    public function __construct(
        private HostResolver $resolver,
    ) {}

    /**
     * The address to connect to, for pinning the connection to what was checked, so a DNS change
     * between the check and the request cannot redirect it.
     *
     * @throws RefusedTarget
     */
    public function resolve(string $url): string
    {
        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (config()->boolean('webhooks.require_https', true) ? 'https' !== $scheme : ! in_array($scheme, ['http', 'https'], true)) {
            throw new RefusedTarget(__('api.webhooks.target_not_https'));
        }

        $addresses = $this->resolver->addresses(mb_trim($host, '[]'));

        if ([] === $addresses) {
            throw new RefusedTarget(__('api.webhooks.target_unresolvable'));
        }

        if ( ! config()->boolean('webhooks.allow_private_networks', false)) {
            foreach ($addresses as $address) {
                if (false === filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new RefusedTarget(__('api.webhooks.target_private'));
                }
            }
        }

        return $addresses[0];
    }
}
