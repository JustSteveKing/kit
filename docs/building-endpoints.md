# Building endpoints

Every endpoint in Kit is built from the same six pieces: a route, a Form Request, a payload, an
invokable controller, a resource and a test. `make:endpoint` writes all of them, so that is where
every new endpoint starts.

The examples on this page add a `projects` domain. Kit has no `Project` model, so make one first
(`php artisan make:model Project -mf`) if you are following along.

## Generate it

```bash
php artisan make:endpoint Projects/CreateProject --method=POST --uri=/projects
php artisan make:endpoint Projects/ShowProject --method=GET --uri=/projects/{project}
```

The name is `{Domain}/{Action}`. The options:

| Option | Default | Effect |
| --- | --- | --- |
| `--method=` | | `GET`, `POST`, `PUT`, `PATCH` or `DELETE` |
| `--uri=` | | The path under `/v1` |
| `--ability=` | `projects:read` for a GET, `projects:write` otherwise | The Sanctum ability the route checks |
| `--guest` | | Leaves the route public, with no token or ability |
| `--force` | | Overwrites files that already exist |

It writes the controller, the payload, a Pest test and, for anything but a DELETE, a Form Request.
Then it appends the route to `routes/api/v1.php`, runs Pint over the new files and prints what is
left for you to do. The templates live in `stubs/endpoint/`, and every endpoint you generate after
changing them follows suit.

Anything the generator cannot know stays visible. The controller answers `501`, each path parameter
has a TODO asking for a constraint, and the description is three TODO sentences. Both the contract
tests and legible fail while that documentation is still there, so a half-written endpoint does not
get through CI.

Finish the endpoint before you run `composer rector`. To Rector, the generator's placeholders (an
unused payload, an empty constructor) are dead code, and it deletes them.

## The route

The generator appends something like this:

```php
Route::post('/projects', CreateProjectController::class)
    ->middleware(['auth:sanctum', 'throttle:auth-protected', 'abilities:projects:write', 'idempotent'])
    ->name('v1.projects.store');
```

- `routes/api/routes.php` adds the `v1` prefix, so the path here is `/projects` and the URL is
  `/v1/projects`. There is no `/api` prefix anywhere.
- Names are `v1.{domain}.{action}`. The generator maps verbs to Laravel's resource names, so
  `CreateProject` becomes `store` and `ShowProject` becomes `show`.
