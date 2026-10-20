<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // A route bound to a model, as the HTTP standards guide recommends for ETags and If-Match.
    Route::bind('member', fn(string $id): User => User::query()->findOrFail($id));
    Route::prefix('v1')->middleware('api')->group(function (): void {
        Route::get('/test/members/{member}', fn(User $member): array => ['id' => $member->id])
            ->middleware(['auth:sanctum', 'throttle:auth-protected', 'abilities:auth:me']);
        Route::post('/test/members', fn(): array => ['created' => true])
            ->middleware(['auth:sanctum', 'throttle:auth-protected', 'abilities:auth:tokens:create', 'idempotent']);
    });
});

it('refuses a token without the ability before binding the route, whether or not the record exists', function (): void {
    $member = User::factory()->create();
    $token = User::factory()->create()->createToken('test', ['webhooks:read'])->plainTextToken;

    $this->withToken($token)->getJson('/v1/test/members/' . $member->id)->assertProblem(403);
    $this->withToken($token)->getJson('/v1/test/members/01aaaaaaaaaaaaaaaaaaaaaaaa')->assertProblem(403);
});

it('still binds for a token with the ability', function (): void {
    $token = User::factory()->create()->createToken('test', ['auth:me'])->plainTextToken;

    $this->withToken($token)->getJson('/v1/test/members/01aaaaaaaaaaaaaaaaaaaaaaaa')->assertProblem(404);
});

it('does not store a refusal for an ability as an idempotent response', function (): void {
    $user = User::factory()->create();
    $headers = ['Idempotency-Key' => '"members-create-1"'];

    $this->withToken($user->createToken('read', ['auth:me'])->plainTextToken)->postJson('/v1/test/members', [], $headers)->assertProblem(403);

    app('auth')->forgetGuards();

    $this->withToken($user->createToken('write', ['auth:tokens:create'])->plainTextToken)->postJson('/v1/test/members', [], $headers)
        ->assertOk()
        ->assertJsonPath('created', true);
});
