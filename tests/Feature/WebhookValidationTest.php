<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WebhookSubscription;
use App\Webhooks\HostResolver;
use App\Webhooks\WebhookEvent;
use App\Webhooks\Webhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

/*
 * The CloudEvents webhook handshake (section 4), on when webhooks.validate_endpoints is.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set([
        'webhooks.validate_endpoints' => true,
        'webhooks.origin' => 'api.example.test',
        'webhooks.require_https' => true,
        'webhooks.allow_private_networks' => false,
        'webhooks.events' => ['project.created'],
    ]);

    app()->instance(HostResolver::class, new class implements HostResolver {
        public function addresses(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
});

function consentingEndpoint(?string $allowedOrigin): void
{
    Http::fake(fn(ClientRequest $request) => 'OPTIONS' === $request->method()
        ? Http::response('', 200, array_filter(['WebHook-Allowed-Origin' => $allowedOrigin, 'WebHook-Allowed-Rate' => '*']))
        : Http::response('', 204));
}

function subscribe(User $user, string $url = 'https://hooks.example.com/kit'): WebhookSubscription
{
    $id = test()->withToken($user->createToken('t', ['webhooks:write'])->plainTextToken)
        ->postJson('/v1/webhooks', ['url' => $url, 'events' => ['*']])
        ->assertCreated()
        ->json('data.id');

    return WebhookSubscription::query()->findOrFail($id);
}

it('validates a new subscription when the endpoint grants this origin', function (string $granted): void {
    consentingEndpoint($granted);

    $subscription = subscribe(User::factory()->create());

    expect($subscription->validated_at)->not->toBeNull();
    Http::assertSent(fn(ClientRequest $request): bool => 'OPTIONS' === $request->method()
        && 'https://hooks.example.com/kit' === $request->url()
        && 'api.example.test' === $request->header('WebHook-Request-Origin')[0]);
})->with(['this origin' => 'api.example.test', 'any origin' => '*']);

it('does not take a success status, or another origin, as consent', function (?string $granted): void {
    consentingEndpoint($granted);

    $user = User::factory()->create();
    $subscription = subscribe($user);

    expect($subscription->validated_at)->toBeNull();
    expect(app(Webhooks::class)->dispatch($user, new ProjectCreatedForValidation()))->toBe([]);

    $this->withToken($user->createToken('t', ['webhooks:write'])->plainTextToken)
        ->postJson('/v1/webhooks/' . $subscription->id . '/ping')
        ->assertProblemType('https://apiguide.dev/errors/resource-conflict', 409)
        ->assertJsonPath('detail', __('api.webhooks.not_validated'));
})->with(['no header' => null, 'another origin' => 'someone-else.example']);

it('needs consent again when the url changes, and can be asked again', function (): void {
    consentingEndpoint('api.example.test');
    $user = User::factory()->create();
    $subscription = subscribe($user);
    $token = $user->createToken('t', ['webhooks:write'])->plainTextToken;

    consentingEndpoint(null);

    $this->withToken($token)
        ->patchJson('/v1/webhooks/' . $subscription->id, ['url' => 'https://hooks.example.com/moved'])
        ->assertOk()
        ->assertJsonPath('data.validated_at', null);

    consentingEndpoint('api.example.test');

    $this->withToken($token)
        ->postJson('/v1/webhooks/' . $subscription->id . '/validate')
        ->assertAccepted();

    expect($subscription->refresh()->validated_at)->not->toBeNull();
});

it('delivers to a validated endpoint', function (): void {
    consentingEndpoint('api.example.test');
    $user = User::factory()->create();
    subscribe($user);

    expect(app(Webhooks::class)->dispatch($user, new ProjectCreatedForValidation()))->toHaveCount(1);
    Http::assertSent(fn(ClientRequest $request): bool => 'POST' === $request->method());
});

it('delivers without consent when validation is off', function (): void {
    config()->set('webhooks.validate_endpoints', false);
    Http::fake(['*' => Http::response('', 204)]);
    $user = User::factory()->create();
    $subscription = subscribe($user);

    expect($subscription->validated_at)->toBeNull();
    expect(app(Webhooks::class)->dispatch($user, new ProjectCreatedForValidation()))->toHaveCount(1);
    Http::assertNotSent(fn(ClientRequest $request): bool => 'OPTIONS' === $request->method());
});

/**
 * The WebHook-Request-Callback URL sent with the subscription's validation request.
 */
function consentCallbackFor(WebhookSubscription $subscription): string
{
    $sent = Http::recorded(fn(ClientRequest $request): bool => 'OPTIONS' === $request->method() && $request->url() === $subscription->url);

    expect($sent)->not->toBeEmpty();

    return (string) $sent->last()[0]->header('WebHook-Request-Callback')[0];
}

it('sends a consent callback that validates the subscription by GET or POST', function (string $method): void {
    consentingEndpoint(null);
    $subscription = subscribe(User::factory()->create());
    $callback = consentCallbackFor($subscription);

    expect($callback)->toStartWith(route('v1.webhooks.consent', ['webhook' => $subscription->id]));
    expect($subscription->validated_at)->toBeNull();

    $this->json($method, $callback)
        ->assertOk()
        ->assertJsonPath('message', __('api.webhooks.consent_granted'));

    expect($subscription->refresh()->validated_at)->not->toBeNull();

    $this->json($method, $callback)->assertOk();
})->with(['GET', 'POST']);

it('refuses a consent link that was altered or has expired', function (): void {
    consentingEndpoint(null);
    $subscription = subscribe(User::factory()->create());
    $callback = consentCallbackFor($subscription);

    $this->getJson(str_replace('url_hash=', 'url_hash=0', $callback))
        ->assertProblem(403)
        ->assertJsonPath('detail', __('api.webhooks.invalid_consent_link'));

    $this->travel(73)->hours();

    $this->getJson($callback)->assertProblem(403);
    expect($subscription->refresh()->validated_at)->toBeNull();
});

it('refuses consent given for a url the subscription no longer uses', function (): void {
    consentingEndpoint(null);
    $user = User::factory()->create();
    $subscription = subscribe($user);
    $callback = consentCallbackFor($subscription);

    $this->withToken($user->createToken('t', ['webhooks:write'])->plainTextToken)
        ->patchJson('/v1/webhooks/' . $subscription->id, ['url' => 'https://hooks.example.com/moved'])
        ->assertOk();

    $this->getJson($callback)
        ->assertProblemType('https://apiguide.dev/errors/resource-conflict', 409)
        ->assertJsonPath('detail', __('api.webhooks.consent_url_changed'));

    expect($subscription->refresh()->validated_at)->toBeNull();
});

it('answers 404 to a consent link for a deleted subscription', function (): void {
    consentingEndpoint(null);
    $subscription = subscribe(User::factory()->create());
    $callback = consentCallbackFor($subscription);
    $subscription->delete();

    $this->getJson($callback)->assertProblem(404);
});

final readonly class ProjectCreatedForValidation implements WebhookEvent
{
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
}
