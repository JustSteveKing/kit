<?php

declare(strict_types=1);

namespace App\Webhooks;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses a subscription URL the delivery job would refuse, so the consumer hears about it when
 * subscribing rather than from a failed delivery. The job checks again on every send.
 */
final readonly class DeliverableUrl implements ValidationRule
{
    public function __construct(
        private WebhookTargets $targets,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ( ! is_string($value)) {
            return;
        }

        try {
            $this->targets->resolve($value);
        } catch (RefusedTarget $exception) {
            $fail($exception->getMessage());
        }
    }
}
