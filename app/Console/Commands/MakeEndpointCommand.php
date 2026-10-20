<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

use Override;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Scaffolds an endpoint the way AGENTS.md describes one: controller with Scribe attributes, Form
 * Request, payload, Pest test and route. What it cannot know is left as TODO, the endpoint answers
 * 501 until it is written, and the contract tests fail while its documentation still says TODO.
 */
final class MakeEndpointCommand extends Command implements PromptsForMissingInput
{
    private const array METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    /**
     * @var string
     */
    #[Override]
    protected $signature = 'make:endpoint
        {name : Domain and action, such as Projects/CreateProject}
        {--method= : GET, POST, PUT, PATCH or DELETE}
        {--uri= : Path under /v1, such as /projects or /projects/{project}}
        {--ability= : Sanctum ability the route requires (default: domain:read for GET, domain:write otherwise)}
        {--guest : Leave the route unauthenticated}
        {--force : Overwrite files that already exist}';

    /**
     * @var string
     */
    #[Override]
    protected $description = 'Scaffold a v1 endpoint: controller, Form Request, payload, test and route';

    public function __construct(
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->stringArgument('name');

        if (1 !== preg_match('/^([A-Z][A-Za-z0-9]*)\/([A-Z][A-Za-z0-9]*)$/', $name, $parts)) {
            $this->components->error('The name is a domain and an action in StudlyCase, such as Projects/CreateProject.');

            return self::FAILURE;
        }

        [, $domain, $action] = $parts;
        $action = Str::replaceEnd('Controller', '', $action);
        $method = mb_strtoupper($this->stringOption('method') ?: 'GET');

        if ( ! in_array($method, self::METHODS, true)) {
            $this->components->error('The method is one of ' . implode(', ', self::METHODS) . '.');

            return self::FAILURE;
        }

        $uri = '/' . mb_ltrim($this->stringOption('uri') ?: Str::kebab($domain), '/');
        $guest = (bool) $this->option('guest');
        $ability = $guest ? null : ($this->stringOption('ability') ?: Str::kebab($domain) . ':' . ('GET' === $method ? 'read' : 'write'));
        $hasRequest = 'DELETE' !== $method;

        $endpoint = new EndpointBlueprint($domain, $action, $method, $uri, $ability, $hasRequest);

        $files = [
            app_path("Http/Controllers/Api/V1/{$domain}/{$action}Controller.php") => $this->controller($endpoint),
            base_path("tests/Feature/{$domain}/{$action}Test.php") => $this->test($endpoint),
        ];

        if ($hasRequest) {
            $files[app_path("Http/Requests/{$domain}/{$action}Request.php")] = $this->request($endpoint);
            $files[app_path("Http/Payloads/V1/{$action}Payload.php")] = $this->payload($endpoint);
        }

        $existing = array_filter(array_keys($files), $this->files->exists(...));

        if ([] !== $existing && ! $this->option('force')) {
            $this->components->error('These files already exist; pass --force to overwrite them:');
            $this->components->bulletList(array_map($this->relative(...), $existing));

            return self::FAILURE;
        }

        foreach ($files as $path => $contents) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $contents);
        }

        $this->addRoute($endpoint);
        $this->format([...array_keys($files), base_path('routes/api/v1.php')]);

        $this->components->info("Scaffolded {$method} /v1{$uri}.");
        $this->components->bulletList([...array_map($this->relative(...), array_keys($files)), 'routes/api/v1.php']);
        $this->nextSteps($endpoint);

        return self::SUCCESS;
    }

    /**
     * @return array<string, Closure(): string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => fn(): string => text(
                label: 'Which domain and action?',
                placeholder: 'Projects/CreateProject',
                required: true,
            ),
        ];
    }

    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        if (null === $input->getOption('method')) {
            $input->setOption('method', select('Which HTTP method?', self::METHODS, default: 'POST'));
        }

        if (null === $input->getOption('uri')) {
            $name = $input->getArgument('name');
            $domain = Str::before(is_string($name) ? $name : '', '/');
            $input->setOption('uri', text('Which path under /v1?', default: '/' . Str::kebab($domain), required: true));
        }
    }

    private function controller(EndpointBlueprint $endpoint): string
    {
        $imports = [
            JsonResponse::class,
            Endpoint::class,
            Group::class,
            Response::class,
        ];
        $attributes = [];
        $parameters = [];
        $body = [];

        if ($endpoint->hasRequest) {
            $imports[] = "App\\Http\\Requests\\{$endpoint->domain}\\{$endpoint->action}Request";
            $imports[] = "App\\Http\\Payloads\\V1\\{$endpoint->action}Payload";
            $parameters[] = "{$endpoint->action}Request \$request";
            $body[] = "        \$payload = {$endpoint->action}Payload::fromRequest(\$request);";
            $body[] = '';
        }

        foreach ($endpoint->pathParameters() as $parameter) {
            $imports[] = UrlParam::class;
            $attributes[] = "#[UrlParam('{$parameter}', type: 'string', description: 'TODO: Describe {$parameter}.', required: true, example: 'TODO')]";
        }

        if (null === $endpoint->ability) {
            $imports[] = Unauthenticated::class;
            $attributes[] = '#[Unauthenticated]';
        } else {
            $imports[] = Authenticated::class;
            $attributes[] = '#[Authenticated]';
            $attributes[] = $this->problemAttribute(401, 'unauthorized', 'Unauthorized', 'Unauthenticated.', 'Authentication failed.');
            $attributes[] = $this->problemAttribute(403, 'forbidden', 'Forbidden', 'Forbidden.', "Token is missing the {$endpoint->ability} ability.");
        }

        // The route takes the idempotent middleware, so the documentation says what the header does.
        if ('POST' === $endpoint->method) {
            $imports[] = Header::class;
            $attributes[] = "#[Header('Idempotency-Key', '\"request-retry-1\"')]";
        }

        if ($endpoint->hasRequest) {
            $attributes[] = $this->problemAttribute(422, 'validation-failed', 'Validation Failed', 'TODO: An example validation message.', 'TODO: Say what input is refused.', withErrors: true);
        }

        if ([] === $parameters) {
            $imports[] = Request::class;
            $parameters[] = 'Request $request';
        }

        sort($imports);

        return $this->render('controller', [
            'namespace' => "App\\Http\\Controllers\\Api\\V1\\{$endpoint->domain}",
            'imports' => implode("\n", array_map(static fn(string $import): string => "use {$import};", array_unique($imports))),
            'group' => Str::headline($endpoint->domain),
            'title' => Str::headline($endpoint->action),
            'attributes' => implode("\n", $attributes),
            'class' => "{$endpoint->action}Controller",
            'parameters' => implode(', ', $parameters),
            'body' => implode("\n", $body),
        ]);
    }

    private function request(EndpointBlueprint $endpoint): string
    {
        // Scribe documents a Form Request's rules as body parameters unless it has a
        // queryParameters() method, and a GET has no body.
        $queryParameters = 'GET' !== $endpoint->method ? '' : <<<'PHP'

    /**
     * Read by Scribe, so this GET's rules are documented as query parameters, not a body.
     *
     * @return array<string, array{description: string, example: mixed}>
     */
    public function queryParameters(): array
    {
        return [
            // 'status' => ['description' => 'Only projects in this status.', 'example' => 'active'],
        ];
    }

