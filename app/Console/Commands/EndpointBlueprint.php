<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Support\Str;

/**
 * What make:endpoint knows about the endpoint it is scaffolding.
 */
final readonly class EndpointBlueprint
{
    private const array ACTIONS = [
        'Create' => 'store',
        'Store' => 'store',
        'List' => 'index',
        'Index' => 'index',
        'Show' => 'show',
        'Get' => 'show',
        'Update' => 'update',
        'Delete' => 'destroy',
        'Destroy' => 'destroy',
    ];

    public function __construct(
        public string $domain,
        public string $action,
        public string $method,
        public string $uri,
        public ?string $ability,
        public bool $hasRequest,
    ) {}

    /**
     * v1.{domain}.{action}, with dashes rather than dots inside each part, and the action named the
     * way Laravel's resource routes are where the verb says which one it is.
     */
    public function routeName(): string
    {
        $verb = Str::before(Str::snake($this->action), '_');
        $action = self::ACTIONS[Str::studly($verb)] ?? Str::kebab($this->action);

        return 'v1.' . Str::kebab($this->domain) . '.' . $action;
    }

    /**
     * @return list<string>
     */
    public function pathParameters(): array
    {
        preg_match_all('/\{(\w+)\??\}/', $this->uri, $matches);

        return $matches[1];
    }
}
