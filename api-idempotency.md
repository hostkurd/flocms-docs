# Idempotency

Networks fail. A mobile app sends an order, the connection drops before the answer arrives, and the app doesn't know whether the order was saved. If it simply sends it again, the customer may pay twice. Idempotency keys make that retry safe.

## How it works

The client creates a unique key, usually a UUID, for each action, and sends it with the request:

```text
POST /api/v1/contact
Idempotency-Key: 7c1e4a52-9f3b-4d38-8a61-2b5f0c9e1d47
Content-Type: application/json

{"name": "Sara", "email": "sara@example.com", "message": "Hello there!"}
```

The first request runs normally, and its response is stored. A repeat with the same key gets the stored response, with an `Idempotent-Replayed: true` header, and the handler doesn't run again. When the user starts a new action, the client makes a new key.

## Using it on a route

In a new project, the `idempotent` alias is ready to use:

```php
$router->post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:forms', 'idempotent');
```

Requests without the header are handled normally, so adding `idempotent` doesn't break existing clients.

## The rules

| Situation | Result |
|---|---|
| Same key, same request, first one finished | The stored response, with `Idempotent-Replayed: true` |
| Same key, same request, first one still running | `409` with `Retry-After` |
| Same key, different body or query string | `422` |
| The first request failed with an exception or a 5xx response | Not stored, so the client can retry with the same key |
| A key that isn't 1 to 255 visible ASCII characters | `400` |

- Keys are scoped to the signed-in user, or to the client IP for anonymous requests, plus the method and path. Two clients can't collide, even with the same key.
- Responses are kept for 24 hours.
- Only POST requests are checked.

## Options

The alias uses file storage under `storage/cache/api-idempotency`. Build the middleware yourself for other settings:

```php
use FloCMS\Api\Idempotency\{DatabaseIdempotencyStore, FileIdempotencyStore};
use FloCMS\Api\Middleware\IdempotencyMiddleware;

new IdempotencyMiddleware(
    new FileIdempotencyStore(ROOT . '/storage/cache/api-idempotency'),
    ttlSeconds: 3600,               // keep responses for an hour
    required: true,                 // 400 when the header is missing
    methods: ['POST', 'PATCH'],     // check these methods
);
```

`DatabaseIdempotencyStore` keeps records in the `api_idempotency` table instead of files, which suits sites on several servers. The table is created by `php flo migrate`.

## Cleaning up

Stored responses expire, but their files stay until they're removed. `php flo api:gc` deletes expired records, and the [scheduler](/docs/{{version}}/scheduler) runs it daily. Add `--database` to clean the `api_idempotency` table too.