PHP;

        return $this->render('request', [
            'namespace' => "App\\Http\\Requests\\{$endpoint->domain}",
            'class' => "{$endpoint->action}Request",
            'queryParameters' => $queryParameters,
        ]);
    }

    private function payload(EndpointBlueprint $endpoint): string
    {
        return $this->render('payload', [
            'namespace' => 'App\Http\Payloads\V1',
            'class' => "{$endpoint->action}Payload",
        ]);
    }

    private function test(EndpointBlueprint $endpoint): string
    {
        $call = $this->testCall($endpoint);
        $guardTests = '';

        if (null !== $endpoint->ability) {
            $guardTests = <<<PHP

it('requires authentication', function (): void {
    \$this->{$call}
        ->assertProblem(401);
});

it('requires the {$endpoint->ability} ability', function (): void {
    \$token = User::factory()->create()->createToken('test', ['auth:me'])->plainTextToken;

    \$this->withToken(\$token)
        ->{$call}
        ->assertProblem(403);
});

PHP;
        }

        return $this->render('test', [
            'successDescription' => mb_strtolower(Str::headline($endpoint->action)) . ' successfully',
            'method' => $endpoint->method,
            'uri' => '/v1' . $endpoint->uri,
            'abilityOrNone' => $endpoint->ability ?? 'no ability (the route is public)',
            'guardTests' => $guardTests,
        ]);
    }

    private function testCall(EndpointBlueprint $endpoint): string
    {
        $uri = preg_replace('/\{(\w+)\}/', '1', '/v1' . $endpoint->uri);
        $verb = mb_strtolower($endpoint->method);

        return in_array($endpoint->method, ['POST', 'PUT', 'PATCH'], true)
            ? "{$verb}Json('{$uri}', [])"
            : "{$verb}Json('{$uri}')";
    }

    private function addRoute(EndpointBlueprint $endpoint): void
    {
        $path = base_path('routes/api/v1.php');
        $routes = $this->files->get($path);
        $controller = "App\\Http\\Controllers\\Api\\V1\\{$endpoint->domain}\\{$endpoint->action}Controller";

        $middleware = [];

        if (null !== $endpoint->ability) {
            $middleware = ['auth:sanctum', 'throttle:auth-protected', "abilities:{$endpoint->ability}"];
        }

        if ('POST' === $endpoint->method) {
            $middleware[] = 'idempotent';
        }

        $route = "\nRoute::" . mb_strtolower($endpoint->method) . "('{$endpoint->uri}', {$endpoint->action}Controller::class)";

        foreach ($endpoint->pathParameters() as $parameter) {
            $route .= "\n    // TODO: Constrain {$parameter}, such as ->whereNumber('{$parameter}') or ->whereUlid('{$parameter}').";
        }

        if ([] !== $middleware) {
            $route .= "\n    ->middleware(['" . implode("', '", $middleware) . "'])";
        }

        $route .= "\n    ->name('{$endpoint->routeName()}');\n";

        $routes = preg_replace('/^(use [^;]+;\n)(?!use )/m', "$1use {$controller};\n", $routes, 1) ?? $routes;

        $this->files->put($path, mb_rtrim($routes) . "\n" . $route);
    }

    /**
     * Runs the project's Pint over what was written, so the output follows pint.json whatever the
     * stubs look like.
     *
     * @param  list<string>  $paths
     */
    private function format(array $paths): void
    {
        $pint = base_path('vendor/bin/pint');

        if ( ! $this->files->exists($pint)) {
            return;
        }

        Process::path(base_path())->run([PHP_BINARY, $pint, '--quiet', ...$paths]);
    }

    private function nextSteps(EndpointBlueprint $endpoint): void
    {
        $steps = [
            'Write the rules, the payload, the work and the response in place of each TODO and the 501.',
            'Replace the TODO description: what it does, when to call it, what it returns. CI fails while it says TODO.',
        ];

        if ([] !== $endpoint->pathParameters()) {
            $steps[] = 'Constrain the path parameters in routes/api/v1.php; validate them there, not in the Form Request.';
        }

        if (null !== $endpoint->ability) {
            $steps[] = "Add '{$endpoint->ability}' to sanctum.abilities.available in config/sanctum.php, and to default if every session token should hold it.";
        }

        $steps[] = "Add a runtime check for {$endpoint->method} /v1{$endpoint->uri} to OpenApiContractTest.";

        if ($endpoint->hasRequest) {
            $steps[] = "Add {$endpoint->method} /v1{$endpoint->uri} to the error-shape ignore in .legible.yaml: its validation problems carry errors.";
        }
        $steps[] = 'Run: php artisan scribe:generate, then composer stan and php artisan test.';

        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>Next</>');
        $this->components->bulletList($steps);
    }

    private function problemAttribute(int $status, string $slug, string $title, string $detail, string $description, bool $withErrors = false): string
    {
        $errors = $withErrors ? "\n        'errors' => ['field' => ['{$detail}']]," : '';

        return <<<PHP
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/{$slug}',
        'title' => '{$title}',
        'status' => {$status},
        'detail' => '{$detail}',{$errors}
    ],
    status: {$status},
    description: '{$description}',
)]
PHP;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function render(string $stub, array $replacements): string
    {
        $path = base_path("stubs/endpoint/{$stub}.stub");

        if ( ! $this->files->exists($path)) {
            throw new InvalidArgumentException("The stub [stubs/endpoint/{$stub}.stub] is missing.");
        }

        $contents = $this->files->get($path);

        foreach ($replacements as $key => $value) {
            $contents = str_replace('{{ ' . $key . ' }}', $value, $contents);
        }

        return $contents;
    }

    private function relative(string $path): string
    {
        return mb_ltrim(Str::after($path, base_path()), '/');
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? mb_trim($value) : '';
    }

    private function stringOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? mb_trim($value) : '';
    }
}
