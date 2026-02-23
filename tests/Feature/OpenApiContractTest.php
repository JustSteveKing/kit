<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function generateOpenApiSpec(bool $forceGenerate = false): array
{
    $specPath = base_path('public/docs/openapi.yaml');

    if ($forceGenerate || !File::exists($specPath)) {
        $exitCode = Artisan::call('scribe:generate', [
            '--no-interaction' => true,
        ]);

        expect($exitCode)->toBe(0);
    }

    expect(File::exists($specPath))->toBeTrue();

    /** @var array<string, mixed> $spec */
    $spec = Yaml::parseFile($specPath);

    return $spec;
}

it('generates openapi and documents all v1 auth routes', function (): void {
    $spec = generateOpenApiSpec(forceGenerate: true);
    $paths = $spec['paths'] ?? [];

    expect($spec['openapi'] ?? null)->not->toBeNull();
    expect(is_array($paths))->toBeTrue();

    $documentedOperations = collect($paths)
        ->flatMap(
            fn (array $operations, string $path) => collect($operations)
                ->keys()
                ->filter(fn (string $method) => in_array(strtoupper($method), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'TRACE'], true))
                ->reject(fn (string $method) => strtoupper($method) === 'HEAD')
                ->map(fn (string $method) => strtoupper($method).' '.$path)
        )
        ->sort()
        ->values();

    $routeOperations = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (\Illuminate\Routing\Route $route) => str_starts_with($route->uri(), 'v1/auth'))
        ->flatMap(function (\Illuminate\Routing\Route $route) {
            $uri = '/'.$route->uri();

            return collect($route->methods())
                ->reject(fn (string $method) => $method === 'HEAD')
                ->map(fn (string $method) => strtoupper($method).' '.$uri);
        })
        ->unique()
        ->sort()
        ->values();

    $missingFromSpec = $routeOperations->diff($documentedOperations)->values()->all();
    $extraInSpec = $documentedOperations->diff($routeOperations)->values()->all();

    expect($missingFromSpec)->toBe([]);
    expect($extraInSpec)->toBe([]);
});

it('keeps runtime auth responses aligned with documented openapi responses', function (): void {
    $spec = generateOpenApiSpec();
    /** @var array<string, array<string, array<string, mixed>>> $paths */
    $paths = $spec['paths'] ?? [];

    $loginUser = User::factory()->create([
        'email' => 'login@example.com',
        'password' => 'password123',
    ]);

    $verifyUser = User::factory()->unverified()->create([
        'email' => 'verify@example.com',
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(30),
        [
            'id' => $verifyUser->getKey(),
            'hash' => sha1($verifyUser->getEmailForVerification()),
        ],
    );

    $checks = [
        [
            'method' => 'POST',
            'path' => '/v1/auth/register',
            'response' => $this->postJson('/v1/auth/register', [
                'name' => 'OpenAPI Contract',
                'email' => sprintf('%s@example.com', Str::lower((string) Str::ulid())),
                'password' => 'password123',
                'device_name' => 'contract-test',
            ]),
        ],
        [
            'method' => 'POST',
            'path' => '/v1/auth/login',
            'response' => $this->postJson('/v1/auth/login', [
                'email' => $loginUser->email,
                'password' => 'password123',
                'device_name' => 'contract-test',
            ]),
        ],
        [
            'method' => 'GET',
            'path' => '/v1/auth/me',
            'response' => $this->getJson('/v1/auth/me'),
        ],
        [
            'method' => 'POST',
            'path' => '/v1/auth/logout',
            'response' => $this->postJson('/v1/auth/logout'),
        ],
        [
            'method' => 'POST',
            'path' => '/v1/auth/email/verification-notification',
            'response' => $this->postJson('/v1/auth/email/verification-notification'),
        ],
        [
            'method' => 'GET',
            'path' => '/v1/auth/email/verify/{id}/{hash}',
            'response' => $this->getJson($verificationUrl),
        ],
        [
            'method' => 'POST',
            'path' => '/v1/auth/password/forgot',
            'response' => $this->postJson('/v1/auth/password/forgot', [
                'email' => $loginUser->email,
            ]),
        ],
        [
            'method' => 'GET',
            'path' => '/v1/auth/password/reset/{token}',
            'response' => $this->getJson('/v1/auth/password/reset/contract-token?email=login@example.com'),
        ],
        [
            'method' => 'POST',
            'path' => '/v1/auth/password/reset',
            'response' => $this->postJson('/v1/auth/password/reset', [
                'token' => 'invalid-token',
                'email' => $loginUser->email,
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ]),
        ],
    ];

    foreach ($checks as $check) {
        $method = strtolower($check['method']);
        $path = $check['path'];
        $response = $check['response'];
        $actualStatus = $response->status();
        $documentedResponses = array_map('intval', array_keys($paths[$path][$method]['responses'] ?? []));

        $this->assertContains(
            $actualStatus,
            $documentedResponses,
            sprintf('%s %s returned %d but OpenAPI does not declare it.', strtoupper($method), $path, $actualStatus),
        );
    }
});
