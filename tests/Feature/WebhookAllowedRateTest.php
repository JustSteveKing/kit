<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WebhookSubscription;
use App\Webhooks\DeliverWebhook;
use App\Webhooks\DeliveryState;
use App\Webhooks\HostResolver;
use App\Webhooks\WebhookEvent;
use App\Webhooks\Webhooks;
use App\Webhooks\WebhookTargets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
 * WebHook-Request-Rate and WebHook-Allowed-Rate, from the CloudEvents webhook handshake.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set([
        'webhooks.validate_endpoints' => true,
        'webhooks.origin' => 'api.example.test',
        'webhooks.request_rate' => null,
        'webhooks.events' => ['project.created'],
    ]);

    app()->instance(HostResolver::class, new class implements HostResolver {
        public function addresses(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
});

function endpointGranting(?string $rate): void
{
    Http::fake(fn(ClientRequest $request) => 'OPTIONS' === $request->method()
        ? Http::response('', 200, array_filter(['WebHook-Allowed-Origin' => 'api.example.test', 'WebHook-Allowed-Rate' => $rate]))
        : Http::response('', 204));
}

function subscribeFor(User $user): WebhookSubscription
{
    $id = test()->withToken($user->createToken('t', ['webhooks:write'])->plainTextToken)
        ->postJson('/v1/webhooks', ['url' => 'https://hooks.example.com/kit', 'events' => ['*']])
        ->assertCreated()
        ->json('data.id');

    return WebhookSubscription::query()->findOrFail($id);
}

it('stores the rate the endpoint grants', function (?string $granted, ?int $requested, ?int $stored): void {
    config()->set('webhooks.request_rate', $requested);
    endpointGranting($granted);

    $subscription = subscribeFor(User::factory()->create());

    expect($subscription->validated_at)->not->toBeNull();
    expect($subscription->allowed_rate)->toBe($stored);
})->with([
    'a number' => ['30', null, 30],
    'no limit' => ['*', 60, null],
    'none granted, none asked' => [null, null, null],
    'none granted, the requested rate applies' => [null, 20, 20],
    'not a rate, the requested rate applies' => ['fast', 20, 20],
]);

it('asks for the configured rate, and only when there is one', function (): void {
    endpointGranting('10');
    subscribeFor(User::factory()->create());

    Http::assertSent(fn(ClientRequest $request): bool => 'OPTIONS' === $request->method() && ! $request->hasHeader('WebHook-Request-Rate'));

    config()->set('webhooks.request_rate', 120);
    endpointGranting('10');
    subscribeFor(User::factory()->create());

    Http::assertSent(fn(ClientRequest $request): bool => 'OPTIONS' === $request->method() && '120' === ($request->header('WebHook-Request-Rate')[0] ?? null));
});

it('takes the rate from a consent callback, or the requested rate without one', function (?string $header, int $expected): void {
    config()->set('webhooks.request_rate', 20);
    Http::fake(fn() => Http::response('', 200));
    $subscription = subscribeFor(User::factory()->create());
    $callback = (string) Http::recorded()->last()[0]->header('WebHook-Request-Callback')[0];

    $this->withHeaders(array_filter(['WebHook-Allowed-Rate' => $header]))->postJson($callback)->assertOk();

    expect($subscription->refresh()->allowed_rate)->toBe($expected);
})->with([
    'granted in the callback' => ['15', 15],
    'a browser GET-style callback without it' => [null, 20],
]);

it('updates the rate when a callback answers a re-validation', function (): void {
    Http::fake(fn() => Http::response('', 200));
    $user = User::factory()->create();
    $subscription = subscribeFor($user);
    $first = (string) Http::recorded()->last()[0]->header('WebHook-Request-Callback')[0];

    $this->withHeaders(['WebHook-Allowed-Rate' => '30'])->postJson($first)->assertOk();
    expect($subscription->refresh()->allowed_rate)->toBe(30);

    $this->withToken($user->createToken('t', ['webhooks:write'])->plainTextToken)
        ->postJson('/v1/webhooks/' . $subscription->id . '/validate')
        ->assertAccepted();
    $second = (string) Http::recorded()->last()[0]->header('WebHook-Request-Callback')[0];

    $this->withHeaders(['WebHook-Allowed-Rate' => '5'])->postJson($second)->assertOk();
    expect($subscription->refresh()->allowed_rate)->toBe(5);

    // The first request was superseded, so its callback cannot put the old rate back.
    $this->withHeaders(['WebHook-Allowed-Rate' => '30'])->postJson($first)
        ->assertProblemType('https://apiguide.dev/errors/resource-conflict', 409)
        ->assertJsonPath('detail', __('api.webhooks.consent_superseded'));
    expect($subscription->refresh()->allowed_rate)->toBe(5);
});

it('forgets the granted rate when the url changes', function (): void {
    endpointGranting('30');
    $user = User::factory()->create();
    $subscription = subscribeFor($user);
    endpointGranting(null);

    $this->withToken($user->createToken('t', ['webhooks:write'])->plainTextToken)
        ->patchJson('/v1/webhooks/' . $subscription->id, ['url' => 'https://hooks.example.com/moved'])
        ->assertOk()
        ->assertJsonPath('data.allowed_rate', null);
});

it('postpones a delivery over the granted rate without spending an attempt', function (): void {
    $user = User::factory()->create();
    $subscription = WebhookSubscription::factory()->for($user)->create(['validated_at' => now(), 'allowed_rate' => 2]);
    Http::fake(['*' => Http::response('', 204)]);
    Queue::fake();

    $deliveries = [];

    foreach (['1', '2', '3'] as $id) {
        [$delivery] = app(Webhooks::class)->dispatch($user, new RateLimitedEvent($id));
        new DeliverWebhook($delivery->id)->handle(app(WebhookTargets::class));
        $deliveries[] = $delivery->refresh();
    }

    expect($deliveries[0]->state)->toBe(DeliveryState::Succeeded);
    expect($deliveries[1]->state)->toBe(DeliveryState::Succeeded);
    expect($deliveries[2]->state)->toBe(DeliveryState::Pending);
    expect($deliveries[2]->attempts()->count())->toBe(0);
    Http::assertSentCount(2);
    Queue::assertPushed(DeliverWebhook::class, fn(DeliverWebhook $job): bool => $job->deliveryId === $deliveries[2]->id && $job->delay > 0 && $job->delay <= 60);
});

it('does not limit a subscription that was granted no limit', function (): void {
    $user = User::factory()->create();
    WebhookSubscription::factory()->for($user)->create(['validated_at' => now(), 'allowed_rate' => null]);
    Http::fake(['*' => Http::response('', 204)]);

    foreach (range(1, 5) as $id) {
        app(Webhooks::class)->dispatch($user, new RateLimitedEvent((string) $id));
    }

    Http::assertSentCount(5);
});

final readonly class RateLimitedEvent implements WebhookEvent
{
    public function __construct(
        private string $id,
    ) {}

    public function type(): string
    {
        return 'project.created';
    }

    public function subject(): string
    {
        return $this->id;
    }

    public function data(): array
    {
        return ['project_id' => $this->id];
    }
}
