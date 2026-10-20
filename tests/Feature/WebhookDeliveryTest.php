<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEvent as StoredEvent;
use App\Models\WebhookSubscription;
use App\Webhooks\DeliverWebhook;
use App\Webhooks\DeliveryState;
use App\Webhooks\HostResolver;
use App\Webhooks\WebhookEvent;
use App\Webhooks\Webhooks;
use App\Webhooks\WebhookTargets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('webhooks.require_https', true);
    config()->set('webhooks.allow_private_networks', false);
    config()->set('webhooks.backoff', [10, 60]);
    config()->set('webhooks.disable_after', 10);
    config()->set('webhooks.events', ['project.created', 'project.deleted']);

    app()->instance(HostResolver::class, new class implements HostResolver {
        /** @var list<string> */
        public array $answer = ['93.184.216.34'];

        public function addresses(string $host): array
        {
            return $this->answer;
        }
    });
});

it('signs a ping so that an api-toolbox receiver verifies it', function (): void {
    $user = User::factory()->create();
    $subscription = WebhookSubscription::factory()->for($user)->create(['url' => 'https://hooks.example.com/receive']);

    // The receiver: this application, with api-toolbox's verifier and the subscription's secret.
    Route::post('/receive', fn(): array => ['verified' => true])->middleware('signature.verify');

    Http::fake(function (ClientRequest $request) {
        $server = ['HTTP_HOST' => 'hooks.example.com', 'HTTPS' => 'on', 'CONTENT_TYPE' => $request->header('Content-Type')[0] ?? ''];

        foreach ($request->headers() as $name => $values) {
            $server['HTTP_' . mb_strtoupper(str_replace('-', '_', $name))] = implode(', ', $values);
        }

        $received = $this->call('POST', 'https://hooks.example.com/receive', [], [], [], $server, $request->body());

        return Http::response($received->getContent(), $received->getStatusCode());
    });

    $response = $this->withToken($user->createToken('t', ['webhooks:write'])->plainTextToken)
        ->postJson('/v1/webhooks/' . $subscription->id . '/ping')
        ->assertAccepted()
        ->assertJsonPath('data.event.type', 'webhook.ping');

    $delivery = WebhookDelivery::query()->findOrFail($response->json('data.id'));

    expect($delivery->state)->toBe(DeliveryState::Succeeded);
    expect($delivery->attempts()->sole()->response_status)->toBe(200);

    Http::assertSent(fn(ClientRequest $request): bool => str_contains($request->header('Signature-Input')[0] ?? '', 'keyid="' . $subscription->id . '"')
            && $request->hasHeader('Content-Digest')
            && $request->header('Idempotency-Key')[0] === '"' . $delivery->event->id . '"'
            && $request->header('WebHook-Request-Origin')[0] === config('webhooks.origin'));
});

it('delivers a CloudEvent in structured content mode', function (): void {
    config()->set(['webhooks.source' => 'https://api.example.test/v1', 'webhooks.origin' => 'api.example.test']);
    $user = User::factory()->create();
    WebhookSubscription::factory()->for($user)->create();
    Http::fake(['*' => Http::response('', 204)]);
    $this->freezeSecond();

    [$delivery] = app(Webhooks::class)->dispatch($user, new ProjectCreated('01jbproject'));

    Http::assertSent(fn(ClientRequest $request): bool => 'application/cloudevents+json; charset=utf-8' === $request->header('Content-Type')[0]
            && json_decode($request->body(), true) === [
                'specversion' => '1.0',
                'id' => $delivery->webhook_event_id,
                'source' => 'https://api.example.test/v1',
                'type' => 'project.created',
                'subject' => '01jbproject',
                'time' => now()->toRfc3339String(),
                'datacontenttype' => 'application/json',
                'data' => ['project_id' => '01jbproject'],
            ]);
});

it('leaves out the subject of an event that has none', function (): void {
    $user = User::factory()->create();
    WebhookSubscription::factory()->for($user)->create();
    Http::fake(['*' => Http::response('', 204)]);

    app(Webhooks::class)->dispatch($user, new class implements WebhookEvent {
        public function type(): string
        {
            return 'project.created';
        }

        public function subject(): ?string
        {
            return null;
        }

        public function data(): array
        {
            return [];
        }
    });

    Http::assertSent(fn(ClientRequest $request): bool => ! array_key_exists('subject', (array) json_decode($request->body(), true)));
});

it('retries on the published schedule and then fails, recording every attempt', function (): void {
    $user = User::factory()->create();
    WebhookSubscription::factory()->for($user)->create();
    Http::fake(['*' => Http::response('Service Unavailable', 503)]);

    [$delivery] = app(Webhooks::class)->dispatch($user, new ProjectCreated('01jb'));

    $delivery->refresh();
    $attempts = $delivery->attempts()->get();

    expect($delivery->state)->toBe(DeliveryState::Failed);
    expect($delivery->next_attempt_at)->toBeNull();
    expect($attempts->pluck('attempt')->all())->toBe([1, 2, 3]);
    expect($attempts->pluck('response_status')->unique()->all())->toBe([503]);
    expect($delivery->subscription->consecutive_failures)->toBe(1);
});

