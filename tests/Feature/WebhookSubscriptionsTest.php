<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WebhookSubscription;
use App\Webhooks\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('webhooks.require_https', true);
    config()->set('webhooks.allow_private_networks', false);
    app()->instance(HostResolver::class, new class implements HostResolver {
        /** @var list<string> */
        public array $answer = ['93.184.216.34'];

        public function addresses(string $host): array
        {
            return $this->answer;
        }
    });
});

function webhookToken(User $user, array $abilities = ['webhooks:read', 'webhooks:write']): string
{
    return $user->createToken('webhooks', $abilities)->plainTextToken;
}

it('creates a subscription and shows its secret only once', function (): void {
    $user = User::factory()->create();

    $response = $this->withToken(webhookToken($user))
        ->postJson('/v1/webhooks', ['url' => 'https://hooks.example.com/kit', 'events' => ['*']])
        ->assertCreated()
        ->assertJsonPath('data.url', 'https://hooks.example.com/kit')
        ->assertJsonPath('data.enabled', true)
        ->assertJsonMissingPath('data.secret');

    $secret = (string) $response->json('meta.secret');
    $subscription = WebhookSubscription::query()->findOrFail($response->json('data.id'));

    expect($secret)->toStartWith('whsec_');
    expect($subscription->secret)->toBe($secret);
    expect(DB::table('webhook_subscriptions')->value('secret'))->not->toContain($secret);

    $this->withToken(webhookToken($user))
        ->getJson('/v1/webhooks/' . $subscription->id)
        ->assertOk()
        ->assertJsonMissingPath('meta.secret')
        ->assertJsonMissingPath('data.secret');
});

it('refuses urls that are not https or resolve to a private address', function (): void {
    $user = User::factory()->create();

    $this->withToken(webhookToken($user))
        ->postJson('/v1/webhooks', ['url' => 'http://hooks.example.com/kit', 'events' => ['*']])
        ->assertProblemType('https://apiguide.dev/errors/validation-failed', 422)
        ->assertJsonPath('errors.url.0', __('api.webhooks.target_not_https'));

    app(HostResolver::class)->answer = ['10.0.0.12'];

    $this->withToken(webhookToken($user))
        ->postJson('/v1/webhooks', ['url' => 'https://internal.example.com/kit', 'events' => ['*']])
        ->assertProblem(422)
        ->assertJsonPath('errors.url.0', __('api.webhooks.target_private'));

    $this->withToken(webhookToken($user))
        ->postJson('/v1/webhooks', ['url' => 'https://127.0.0.1/kit', 'events' => ['*']])
        ->assertProblem(422);

    expect(WebhookSubscription::query()->count())->toBe(0);
});

it('only accepts event types the application lists', function (): void {
    config()->set('webhooks.events', ['project.created']);
    $user = User::factory()->create();

    $this->withToken(webhookToken($user))
        ->postJson('/v1/webhooks', ['url' => 'https://hooks.example.com/kit', 'events' => ['project.deleted']])
        ->assertProblem(422)
        ->assertJsonValidationErrors(['events.0']);

    $this->withToken(webhookToken($user))
        ->postJson('/v1/webhooks', ['url' => 'https://hooks.example.com/kit', 'events' => ['project.created']])
        ->assertCreated();
});

it('keeps each user to their own subscriptions, without naming the model', function (): void {
    $user = User::factory()->create();
    $mine = WebhookSubscription::factory()->for($user)->create();
    $theirs = WebhookSubscription::factory()->create();

    $this->withToken(webhookToken($user))
        ->getJson('/v1/webhooks')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $mine->id);

    foreach (['getJson', 'deleteJson'] as $call) {
        $this->withToken(webhookToken($user))
            ->{$call}('/v1/webhooks/' . $theirs->id)
            ->assertProblemType('https://apiguide.dev/errors/resource-not-found', 404)
            ->assertJsonPath('detail', __('api.errors.not_found'));
    }

    expect(WebhookSubscription::query()->whereKey($theirs->id)->exists())->toBeTrue();
});

it('updates only the fields sent, and re-enabling resets the failure count', function (): void {
    $user = User::factory()->create();
    $subscription = WebhookSubscription::factory()->for($user)->disabled('Disabled after 10 deliveries in a row failed.')->create([
        'description' => 'keep me',
        'consecutive_failures' => 10,
    ]);

    $this->withToken(webhookToken($user))
        ->patchJson('/v1/webhooks/' . $subscription->id, ['url' => 'https://hooks.example.com/v2', 'enabled' => true])
        ->assertOk()
        ->assertJsonPath('data.url', 'https://hooks.example.com/v2')
        ->assertJsonPath('data.description', 'keep me')
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.disabled_reason', null);

    expect($subscription->refresh()->consecutive_failures)->toBe(0);

    $this->withToken(webhookToken($user))
        ->patchJson('/v1/webhooks/' . $subscription->id, ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.enabled', false);
});

it('deletes a subscription', function (): void {
    $user = User::factory()->create();
    $subscription = WebhookSubscription::factory()->for($user)->create();

    $this->withToken(webhookToken($user))
        ->deleteJson('/v1/webhooks/' . $subscription->id)
        ->assertNoContent();

    expect(WebhookSubscription::query()->count())->toBe(0);
});

it('rotates the secret and shows the new one once', function (): void {
    $user = User::factory()->create();
    $subscription = WebhookSubscription::factory()->for($user)->create();
    $old = $subscription->secret;

    $response = $this->withToken(webhookToken($user))
        ->postJson('/v1/webhooks/' . $subscription->id . '/secret')
        ->assertOk();

    expect($response->json('meta.secret'))->toStartWith('whsec_')->not->toBe($old);
    expect($subscription->refresh()->secret)->toBe($response->json('meta.secret'));
});

it('requires the webhooks abilities', function (): void {
    $user = User::factory()->create();
    $readOnly = webhookToken($user, ['webhooks:read']);

    $this->withToken($readOnly)->getJson('/v1/webhooks')->assertOk();
    $this->withToken($readOnly)
        ->postJson('/v1/webhooks', ['url' => 'https://hooks.example.com/kit', 'events' => ['*']])
        ->assertProblem(403);
    app('auth')->forgetGuards();
    $this->withToken(webhookToken($user, ['auth:me']))->getJson('/v1/webhooks')->assertProblem(403);
});
