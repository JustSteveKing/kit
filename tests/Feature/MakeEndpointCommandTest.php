<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

/*
 * The command writes into a temporary copy of the project's stubs and v1 routes, never the real
 * files: the suite runs in parallel, and other tests load routes/api/v1.php.
 */
beforeEach(function (): void {
    $this->files = new Filesystem();
    $this->base = sys_get_temp_dir() . '/kit-make-endpoint-' . bin2hex(random_bytes(6));

    $this->files->copyDirectory(base_path('stubs'), "{$this->base}/stubs");
    $this->files->ensureDirectoryExists("{$this->base}/routes/api");
    $this->files->copy(base_path('routes/api/v1.php'), "{$this->base}/routes/api/v1.php");

    $this->originalBase = base_path();
    $this->originalApp = app_path();
    app()->setBasePath($this->base);
    app()->useAppPath("{$this->base}/app");
});

afterEach(function (): void {
    app()->setBasePath($this->originalBase);
    app()->useAppPath($this->originalApp);
    $this->files->deleteDirectory($this->base);
});

it('scaffolds the six pieces of an authenticated endpoint', function (): void {
    $this->artisan('make:endpoint', [
        'name' => 'Projects/CreateProject',
        '--method' => 'POST',
        '--uri' => '/projects',
    ])->assertSuccessful();

    foreach ([
        'app/Http/Controllers/Api/V1/Projects/CreateProjectController.php',
        'app/Http/Requests/Projects/CreateProjectRequest.php',
        'app/Http/Payloads/V1/CreateProjectPayload.php',
        'tests/Feature/Projects/CreateProjectTest.php',
    ] as $file) {
        expect("{$this->base}/{$file}")->toBeFile();
        expect(php_strip_whitespace("{$this->base}/{$file}"))->not->toBe('');
    }

    $controller = (string) file_get_contents("{$this->base}/app/Http/Controllers/Api/V1/Projects/CreateProjectController.php");

    expect($controller)
        ->toContain('final class CreateProjectController')
        ->toContain('#[Authenticated]')
        ->toContain("'status' => 401")
        ->toContain("'status' => 403")
        ->toContain("'status' => 422")
        ->toContain('CreateProjectPayload::fromRequest($request)')
        ->toContain("abort(501, 'TODO: Implement Create Project.')");

    $routes = (string) file_get_contents("{$this->base}/routes/api/v1.php");

    expect($routes)
        ->toContain('use App\Http\Controllers\Api\V1\Projects\CreateProjectController;')
        ->toContain("Route::post('/projects', CreateProjectController::class)")
        ->toContain("'auth:sanctum', 'throttle:auth-protected', 'abilities:projects:write', 'idempotent'")
        ->toContain("->name('v1.projects.store')");

    expect((string) file_get_contents("{$this->base}/tests/Feature/Projects/CreateProjectTest.php"))
        ->toContain("it('requires the projects:write ability'")
        ->toContain('->todo();');
});

it('documents a GET request as query parameters and leaves path parameters to the route', function (): void {
    $this->artisan('make:endpoint', [
        'name' => 'Projects/ShowProject',
        '--method' => 'GET',
        '--uri' => '/projects/{project}',
        '--ability' => 'projects:view',
    ])->assertSuccessful();

    expect((string) file_get_contents("{$this->base}/app/Http/Requests/Projects/ShowProjectRequest.php"))
        ->toContain('public function queryParameters(): array');

    expect((string) file_get_contents("{$this->base}/app/Http/Controllers/Api/V1/Projects/ShowProjectController.php"))
        ->toContain("#[UrlParam('project'");

    expect((string) file_get_contents("{$this->base}/routes/api/v1.php"))
        ->toContain("// TODO: Constrain project")
        ->toContain("'abilities:projects:view'")
        ->toContain("->name('v1.projects.show')")
        ->not->toContain("'abilities:projects:view', 'idempotent'");
});

it('scaffolds a public endpoint without auth, and a DELETE without a Form Request', function (): void {
    $this->artisan('make:endpoint', ['name' => 'Status/ShowStatus', '--method' => 'GET', '--uri' => '/status', '--guest' => true])
        ->assertSuccessful();
    $this->artisan('make:endpoint', ['name' => 'Projects/DeleteProject', '--method' => 'DELETE', '--uri' => '/projects/{project}'])
        ->assertSuccessful();

    expect((string) file_get_contents("{$this->base}/app/Http/Controllers/Api/V1/Status/ShowStatusController.php"))
        ->toContain('#[Unauthenticated]')
        ->not->toContain("'status' => 401");

    expect("{$this->base}/app/Http/Requests/Projects/DeleteProjectRequest.php")->not->toBeFile();
    expect((string) file_get_contents("{$this->base}/routes/api/v1.php"))
        ->toContain("Route::get('/status', ShowStatusController::class)\n    ->name('v1.status.show');")
        ->toContain("->name('v1.projects.destroy')");
});

it('refuses to overwrite an endpoint unless forced', function (): void {
    $arguments = ['name' => 'Projects/CreateProject', '--method' => 'POST', '--uri' => '/projects'];

    $this->artisan('make:endpoint', $arguments)->assertSuccessful();
    $this->artisan('make:endpoint', $arguments)->assertFailed();
    $this->artisan('make:endpoint', [...$arguments, '--force' => true])->assertSuccessful();
});

it('rejects names and methods it cannot scaffold', function (string $name, string $method): void {
    $this->artisan('make:endpoint', ['name' => $name, '--method' => $method, '--uri' => '/things'])->assertFailed();
})->with([
    'no domain' => ['CreateProject', 'POST'],
    'not studly' => ['projects/create', 'POST'],
    'unknown method' => ['Projects/CreateProject', 'TRACE'],
]);
