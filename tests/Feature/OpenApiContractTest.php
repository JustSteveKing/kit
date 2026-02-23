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

/**
 * @param  array<string, mixed>  $spec
 * @return array<string, mixed>|null
 */
function documentedResponseSchema(array $spec, string $path, string $method, int $status): ?array
{
    $operation = $spec['paths'][$path][strtolower($method)] ?? null;
    if (! is_array($operation)) {
        return null;
    }

    $response = $operation['responses'][(string) $status] ?? null;
    if (! is_array($response)) {
        return null;
    }

    $content = $response['content'] ?? null;
    if (! is_array($content) || $content === []) {
        return null;
    }

    $mediaType = $content['application/json'] ?? reset($content);
    if (! is_array($mediaType)) {
        return null;
    }

    $schema = $mediaType['schema'] ?? null;

    return is_array($schema) ? $schema : null;
}

/**
 * @param  array<string, mixed>  $spec
 * @param  array<string, mixed>  $schema
 * @return array<string, mixed>
 */
function resolveOpenApiSchema(array $spec, array $schema): array
{
    $ref = $schema['$ref'] ?? null;
    if (! is_string($ref) || ! str_starts_with($ref, '#/')) {
        return $schema;
    }

    $segments = explode('/', ltrim($ref, '#/'));
    $resolved = $spec;

    foreach ($segments as $segment) {
        if (! is_array($resolved) || ! array_key_exists($segment, $resolved)) {
            return $schema;
        }

        $resolved = $resolved[$segment];
    }

    return is_array($resolved) ? $resolved : $schema;
}

/**
 * @param  array<string, mixed>  $spec
 * @param  array<string, mixed>  $schema
 * @param  mixed  $value
 */
function assertMatchesOpenApiSchema(array $spec, array $schema, mixed $value, string $path = '$'): void
{
    $resolvedSchema = resolveOpenApiSchema($spec, $schema);

    if (($resolvedSchema['nullable'] ?? false) === true && $value === null) {
        return;
    }

    if (isset($resolvedSchema['oneOf']) && is_array($resolvedSchema['oneOf'])) {
        foreach ($resolvedSchema['oneOf'] as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            try {
                assertMatchesOpenApiSchema($spec, $candidate, $value, $path);

                return;
            } catch (\Throwable) {
                continue;
            }
        }

        test()->fail(sprintf('Value at %s did not match any oneOf schema.', $path));
    }

    if (isset($resolvedSchema['anyOf']) && is_array($resolvedSchema['anyOf'])) {
        foreach ($resolvedSchema['anyOf'] as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            try {
                assertMatchesOpenApiSchema($spec, $candidate, $value, $path);

                return;
            } catch (\Throwable) {
                continue;
            }
        }

        test()->fail(sprintf('Value at %s did not match any anyOf schema.', $path));
    }

    $type = $resolvedSchema['type'] ?? null;
    if (! is_string($type)) {
        return;
    }

    match ($type) {
        'object' => assertOpenApiObject($spec, $resolvedSchema, $value, $path),
        'array' => assertOpenApiArray($spec, $resolvedSchema, $value, $path),
        'string' => test()->assertIsString($value, sprintf('Expected string at %s.', $path)),
        'integer' => test()->assertIsInt($value, sprintf('Expected integer at %s.', $path)),
        'number' => test()->assertTrue(is_int($value) || is_float($value), sprintf('Expected number at %s.', $path)),
        'boolean' => test()->assertIsBool($value, sprintf('Expected boolean at %s.', $path)),
        default => null,
    };
}

/**
 * @param  array<string, mixed>  $spec
 * @param  array<string, mixed>  $schema
 * @param  mixed  $value
 */
function assertOpenApiObject(array $spec, array $schema, mixed $value, string $path): void
{
    test()->assertIsArray($value, sprintf('Expected object-like array at %s.', $path));
    test()->assertFalse(array_is_list($value), sprintf('Expected object at %s, got list.', $path));

    $required = $schema['required'] ?? [];
    if (is_array($required)) {
        foreach ($required as $requiredProperty) {
            if (is_string($requiredProperty)) {
                test()->assertArrayHasKey($requiredProperty, $value, sprintf('Missing required property %s.%s', $path, $requiredProperty));
            }
        }
    }

    $properties = $schema['properties'] ?? [];
    if (! is_array($properties)) {
        return;
    }

    foreach ($properties as $propertyName => $propertySchema) {
        if (! is_string($propertyName) || ! is_array($propertySchema) || ! array_key_exists($propertyName, $value)) {
            continue;
        }

        $isRequired = is_array($required) && in_array($propertyName, $required, true);
        if ($value[$propertyName] === null && ! $isRequired && ($propertySchema['nullable'] ?? false) !== true) {
            continue;
        }

        assertMatchesOpenApiSchema($spec, $propertySchema, $value[$propertyName], sprintf('%s.%s', $path, $propertyName));
    }
}

/**
 * @param  array<string, mixed>  $spec
 * @param  array<string, mixed>  $schema
 * @param  mixed  $value
 */
function assertOpenApiArray(array $spec, array $schema, mixed $value, string $path): void
{
    test()->assertIsArray($value, sprintf('Expected array at %s.', $path));
    test()->assertTrue(array_is_list($value), sprintf('Expected list array at %s.', $path));

    $itemSchema = $schema['items'] ?? null;
    if (! is_array($itemSchema)) {
        return;
    }

    foreach ($value as $index => $item) {
        assertMatchesOpenApiSchema($spec, $itemSchema, $item, sprintf('%s[%d]', $path, $index));
    }
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

        $schema = documentedResponseSchema($spec, $path, strtoupper($method), $actualStatus);
        if ($schema === null) {
            continue;
        }

        $rawContent = $response->getContent();
        if ($rawContent === false || $rawContent === '') {
            continue;
        }

        $payload = json_decode($rawContent, true);
        $this->assertNotNull($payload, sprintf('Expected JSON response body for %s %s status %d.', strtoupper($method), $path, $actualStatus));
        assertMatchesOpenApiSchema($spec, $schema, $payload, '$');
    }
});