it('marks a delivery retrying, and queues the next attempt for when it says', function (): void {
    $user = User::factory()->create();
    WebhookSubscription::factory()->for($user)->create();
    Http::fake(['*' => Http::response('Busy', 503)]);
    Queue::fake();
    $this->freezeSecond();

    [$delivery] = app(Webhooks::class)->dispatch($user, new ProjectCreated('01jb'));
    new DeliverWebhook($delivery->id)->handle(app(WebhookTargets::class));

    $delivery->refresh();

    expect($delivery->state)->toBe(DeliveryState::Retrying);
    expect($delivery->next_attempt_at?->equalTo(now()->addSeconds(10)))->toBeTrue();
    Queue::assertPushed(DeliverWebhook::class, fn(DeliverWebhook $job): bool => $job->deliveryId === $delivery->id && 10 === $job->delay);
});

it('stops and disables the subscription when the endpoint answers 410 Gone', function (): void {
    $user = User::factory()->create();
    $subscription = WebhookSubscription::factory()->for($user)->create();
    Http::fake(['*' => Http::response('', 410)]);

    [$delivery] = app(Webhooks::class)->dispatch($user, new ProjectCreated('1'));

    expect($delivery->refresh()->state)->toBe(DeliveryState::Failed);
    expect($delivery->attempts()->count())->toBe(1);
    expect($subscription->refresh()->isEnabled())->toBeFalse();
    expect($subscription->disabled_reason)->toBe(__('api.webhooks.disabled_gone'));
});

it('does not retry a delivery the endpoint answered 415, as the format will not change', function (): void {
    $user = User::factory()->create();
    WebhookSubscription::factory()->for($user)->create();
    Http::fake(['*' => Http::response('', 415)]);

    [$delivery] = app(Webhooks::class)->dispatch($user, new ProjectCreated('1'));

    expect($delivery->refresh()->state)->toBe(DeliveryState::Failed);
    expect($delivery->attempts()->count())->toBe(1);
});

it('waits as long as a 429 Retry-After says, when that is longer than the schedule', function (string $retryAfter, int $expected): void {
    $user = User::factory()->create();
    WebhookSubscription::factory()->for($user)->create();
    Queue::fake();
    $this->freezeSecond();
    Http::fake(['*' => Http::response('slow down', 429, ['Retry-After' => $retryAfter])]);

    [$delivery] = app(Webhooks::class)->dispatch($user, new ProjectCreated('1'));
    new DeliverWebhook($delivery->id)->handle(app(WebhookTargets::class));

    expect($delivery->refresh()->next_attempt_at?->equalTo(now()->addSeconds($expected)))->toBeTrue();
    Queue::assertPushed(DeliverWebhook::class, fn(DeliverWebhook $job): bool => $expected === $job->delay);
})->with([
    'seconds, longer than the schedule' => ['120', 120],
    'seconds, shorter than the schedule' => ['3', 10],
    'an HTTP date' => [fn(): string => now()->addMinutes(5)->toRfc7231String(), 300],
]);

it('records a missing response as a null status, not a zero', function (): void {
    $user = User::factory()->create();
    WebhookSubscription::factory()->for($user)->create();
    config()->set('webhooks.backoff', []);
    Http::fake(fn() => throw new ConnectionException('cURL error 28: Operation timed out'));

    [$delivery] = app(Webhooks::class)->dispatch($user, new ProjectCreated('01jb'));
    $attempt = $delivery->attempts()->sole();

    expect($attempt->response_status)->toBeNull();
    expect($attempt->error)->toContain('timed out');
    expect($delivery->refresh()->state)->toBe(DeliveryState::Failed);
});

it('disables a subscription after repeated failed deliveries, and sends it nothing more', function (): void {
    $user = User::factory()->create();
    $subscription = WebhookSubscription::factory()->for($user)->create();
    config()->set(['webhooks.backoff' => [], 'webhooks.disable_after' => 2]);
    Http::fake(['*' => Http::response('nope', 500)]);

    app(Webhooks::class)->dispatch($user, new ProjectCreated('1'));
    expect($subscription->refresh()->isEnabled())->toBeTrue();

    app(Webhooks::class)->dispatch($user, new ProjectCreated('2'));
    $subscription->refresh();

    expect($subscription->isEnabled())->toBeFalse();
    expect($subscription->disabled_reason)->toBe(__('api.webhooks.disabled_after_failures', ['count' => '2']));
    expect(app(Webhooks::class)->dispatch($user, new ProjectCreated('3')))->toBe([]);
    Http::assertSentCount(2);
});

