# Testing

New FloCMS projects come with PHPUnit and a set of tests, so you can check your site after every change and before every deployment.

## Running the tests

```bash
composer test
```

`composer test` runs PHPUnit. To run part of the suite:

```bash
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite feature
vendor/bin/phpunit tests/Feature/CsrfTest.php
vendor/bin/phpunit --filter testHealthRoute
```

`phpunit.xml.dist` defines the two suites:

| Folder | Suite | For |
|---|---|---|
| `tests/Unit/` | `unit` | One class at a time, no web server, no database |
| `tests/Feature/` | `feature` | Whole requests and commands, like a visitor or an admin would make them |

Tests use the namespace `HostKurd\Flocms\Tests\`, mapped to `tests/` in `composer.json`'s `autoload-dev`.

## Creating a test

```bash
php flo make:test PriceCalculator             # tests/Unit/PriceCalculatorTest.php
php flo make:test Contact --feature           # tests/Feature/ContactTest.php
```

A unit test checks one piece of code directly:

```php
<?php
declare(strict_types=1);

namespace HostKurd\Flocms\Tests\Unit;

use FloCMS\Core\ValidationException;
use FloCMS\Core\Validator;
use PHPUnit\Framework\TestCase;

final class ContactRulesTest extends TestCase
{
    public function testAnEmptyMessageIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        Validator::validate(['message' => ''], ['message' => 'required|string']);
    }
}
```

## Testing pages

The skeleton's feature tests start a throw-away copy of the project on PHP's built-in web server, with a fresh `.env` made from `.env.example`, and send real HTTP requests to it. The helper is `tests/Support/AppServer.php`:

```php
<?php
declare(strict_types=1);

namespace HostKurd\Flocms\Tests\Feature;

use HostKurd\Flocms\Tests\Support\AppServer;
use PHPUnit\Framework\TestCase;

final class BlogTest extends TestCase
{
    private static ?AppServer $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$server = AppServer::start(['APP_DEBUG' => 'false']);
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
    }

    public function testTheBlogPageLoads(): void
    {
        $response = self::$server->get('/blog');

        self::assertSame(200, $response['status'], $response['body']);
        self::assertStringContainsString('<h1>Blog</h1>', $response['body']);
    }

    public function testAnUnknownPageIs404(): void
    {
        self::assertSame(404, self::$server->get('/no-such-page')['status']);
    }
}
```

| Method | Does |
|---|---|
| `AppServer::start($env, $prepare)` | Copies the project, applies `$env` on top of `.env`, and starts the server. `$prepare` receives the copy's folder before it starts, to add or change files. |
| `get($path)` | A GET request |
| `request($method, $path, $data, $headers)` | Any request; an array `$data` is sent as form fields, a string as the raw body |
| `json($method, $path, $data, $headers)` | A request with a JSON body |
| `flo($arguments, $env, $stdin)` | Runs `php flo` in the copy and returns `exit` and `output` |
| `forgetCookies()` | Starts a new session |
| `root()`, `stop()` | The copy's folder, and stopping the server |

Responses are arrays with `status`, `headers` (lowercase names) and `body`. Cookies are kept between requests, so a test can log in and then open admin pages. The skeleton's `CsrfTest` and `AdminSessionTest` show how.

> [!NOTE]
> `AppServer` links `vendor/` into the copy and sends the server's output to `/dev/null`, so the feature tests run on Linux and macOS. On Windows, run `vendor/bin/phpunit --testsuite unit`.

## Tests with a database

Tests that need MySQL or MariaDB are skipped unless you point them at a test server:

```bash
FLO_TEST_MYSQL_HOST=127.0.0.1 FLO_TEST_MYSQL_USER=root FLO_TEST_MYSQL_PASS=secret composer test
```

| Variable | Default |
|---|---|
| `FLO_TEST_MYSQL_HOST` | none: the tests are skipped |
| `FLO_TEST_MYSQL_PORT` | `3306` |
| `FLO_TEST_MYSQL_USER` | `root` |
| `FLO_TEST_MYSQL_PASS` | empty |
| `FLO_TEST_MYSQL_NAME` | `flocms_test` |

Use a separate database for tests; tests empty and fill tables. In your own tests, call `MySqlConfig::require()` first: it skips the test when no server is configured, and returns the connection details. `TestDatabase::connect()`, `resetUsers()` and `addUser()` set up users quickly.

For tests that only need the migrations, an SQLite file works too, because `php flo` supports it:

```php
$env = ['DB_TYPE' => 'sqlite', 'DB_NAME' => 'storage/site.sqlite'];
$server = AppServer::start($env);
$server->flo(['migrate'], $env);
```

The skeleton's `FirstAdminTest` uses this. Remember that your pages always connect to MySQL or MariaDB, so page tests that read data need the MySQL settings.

## Testing commands and the API

- **Commands:** `ApplicationTester` runs `php flo` commands inside PHPUnit and feeds answers to their questions. See [testing commands](/docs/{{version}}/writing-commands#testing-commands).
- **API:** `TestClient` sends requests straight to the API kernel, without a web server. See [testing your API](/docs/{{version}}/api-testing).

## Continuous integration

The skeleton includes `.github/workflows/tests.yml` for GitHub Actions. On pushes to `main` and on pull requests, it:

- validates `composer.json` and `composer.lock`, and checks every PHP file for syntax errors
- runs the whole suite with PHP 8.1, 8.2, 8.3 and 8.4 on Linux, and the unit tests on Windows
- runs the database tests against MariaDB 10.11 and MySQL 8.0

Keep it in your site's repository, so every change is tested before you deploy it.
