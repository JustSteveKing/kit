<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookSubscription>
 */
final class WebhookSubscriptionFactory extends Factory
{
    protected $model = WebhookSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'url' => 'https://hooks.example.com/' . $this->faker->slug(2),
            'description' => null,
            'events' => ['*'],
            'secret' => WebhookSubscription::newSecret(),
        ];
    }

    public function disabled(string $reason = 'Disabled for a test.'): self
    {
        return $this->state(fn(): array => ['disabled_at' => now(), 'disabled_reason' => $reason]);
    }
}