it('resets the failure count when a delivery succeeds', function (): void {
    $user = User::factory()->create();
    $subscription = WebhookSubscription::factory()->for($user)->create(['consecutive_failures' => 4]);
    Http::fake(['*' => Http::response('', 204)]);

    app(Webhooks::class)->dispatch($user, new ProjectCreated('1'));

    expect($subscription->refresh()->consecutive_failures)->toBe(0);
});

it('sends an event only to subscriptions of its owner that asked for its type', function (): void {
    $user = User::factory()->create();
    $created = WebhookSubscription::factory()->for($user)->create(['events' => ['project.created']]);
    $everything = WebhookSubscription::factory()->for($user)->create(['events' => ['*']]);
    WebhookSubscription::factory()->for($user)->create(['events' => ['project.deleted']]);
    WebhookSubscription::factory()->create(['events' => ['*']]);
    Http::fake(['*' => Http::response('', 200)]);

    $deliveries = app(Webhooks::class)->dispatch($user, new ProjectCreated('1'));

    expect(collect($deliveries)->pluck('webhook_subscription_id')->sort()->values()->all())
        ->toBe(collect([$created->id, $everything->id])->sort()->values()->all());
    expect(StoredEvent::query()->count())->toBe(1);
});

it('refuses to send to an address that turned private after subscribing, without retrying', function (): void {
    $user = User::factory()->create();
    WebhookSubscription::factory()->for($user)->create();
    app(HostResolver::class)->answer = ['169.254.169.254'];
    Http::fake();

    [$delivery] = app(Webhooks::class)->dispatch($user, new ProjectCreated('1'));

    Http::assertNothingSent();
    expect($delivery->refresh()->state)->toBe(DeliveryState::Failed);
    expect($delivery->attempts()->sole()->error)->toBe(__('api.webhooks.target_private'));
});

it('redelivers an event as a new delivery with the same event', function (): void {
    $user = User::factory()->create();
    $subscription = WebhookSubscription::factory()->for($user)->create();
    config()->set('webhooks.backoff', []);
    Http::fake(['*' => Http::sequence()->push('down', 500)->push('', 200)]);

    [$first] = app(Webhooks::class)->dispatch($user, new ProjectCreated('1'));

    $response = $this->withToken($user->createToken('t', ['webhooks:write'])->plainTextToken)
        ->postJson("/v1/webhooks/{$subscription->id}/deliveries/{$first->id}/redeliver")
        ->assertAccepted()
        ->assertJsonPath('data.event.id', $first->webhook_event_id)
        ->assertJsonPath('data.state', 'pending');

    $redelivery = WebhookDelivery::query()->findOrFail($response->json('data.id'));

    expect($redelivery->id)->not->toBe($first->id);
    expect($redelivery->state)->toBe(DeliveryState::Succeeded);
    expect($first->refresh()->state)->toBe(DeliveryState::Failed);
});

it('lists deliveries with every attempt, newest first, to their owner only', function (): void {
    $user = User::factory()->create();
    $subscription = WebhookSubscription::factory()->for($user)->create();
    config()->set('webhooks.backoff', [1]);
    Http::fake(['*' => Http::sequence()->push('down', 502)->push('', 200)]);

    app(Webhooks::class)->dispatch($user, new ProjectCreated('1'));

    $this->withToken($user->createToken('t', ['webhooks:read'])->plainTextToken)
        ->getJson("/v1/webhooks/{$subscription->id}/deliveries")
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.state', 'succeeded')
        ->assertJsonPath('0.attempts.0.response_status', 502)
        ->assertJsonPath('0.attempts.1.response_status', 200);

    app('auth')->forgetGuards();
    $stranger = User::factory()->create();

    $this->withToken($stranger->createToken('t', ['webhooks:read'])->plainTextToken)
        ->getJson("/v1/webhooks/{$subscription->id}/deliveries")
        ->assertProblem(404);
});

it('prunes events past the retention period with their deliveries', function (): void {
    $user = User::factory()->create();
    WebhookSubscription::factory()->for($user)->create();
    Http::fake(['*' => Http::response('', 200)]);

    app(Webhooks::class)->dispatch($user, new ProjectCreated('old'));
    $this->travel(31)->days();
    app(Webhooks::class)->dispatch($user, new ProjectCreated('new'));

    $this->artisan('model:prune', ['--model' => [StoredEvent::class]])->assertSuccessful();

    expect(StoredEvent::query()->pluck('data')->all())->toBe([['project_id' => 'new']]);
    expect(WebhookDelivery::query()->count())->toBe(1);
});

final readonly class ProjectCreated implements WebhookEvent
{
    public function __construct(
        private string $projectId,
    ) {}

    public function type(): string
    {
        return 'project.created';
    }

    public function subject(): string
    {
        return $this->projectId;
    }

    public function data(): array
    {
        return ['project_id' => $this->projectId];
    }
}
