# Middleware

Middleware wraps your handlers. It can inspect or reject a request before the handler runs, and change the response after it. Authentication, rate limits and caching are all middleware.

## Route middleware

Add middleware to a route, or to every route of a group:

```php
$router->post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:forms', 'idempotent');

$router->group('/users', function (Router $router): void {
    // ...
}, ['auth:users.manage']);
```

Middleware can be written as an alias string, a class name, an object or a closure.

### Aliases in a new project

`public/api.php` defines three aliases:

| Alias | Does |
|---|---|
| `throttle:public`, `throttle:forms`, `throttle:auth` | Named rate limits: `API_RATE_LIMIT` (60) per minute, 5 per minute, 10 per 15 minutes. See [rate limiting](/docs/{{version}}/api-cors#rate-limiting). |
| `auth`, `auth:users.manage,content.edit` | Requires a personal access token or the admin session, optionally with permissions. See [authentication](/docs/{{version}}/api-authentication). |
| `idempotent` | Safe retries with the `Idempotency-Key` header. See [idempotency](/docs/{{version}}/api-idempotency). |

Add your own in `public/api.php`:

```php
$kernel->aliases([
    // ...the existing aliases
    'json' => static fn (): RequireJsonMiddleware => new RequireJsonMiddleware(),
]);
```

Text after the colon is passed to the alias function, split at commas: `auth:users.manage,content.edit` calls it with `'users.manage', 'content.edit'`.

## Global middleware

Global middleware runs for every API request, also for unknown routes, before the route's own middleware. The list in `public/api.php` is outermost first:

```php
$kernel->middleware([
    new SecurityHeadersMiddleware(),   // headers on every response, errors included
    new ExceptionMiddleware(...),      // errors as JSON
    new CorsMiddleware(...),           // answers preflight requests
    new MaintenanceMiddleware(...),    // 503 during maintenance
    new LocaleMiddleware(...),         // ?lang= and Accept-Language
    new RateLimitMiddleware(...),      // API_RATE_LIMIT per minute per client
    new BodySizeMiddleware(),          // 413 for bodies over 2 MB
    new JsonBodyMiddleware(),          // 400 for malformed JSON
]);
```

Keep this order when you change it: security headers and JSON errors outside, so even errors from later middleware get them; CORS before the rate limit, so preflight requests are never counted.

## Writing middleware

```bash
php flo make:middleware RequireApiVersion
```

creates `api/Middleware/RequireApiVersion.php`:

```php
<?php
declare(strict_types=1);

namespace App\Api\Middleware;

use FloCMS\Api\Contracts\MiddlewareInterface;
use FloCMS\Api\Contracts\RequestHandlerInterface;
use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;

final class RequireApiVersion implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Before the handler: inspect or reject the request here.

        $response = $handler->handle($request);

        // After the handler: change the response here.

        return $response;
    }
}
```

Use it on a route with `->middleware(new RequireApiVersion())`. To stop a request, throw an `ApiException`, or return a response without calling `$handler`:

```php
if ($request->header('X-Client-Version') === null) {
    throw new \FloCMS\Api\Exceptions\ApiException('X-Client-Version is required.', 400);
}
```

To pass data to the handler, add a request attribute: `$handler->handle($request->withAttribute('client', $client))`, and read it with `$request->attribute('client')`.

A closure works too. It receives the request and the next handler, and must return a `Response`:

```php
$router->get('/report', $handler)->middleware(
    static fn (Request $request, RequestHandlerInterface $next): Response => $next->handle($request)
        ->header('X-Report', 'yes')
);
```

## Built-in middleware

| Middleware | Does |
|---|---|
| `SecurityHeadersMiddleware` | `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and `Cache-Control: no-store` unless a route sets its own |
| `ExceptionMiddleware($debug)` | Turns exceptions into JSON error responses |
| `CorsMiddleware($origins)` | CORS headers and preflight answers. See [CORS](/docs/{{version}}/api-cors). |
| `MaintenanceMiddleware` | 503 with `Retry-After` during maintenance mode |
| `LocaleMiddleware($languages, $default)` | Picks the language from `?lang=`, then `Accept-Language`, then the default; loads the language file and sends `Content-Language` |
| `RateLimitMiddleware` | A global rate limit per client |
| `BodySizeMiddleware($maxBytes)` | 413 for bodies over the limit, 2 MB by default |
| `JsonBodyMiddleware($maxBytes)` | 400 for malformed JSON, 413 for large JSON |
| `RequireJsonMiddleware(allowMultipart: false)` | 415 for POST, PUT and PATCH bodies that aren't JSON |
| `AccessLogMiddleware($loggerOrFile)` | One log line per request: method, path, status, duration, request ID and IP. Never bodies or tokens. |
| `AuthenticateMiddleware` | Authentication. See [authentication](/docs/{{version}}/api-authentication). |
| `IdempotencyMiddleware` | See [idempotency](/docs/{{version}}/api-idempotency). |
| `CacheControl`, `ETagMiddleware` | See [HTTP caching](/docs/{{version}}/api-pagination#http-caching). |
| `Deprecated` | See [versioning](/docs/{{version}}/api#versioning-and-deprecation). |

The middleware classes are in the `FloCMS\Api\Middleware` namespace.

## Health check

New projects have a health route for uptime monitors:

```bash
curl https://example.com/api/v1/health
```

| Response | Meaning |
|---|---|
| `200 {"status":"ok","db":"ok"}` | The site and its database work |
| `200 {"status":"ok","db":"not_configured"}` | The site has no database |
| `503`, `"db":"down"` | The database doesn't answer |

The health route keeps working during maintenance mode, so monitors can tell maintenance from an outage.

## Maintenance mode

While `php flo down` is active, the API answers 503 with a JSON message and a `Retry-After` header. IPs passed to `--allow` and IPs in `API_MAINTENANCE_ALLOWED_IPS` keep access. See [maintenance mode](/docs/{{version}}/maintenance).
