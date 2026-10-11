# API authentication

API clients, such as a mobile app or another server, can't use the login form, so they prove who they are with a token on every request. FloCMS supports personal access tokens, API keys, the admin session for your own JavaScript, and JWT.

## Protecting routes

In a new project, the `auth` middleware accepts a personal access token or the admin session:

```php
$router->get('/me', [ProfileController::class, 'show'])->middleware('auth');

$router->group('/users', function (Router $router): void {
    // ...
}, ['auth:users.manage']);
```

- `auth` requires a signed-in user.
- `auth:users.manage` also requires that permission. List several, separated by commas; the user needs all of them.

Permissions come from the same `permissions` setting as the admin panel. See [roles and permissions](/docs/{{version}}/permissions).

| Request | Response |
|---|---|
| No token, or an invalid one | `401` "Authentication token is required or invalid." |
| Valid token, missing permission | `403` "You do not have permission to access this resource." |

## Personal access tokens

A token belongs to a user and acts with that user's role. The API tables are created by `php flo migrate`, or on their own with `php flo api:install-schema`. Then:

```bash
php flo api:token:create 1 "Mobile app"
php flo api:token:create 1 "Report script" --scopes=reports.read --expires=2027-01-01
```

The first argument is the user's ID. The token is printed once, so copy it then. Clients send it in the `Authorization` header:

```bash
curl -H "Authorization: Bearer flt_..." https://example.com/api/v1/users
```

Some hosts strip the `Authorization` header before PHP sees it. Clients can send `X-FloCMS-Authorization: Bearer ...` instead.

```bash
php flo api:token:list --user=1
php flo api:token:revoke 7          # one token
php flo api:token:revoke --user=1   # every token of user 1
```

### Issuing tokens from code

To give users a token from your own login endpoint:

```php
use FloCMS\Api\Auth\TokenRepository;
use FloCMS\Api\Database\Connection;

$tokens = new TokenRepository(new Connection());   // the site's database
$issued = $tokens->issueFor($userId, 'Mobile app', 30 * 86400, ['posts.write']);

return ['token' => $issued->plaintext];   // the only time it is visible

$tokens->revokeAllForUser($userId);       // for example after a password change
```

### How tokens are stored

Tokens and API keys are random, shown once, and stored only as SHA-256 hashes in the `api_tokens` and `api_keys` tables. Verification takes constant time, and expiry and revocation are checked on every request.

A token's user is loaded through the same `auth.user_loader` as the admin panel. A deleted or suspended user's tokens stop working at once, and a role change applies to the next request. See [authentication](/docs/{{version}}/authentication#sessions-end-at-once).

## The signed-in user

Authentication middleware puts an `Identity` in the request's `identity` attribute:

```php
public function store(Request $request): Response
{
    $identity = $request->attribute('identity');

    $userId = $identity->id;
    if ($identity->can('posts.publish')) {
        // ...
    }

    $identity->authorize('posts.write');   // 403 when not allowed
}
```

`can()` reads the `permissions` setting with the same wildcard rules as `Auth::can()`.

## The admin session from JavaScript

Admin pages can call `/api/v1` with `fetch()` as the signed-in admin, without a token. The `auth` middleware accepts the session cookie when:

- the user has admin access, reloaded from the database on every request
- POST, PUT, PATCH and DELETE requests carry the `X-CSRF-TOKEN` header (419 otherwise)

The admin layout's `csrf.js` adds that header to same-origin `fetch()` and jQuery requests. See [sessions and CSRF](/docs/{{version}}/session#javascript-requests).

## API keys

API keys are for servers rather than users, such as a partner's feed importer:

```bash
php flo api:key:create "Partner feed" --scopes=posts.read
php flo api:key:create "Sync job" --user=1 --expires=2027-06-30
php flo api:key:list
php flo api:key:revoke 3
```

Clients send `X-API-Key: flk_...`. A key without `--user` is a service key, with the identity `api-key:<id>`.

The skeleton's `auth` alias doesn't accept API keys. To use them, add an authenticator in `public/api.php`.

## Authenticators

`auth` is built from `AuthenticateMiddleware` and authenticators. Build your own combinations in `public/api.php`:

```php
use FloCMS\Api\Auth\{ApiKeyAuthenticator, ApiKeyRepository, BearerTokenAuthenticator, FirstMatchAuthenticator, TokenRepository};
use FloCMS\Api\Database\Connection;
use FloCMS\Api\Middleware\AuthenticateMiddleware;

$kernel->aliases([
    // ...the existing aliases
    'partner' => static fn (string ...$scopes): AuthenticateMiddleware => new AuthenticateMiddleware(
        new FirstMatchAuthenticator(
            new ApiKeyAuthenticator(new ApiKeyRepository(new Connection())),
            new BearerTokenAuthenticator(new TokenRepository(new Connection())),
        ),
        scopes: $scopes,
    ),
]);
```

```php
$router->get('/feed', [FeedController::class, 'index'])->middleware('partner:posts.read');
```

| Authenticator | Credential | Identity |
|---|---|---|
| `BearerTokenAuthenticator` | `Authorization: Bearer flt_<id>_<secret>` | The token's user |
| `ApiKeyAuthenticator` | `X-API-Key: flk_<id>_<secret>` | The key's user, or `api-key:<id>` |
| `SessionAuthenticator` | The admin session cookie, plus `X-CSRF-TOKEN` | The signed-in admin |
| `JwtAuthenticator($secret)` | `Authorization: Bearer <JWT>` | The token's `sub` claim |
| `FirstMatchAuthenticator(...)` | Tries each authenticator in turn | |

`AuthenticateMiddleware` takes `roles:` (any of them), `scopes:` (all of them) and `permissions:` (all of them).

## JWT

`JwtAuthenticator` accepts HS256 tokens and rejects every other algorithm. Keep the secret in `.env`; a secret shorter than 32 bytes is refused. Issue tokens with `Jwt::encode()`:

```php
use FloCMS\Api\Auth\{Jwt, JwtAuthenticator};

$jwt = Jwt::encode(['sub' => $userId, 'exp' => time() + 3600], $secret);

$authenticator = new JwtAuthenticator($secret, issuer: 'https://example.com', audience: 'mobile');
```

`exp`, `nbf` and `iat` are checked with 30 seconds of leeway, and `iss` and `aud` when you pass them.
