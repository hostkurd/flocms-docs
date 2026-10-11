# Building an API

FloCMS includes a JSON API runtime, `hostkurd/flocms-api`, already wired up in new projects. Routes are explicit and versioned, and every response is JSON with security headers.

## Routes

`public/api.php` serves the routes in `api/routes.php` under `/api`. Controllers and resources live in `api/` (namespace `App\Api\`):

```php
return static function (Router $router): void {
    $router->version('v1', static function (Router $router): void {
        $router->get('/health', new HealthController())->name('health');

        $router->post('/contact', [ContactController::class, 'store'])
            ->middleware('throttle:forms', 'idempotent');

        $router->group('/users', static function (Router $router): void {
            $router->get('', [UsersController::class, 'index'])->name('index');
            $router->get('/{id:\d+}', [UsersController::class, 'show'])->name('show');
        }, ['auth:users.manage'], namePrefix: 'users.');
    });
};
```

- `get`, `post`, `put`, `patch`, `delete`, `head`, `options` and `map([...])` register routes.
- A parameter matches one path segment. Constrain it with a regular expression after a colon: `{id:\d+}`.
- `version('v1', ...)` prefixes routes with `/v1`.
- `group()` shares a path prefix, middleware and a name prefix.

`php flo make:api-controller Posts --resource` creates a controller, and `php flo make:resource Post` a resource.

## What every request gets

- **CORS** for the origins in `API_CORS_ORIGINS`. Patterns like `https://*.example.com` are allowed.
- **Locale** from `?lang=` or the `Accept-Language` header.
- **Rate limiting** per client: `API_RATE_LIMIT` requests per minute.
- **No session and no CSRF check** by default, since API clients authenticate with tokens.

## Route middleware

| Middleware | Effect |
|---|---|
| `throttle:public`, `throttle:forms`, `throttle:auth` | Named rate limits: 60 per minute, 5 per minute, 10 per 15 minutes |
| `auth`, `auth:users.manage` | A personal access token (`Authorization: Bearer ...`), or the admin session from same-origin JavaScript (with `X-CSRF-TOKEN`), optionally with a permission |
| `idempotent` | Retries with the same `Idempotency-Key` header get the first response |

## Tokens

The API's database tables are created by `php flo migrate`, or on their own with `php flo api:install-schema`. Then create a token for a user:

```bash
php flo api:token:create 1 "Mobile app"
```

The token is printed once. Send it as `Authorization: Bearer <token>`.

## Examples in the skeleton

New projects include working examples:

- `ContactController`: validation
- `UsersController`: pagination with filters and sorting, protected by a permission
- `UserResource`: a resource that keeps the password and token columns out of responses

## Commands

```bash
php flo api:routes                           # list the API routes
php flo api:docs --out=public/openapi.json   # OpenAPI 3.1 document
php flo api:gc                               # clean up rate-limit and idempotency files
```

In debug mode the OpenAPI document is also served at `/api/v1/openapi.json`. `api:gc` runs daily through the [scheduler](/docs/{{version}}/scheduler).

> [!NOTE]
> The old `/api/<controller>/<action>` route, with methods prefixed `api_`, has no authentication and skips CSRF checks. It is off unless `LEGACY_API=true` is set in `.env`. Move such endpoints to `api/routes.php`.
