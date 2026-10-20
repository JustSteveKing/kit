<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Models\WebhookSubscription;
use JustSteveKing\Toolbox\MessageSignatures\Contracts\KeyResolver;
use JustSteveKing\Toolbox\MessageSignatures\Key;

/**
 * Lets api-toolbox sign with each subscription's own secret: a key ID that is a subscription's ID
 * resolves to an HMAC-SHA256 key, and anything else falls through to config/api-toolbox.php.
 */
final readonly class SubscriptionSigningKeys implements KeyResolver
{
    public function __construct(
        private KeyResolver $configured,
    ) {}

    public function resolve(string $keyId): ?Key
    {
        $subscription = 26 === mb_strlen($keyId) ? WebhookSubscription::query()->find($keyId) : null;

        if ($subscription instanceof WebhookSubscription) {
            return new Key($subscription->id, 'hmac-sha256', $subscription->secret);
        }

        return $this->configured->resolve($keyId);
    }
}