- A POST gets `idempotent`, so clients can retry it safely with an `Idempotency-Key`. See
  [HTTP standards](http-standards.md#idempotency).
- New routes share the `auth-protected` limiter. When a domain needs its own limit, add a named
  limiter in `AppServiceProvider::boot()` and use that instead.

Then do the two things the generator prints:

1. Add the ability to `abilities.available` in `config/sanctum.php`, and to `abilities.default` if
   every signed-in client should hold it. Until you do, nobody can call the route. See
   [Authentication](authentication.md#abilities).
2. Constrain path parameters on the route itself, never in the Form Request:

   ```php
   Route::get('/projects/{project}', ShowProjectController::class)
       ->whereUlid('project')
       ->middleware(['auth:sanctum', 'throttle:auth-protected', 'abilities:projects:read'])
       ->name('v1.projects.show');
   ```

   A Form Request that validates a path parameter also documents it as a body field, and legible
   reports the collision.

## The Form Request

`app/Http/Requests/Projects/CreateProjectRequest.php` validates body or query input, and nothing
else:

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:2000'],
    ];
}
```

A GET's Form Request also has a `queryParameters()` method describing each query parameter. Without
it, Scribe documents a GET's rules as a request body. `ShowResetPasswordTokenRequest` is an example.

## The payload

`app/Http/Payloads/V1/CreateProjectPayload.php` turns validated input into typed properties, so the
controller never touches `$request->input()`:

```php
final readonly class CreateProjectPayload
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = self::validatedData($request);

        return new self(
            name: Arr::string($data, 'name'),
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
        );
    }

    // validatedData() as generated
}
```

PHPStan runs at level 10 and will not let you cast `mixed`, so read each value with a typed
accessor such as `Arr::string()` or `Arr::integer()`. Path parameters come from
`App\Http\Payloads\RouteParameter`:

```php
$projectId = RouteParameter::string($request, 'project');
```

## The controller

`app/Http/Controllers/Api/V1/Projects/CreateProjectController.php` is final, invokable and extends
nothing. Replace the `501` with the work and the response:

```php
public function __invoke(CreateProjectRequest $request, #[CurrentUser] User $user): JsonResponse
{
    $payload = CreateProjectPayload::fromRequest($request);

    $project = $user->projects()->create([
        'name' => $payload->name,
        'description' => $payload->description,
    ]);

    return ProjectResource::make($project)->response()->setStatusCode(201);
}
```

The Scribe attributes on the class are the endpoint's documentation, and CI checks them. The
`#[Endpoint]` description wants three sentences: what it does, when a client should call it, and
what it returns. Then add a `#[BodyParam]` or `#[QueryParam]` for each input, and a
`#[ResponseFromApiResource]` for the success response once the resource exists.

Document the standard headers the route reads with `#[Header]`: `Idempotency-Key` on an
idempotent `POST`, which the generator adds for you, `If-Match` on a write that requires it, and
`If-None-Match` on a `GET` behind `conditional`. Their descriptions come from
`HeaderDescriptionsOpenApiGenerator`, so the attribute only needs the name and an example.

Each error status the endpoint can return needs a `#[Response]` written as a problem. The generator
has already written the `401` and `403`, plus a `422` with a TODO example when there is a Form
Request. `LoginController` is a finished example of the whole set.

Errors are thrown, never returned as JSON. See [Errors](errors.md).

## The resource

Kit's resources are plain `JsonResource` classes, wrapped in `data`. Make one in
`app/Http/Resources/` and list every field:

```php
final class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Project $project */
        $project = $this->resource;

        return [
            'id' => (string) $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'created_at' => $project->created_at?->toAtomString(),
        ];
    }
}
```

IDs are strings and dates are ISO 8601. Because every field is listed by hand, a column you add to
the table later never ends up in the API by accident.

A list endpoint returns a `CursorPage`. Laravel's paginated resources put navigation in the body,
and Kit puts it in `Link` headers:

```php
return CursorPage::of($user->projects()->latest('id')->cursorPaginate(25), ProjectResource::class);
```

[HTTP standards](http-standards.md#pagination) covers what clients get from it.

## The test

The generated test has the authentication and ability cases passing straight away, and a `todo()`
for the success path. Write that, then cover validation and anything the endpoint does with the
standards, such as an idempotent replay:

```php
it('creates a project', function (): void {
    $token = User::factory()->create()->createToken('test', ['projects:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/v1/projects', ['name' => 'Kit'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Kit');
});

it('refuses a project without a name', function (): void {
    $token = User::factory()->create()->createToken('test', ['projects:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/v1/projects', [])
        ->assertProblem(422);
});
```

api-toolbox adds assertions such as `assertProblem()`, `assertProblemType()`,
`assertIdempotentReplay()` and `assertLinkHeader()` to every test response.

## Finish

1. Add a runtime check for the endpoint to `OpenApiContractTest`, so its real responses are
   validated against the spec.
2. If the endpoint has a Form Request, add it to the `error-shape` ignore in `.legible.yaml`.
   Validation problems carry an `errors` member the other problems do not, and the reason in that
   file explains why that is fine.
3. Regenerate the docs and run the checks:

   ```bash
   php artisan scribe:generate --no-interaction
   composer stan
   php artisan test
   ```

4. Commit `public/docs/` and `.scribe/` with the endpoint.

[API documentation](api-documentation.md) explains what the contract tests and legible check.

## Taking a type other than JSON

`consumes:application/json` sits on the whole `v1` group, so every write route accepts JSON and
nothing else unless it says otherwise. A route that declares its own `consumes:` replaces the
group's:

```php
Route::post('/imports', CreateImportController::class)
    ->middleware('consumes:multipart/form-data');
```

List every type the route takes, JSON included if it still takes JSON:
`consumes:application/json,multipart/form-data`.

A merge patch `PATCH` is the other common case. [HTTP standards](http-standards.md#partial-updates)
shows the route.
