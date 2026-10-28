# CORS and rate limiting

Two settings decide who may call your API and how often: CORS lets browser apps on other domains use it, and rate limits stop a single client from overloading it.

## CORS

Browsers block JavaScript on one site from reading responses of another site, unless the other site allows it. That permission is CORS. If your API is called by a front end on another domain, such as a React app on `app.example.com`, list that origin in `.env`:

```ini
API_CORS_ORIGINS=https://app.example.com,https://*.example.com
```

- Separate origins with commas. An origin is a scheme, host and optional port, without a path.
- `https://*.example.com` matches exactly one subdomain label with the same scheme and port: `https://app.example.com`, but not `https://example.com`, `https://a.b.example.com` or `http://app.example.com`.
- An empty value allows no other origin. Pages on your own site never need CORS.

Mobile apps, servers and `curl` aren't browsers, so CORS doesn't affect them.

### How it behaves

- Preflight `OPTIONS` requests are answered by the CORS middleware itself with `204`, before rate limits and authentication run. The requested method and headers must be allowed.
- Error responses keep their CORS headers, so a browser app can read a 422 or 429 message.
- `X-Request-ID`, `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` and `Retry-After` are readable by JavaScript.
- Allowed methods are GET, POST, PUT, PATCH, DELETE and OPTIONS. Allowed request headers include `Authorization`, `Content-Type`, `X-CSRF-TOKEN`, `X-Request-ID` and `X-FloCMS-Authorization`.

### Cookies across origins

By default, browsers don't send cookies with cross-origin requests to your API, and your API doesn't ask them to. Clients on other origins should use [tokens](/docs/{{version}}/api-authentication). If you do need cookies, construct the middleware with `allowCredentials: true` in `public/api.php`. A `*` origin can't be combined with credentials; FloCMS refuses that configuration.

## Rate limiting

### The global limit

Every client gets `API_RATE_LIMIT` requests per minute, 60 by default, across the whole API:

```ini
API_RATE_LIMIT=120
```

Responses tell the client where it stands:

```text
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 57
X-RateLimit-Reset: 1792667460
```

Over the limit, the API answers `429 Too Many Requests` with a `Retry-After` header in seconds. Preflight `OPTIONS` requests are never counted.

### Named limits

Stricter limits for single routes are named in `public/api.php`:

| Name | Limit | For |
|---|---|---|
| `public` | `API_RATE_LIMIT` per minute | Ordinary read endpoints |
| `forms` | 5 per minute | Contact forms, sign-ups |
| `auth` | 10 per 15 minutes | Login and password endpoints |

```php
$router->post('/contact', [ContactController::class, 'store'])->middleware('throttle:forms');
```

A named limit counts per signed-in user when the request is authenticated, and per client IP otherwise. To count per user, put the throttle after `auth`: `->middleware('auth', 'throttle:public')`.

Add your own limits in `public/api.php`, as `[requests, seconds]`:

```php
$limiters = new RateLimiterRegistry($rateLimitStore, $clientIp, [
    'public'  => [max(1, (int) Env::get('API_RATE_LIMIT', 60)), 60],
    'forms'   => [5, 60],
    'auth'    => [10, 900],
    'exports' => [3, 3600],   // 3 per hour
]);
```

### Where counters are kept

Counters live in APCu when the server has it, and otherwise in files under `storage/cache/api-rate-limit`. The limiters are core classes since core 2.8 (`FloCMS\Core\RateLimit\FileRateLimiter` and `ApcuRateLimiter`, with `FloCMS\Core\Security\ClientIpResolver` for the client IP), so the website's login throttle uses them too. flocms-api 1.3 keeps the old `FloCMS\Api\RateLimit\…` and `FloCMS\Api\Security\…` names as deprecated aliases. File counters clean up after themselves on about 1 in 100 requests; `php flo api:gc` removes stale ones on demand, and the [scheduler](/docs/{{version}}/scheduler) runs it daily.

## Client IPs behind a proxy

Rate limits, idempotency and maintenance mode identify clients by IP. Behind a reverse proxy or CDN, list the proxy in `TRUSTED_PROXIES`, so the real client IP is taken from `X-Forwarded-For`:

```ini
TRUSTED_PROXIES=10.0.0.0/8
```

`X-Forwarded-For` is only trusted on requests from the listed IPs or CIDR ranges. Otherwise any client could pretend to be someone else. See [authentication](/docs/{{version}}/authentication#behind-a-proxy-or-cdn).
