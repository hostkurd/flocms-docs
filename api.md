# Building an API

FloCMS includes a JSON API runtime, `hostkurd/flocms-api`. Choose an API setup when you create the project, or add it later:

```bash
composer require hostkurd/flocms-api
php flo api:install
php flo migrate
```

See [choose what you build](/docs/{{version}}/setups#add-an-api-later).

Routes are explicit and versioned, every response is JSON with security headers, and the pieces most APIs need are built in: authentication, validation, resources, pagination, CORS, rate limiting, OpenAPI documents and a test client.

It runs on ordinary shared hosting: PHP 8.1 or newer with PDO, mbstring and fileinfo. APCu is used for rate limits when it's available.

## How it's wired

- `public/.htaccess` sends every `/api/v1/...` request to `public/api.php`.
- `public/api.php` loads the same bootstrap and `.env` as your pages, sets up the middleware, and loads your routes.
- Your routes live in `api/routes.php`, and your controllers and resources in `api/` (namespace `App\Api\`).

Try it on a new site:

```bash
curl http://localhost:8000/api/v1/health
# {"success":true,"data":{"status":"ok","db":"not_configured"}}
```

The API path has no session and no CSRF check by default. Private routes are protected with the `auth` middleware. See [authentication](/docs/{{version}}/api-authentication).

## Defining routes

`api/routes.php` returns a function that receives the router:

```php
<?php

use App\Api\Controllers\ContactController;
use App\Api\Controllers\UsersController;
use FloCMS\Api\Http\HealthController;
use FloCMS\Api\Router;

return static function (Router $router): void {
    $router->version('v1', static function (Router $router): void {
        $router->get('/health', new HealthController())->name('health');

        $router->post('/contact', [ContactController::class, 'store'])
            ->name('contact.store')
            ->middleware('throttle:forms', 'idempotent');

        $router->group('/users', static function (Router $router): void {
            $router->get('', [UsersController::class, 'index'])->name('index');
            $router->get('/{id:\d+}', [UsersController::class, 'show'])->name('show');
        }, ['auth:users.manage'], namePrefix: 'users.');
    });
};
```

These are the routes `php flo api:install` writes. `GET /api/v1/users/5` runs `UsersController::show()` with `$id = 5`.

### Methods

```php
$router->get('/posts', $handler);
$router->post('/posts', $handler);
$router->put('/posts/{id}', $handler);
$router->patch('/posts/{id}', $handler);
$router->delete('/posts/{id}', $handler);
$router->map(['GET', 'POST'], '/search', $handler);
```

`head()` and `options()` exist too, but you rarely need them:

- a `HEAD` request runs the `GET` route without a body
- an `OPTIONS` request answers `204` with an `Allow` header
- a known path with the wrong method answers `405` with `Allow`

### Parameters

A parameter in braces matches one path segment. Add a regular expression after a colon to constrain it:

```php
$router->get('/posts/{slug}', ...);                 // any single segment
$router->get('/authors/{id:\d+}', ...);             // digits only
$router->get('/{country:[a-z]{2}}/cities', ...);    // two letters
```

Values reach the handler URL-decoded. A constraint can't match across segments: `{path:.+/.+}` is rejected.

### Groups and versions

`group()` shares a path prefix, middleware and a name prefix with the routes inside it. Groups can be nested:

```php
$router->group('/admin', function (Router $router): void {
    $router->get('/stats', [StatsController::class, 'index'])->name('stats');   // admin.stats
}, ['auth:settings.view'], namePrefix: 'admin.');
```

`version('v1', ...)` is a group with the prefix `/v1` and the name prefix `v1.`.

### Matching order

Routes match in the order you register them, and the first match wins. Register specific routes before general ones:

```php
$router->get('/posts/featured', ...);     // before {slug}
$router->get('/posts/{id:\d+}', ...);     // before {slug}
$router->get('/posts/{slug}', ...);
```

With `APP_DEBUG=true`, the router is strict: a route that can never match, such as `/posts/{id}` after `/posts/{slug}`, or a duplicate route name, throws an error. In production the same problems are logged to `storage/logs/api.log`.

### Route names

Name routes to build their URLs:

```php
$router->url('v1.users.show', ['id' => 5], ['include' => 'roles']);
// "/api/v1/users/5?include=roles"
```

## Versioning and deprecation

Keep old clients working by adding a new version next to the old one, and mark the old one as deprecated:

```php
use FloCMS\Api\Middleware\Deprecated;

$router->version('v1', $v1Routes, [new Deprecated(sunset: '2027-06-30', successor: 'https://example.com/api/v2')]);
$router->version('v2', $v2Routes);
```

`Deprecated` adds `Deprecation`, `Sunset` and `Link` headers to every response of those routes.

> [!IMPORTANT]
> The skeleton's `public/.htaccess` only sends `/api/v1` to `api.php`. Before you add `v2`, change that rule, for example to `RewriteRule ^api/v[0-9]+(/.*)?$ api.php [L,QSA]`.

## What every request gets

The middleware in `public/api.php` runs for every API request, in this order:

1. **Security headers**: `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` and `Cache-Control: no-store`
2. **JSON errors**: every error, a 404 too, becomes a JSON response
3. **CORS** for the origins in `API_CORS_ORIGINS`. See [CORS and rate limiting](/docs/{{version}}/api-cors).
4. **Maintenance mode**: 503 while `php flo down` is active, except for `/api/v1/health` and allowed IPs
5. **Locale** from `?lang=` or `Accept-Language`
6. **Rate limiting**: `API_RATE_LIMIT` requests per minute per client
7. **Body limits**: bodies over 2 MB are rejected, and malformed JSON is a 400

Then the route's own middleware runs. See [middleware](/docs/{{version}}/api-middleware).

## Next steps

For services and modules, see the [container](/docs/{{version}}/container#using-it-with-the-api) and [module API route setup](/docs/{{version}}/modules#loading-module-api-routes).

- [Controllers and resources](/docs/{{version}}/api-controllers): handlers, responses and errors
- [Authentication](/docs/{{version}}/api-authentication): tokens, API keys and the admin session
- [Validation](/docs/{{version}}/api-validation): checking request data
- [Pagination](/docs/{{version}}/api-pagination): pages, sorting and filters
- [Testing](/docs/{{version}}/api-testing): calling your API from PHPUnit

## Commands

```bash
php flo api:routes            # every API route with its name and middleware
php flo list api              # all API commands
```

`php flo route:list` shows the API routes next to your page routes.

> [!NOTE]
> The old `/api/<controller>/<action>` route, with methods prefixed `api_`, has no authentication and skips CSRF checks. It is off unless `LEGACY_API=true` is set in `.env`. Move such endpoints to `api/routes.php`.

## Using the API without the skeleton

In a Composer project that requires `hostkurd/flocms-api`, create an entry point and route requests to it. This example serves one endpoint without FloCMS application bootstrap or a database:

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use FloCMS\Api\Kernel;
use FloCMS\Api\Middleware\BodySizeMiddleware;
use FloCMS\Api\Middleware\ExceptionMiddleware;
use FloCMS\Api\Middleware\JsonBodyMiddleware;
use FloCMS\Api\Middleware\SecurityHeadersMiddleware;
use FloCMS\Api\Router;
use FloCMS\Core\Http\Request;
use FloCMS\Core\Support\Container;

$container = new Container();
$router = new Router();
$router->get('/api/v1/health', static fn (): array => ['status' => 'ok'])->name('health');

$kernel = new Kernel(router: $router, container: $container);
$kernel->middleware([
    new SecurityHeadersMiddleware(),
    new ExceptionMiddleware(),
    new BodySizeMiddleware(),
    new JsonBodyMiddleware(),
]);
$kernel->handle(Request::fromGlobals())->send();
```

The router matches the complete request path; it does not automatically add `/api` or `/v1`. Register [authentication](/docs/{{version}}/api-authentication), CORS, throttling and application services as your endpoints require. The skeleton's aliases and middleware are not supplied by loading the package alone.

`middleware([...])` replaces the global list; `pipe($middleware)` appends one entry. A kernel starts with exception middleware and also has an exception-rendering fallback, but configure the full pipeline explicitly to get the behavior your application needs.

`$kernel->includeRequestIdInBody()` adds `request_id` to successful envelopes created through the response factory. The `X-Request-ID` header is always set by the kernel; plain custom `Response` bodies are not rewritten.

For files that return route registration callables or definition lists, use `RouteLoader($router, $container)->load($path)`. Module route files use [loadModules()](/docs/{{version}}/modules#loading-module-api-routes).
