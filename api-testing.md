# Testing your API

`TestClient` sends requests straight to the API kernel inside PHPUnit, without a web server. Tests are fast, and you can check status codes, headers and JSON with one line each.

## A first test

```bash
php flo make:test ContactApi --feature
```

creates `tests/Feature/ContactApiTest.php`. Replace its content with a test that loads your routes into a kernel:

```php
<?php
declare(strict_types=1);

namespace HostKurd\Flocms\Tests\Feature;

use FloCMS\Api\Kernel;
use FloCMS\Api\Middleware\AuthenticateMiddleware;
use FloCMS\Api\Router;
use FloCMS\Api\RouteLoader;
use FloCMS\Api\Security\Identity;
use FloCMS\Api\Testing\StaticAuthenticator;
use FloCMS\Api\Testing\TestClient;
use FloCMS\Core\Support\Container;
use PHPUnit\Framework\TestCase;

final class ContactApiTest extends TestCase
{
    private function client(?Identity $user = null): TestClient
    {
        $container = new Container();
        $router = new Router();
        $router->group('/api', static function (Router $router) use ($container): void {
            (new RouteLoader($router, $container))->load(dirname(__DIR__, 2) . '/api/routes.php');
        });

        $kernel = new Kernel(router: $router, container: $container);
        $kernel->aliases([
            // The real aliases need storage and the database; tests replace them.
            'throttle' => static fn (string ...$names) => static fn ($request, $next) => $next->handle($request),
            'idempotent' => static fn () => static fn ($request, $next) => $next->handle($request),
            'auth' => static fn (string ...$permissions) => new AuthenticateMiddleware(
                new StaticAuthenticator($user),
                permissions: $permissions
            ),
        ]);

        return new TestClient($kernel);
    }

    public function testContactNeedsAnEmail(): void
    {
        $this->client()
            ->postJson('/api/v1/contact', ['name' => 'Sara', 'message' => 'Hello there, FloCMS!'])
            ->assertUnprocessable()
            ->assertValidationError('email');
    }

    public function testContactAcceptsAValidMessage(): void
    {
        $this->client()
            ->postJson('/api/v1/contact', ['name' => 'Sara', 'email' => 'sara@example.com', 'message' => 'Hello there, FloCMS!'])
            ->assertStatus(202)
            ->assertJsonPath('data.received', true);
    }

    public function testUsersRequireASignedInUser(): void
    {
        $this->client()->get('/api/v1/users')->assertUnauthorized();
    }

    public function testUsersRequireThePermission(): void
    {
        $editor = new Identity(5, permissions: ['content.*']);

        $this->client($editor)->get('/api/v1/users')->assertForbidden();
    }
}
```

Run it with `composer test` or `vendor/bin/phpunit tests/Feature/ContactApiTest.php`.

What the test does:

- **Loads `api/routes.php`** under `/api`, like `public/api.php` does, so the URLs are the real ones.
- **Replaces the aliases.** The real `throttle`, `idempotent` and `auth` aliases need storage folders, the database and real tokens. In tests, the first two let every request through, and `auth` uses `StaticAuthenticator`, which signs in whoever you pass.
- **Signs in a fake user.** `new Identity(5, permissions: ['content.*'])` is a user with ID 5 and those permissions.

## Sending requests

| Method | Sends |
|---|---|
| `get($uri, $query)`, `head($uri, $query)` | A GET or HEAD request; `$query` becomes the query string |
| `postJson($uri, $data)`, `putJson(...)`, `patchJson(...)` | A JSON body |
| `delete($uri)`, `options($uri)` | |
| `postForm($uri, $fields)` | A form-encoded body |
| `json($method, $uri, $data)` | Any method with a JSON body |

Set up the request first. Each of these returns a new client, so the original stays unchanged:

```php
$client->withToken($token)                 // Authorization: Bearer ...
    ->withApiKey($key)                     // X-API-Key: ...
    ->withHeader('Accept-Language', 'ar')
    ->withCookie('name', 'value')
    ->fromIp('203.0.113.7')
    ->get('/api/v1/users');
```

## Checking responses

```php
$client->get('/api/v1/posts', ['filter' => ['status' => 'published']])
    ->assertOk()
    ->assertHeader('Content-Type')
    ->assertJsonCount(3)
    ->assertJsonPath('meta.total', 3)
    ->assertJsonPath('data.0.title', 'Hello');
```

| Assertion | Checks |
|---|---|
| `assertStatus(201)` | The status code |
| `assertOk()`, `assertCreated()`, `assertNoContent()` | 200, 201, 204 |
| `assertUnauthorized()`, `assertForbidden()`, `assertNotFound()`, `assertUnprocessable()` | 401, 403, 404, 422 |
| `assertHeader('Name', $value)`, `assertHeaderMissing('Name')` | A header, optionally with its value |
| `assertJsonPath('data.0.id', 5)` | A value in the JSON, by dotted path |
| `assertJsonMissingPath('data.0.password')` | A path that must not exist |
| `assertJsonCount(3, 'data')` | The number of items at a path (`data` by default) |
| `assertValidationError('email', $message)` | A 422 with an error for that field |

To inspect a response yourself, use `status()`, `body()`, `header('Name')` and `json('data.0')`.

## Tests that need the database

Routes that query the database need one in the test, too. The skeleton's own tests use a throw-away MySQL database when `FLO_TEST_MYSQL_HOST` is set, and skip those tests otherwise. See [testing](/docs/{{version}}/testing).

Check what a resource leaves out, so internal columns can never leak:

```php
$client->get('/api/v1/users/1')
    ->assertOk()
    ->assertJsonMissingPath('data.password')
    ->assertJsonMissingPath('data.token');
```
