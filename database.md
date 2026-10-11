# Database

FloCMS sites use MySQL or MariaDB. A site doesn't need a database to run: the welcome page, static pages and the documentation you're reading work without one. You add a database when your site stores data.

## Configuration

The connection is set in `.env`:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=my_site
DB_USERNAME=my_user
DB_PASSWORD=secret
DB_CHARSET=utf8mb4
```

| Key | Default | |
|---|---|---|
| `DB_HOST` | `localhost` | On cPanel usually `localhost` |
| `DB_PORT` | `3306` | |
| `DB_NAME` | empty | Leave empty until the database exists |
| `DB_USERNAME`, `DB_PASSWORD` | empty | |
| `DB_CHARSET` | `utf8mb4` | Full Unicode, including Arabic, Kurdish and emoji |

`config/config.php` copies these into the `db.*` settings, which the connection reads. While `DB_NAME` or `DB_USERNAME` is empty, the site runs without a database.

> [!NOTE]
> The `php flo` commands read the same keys, and also `DB_TYPE=sqlite` and `DB_DSN` for other PDO drivers. Your pages always connect to MySQL or MariaDB, so use those for a site.

## The connection

FloCMS opens the connection the first time a query needs it, and every model and query in the request shares it. Pages that never query the database never connect.

In a model, the connection is `$this->db`. Anywhere else:

```php
use FloCMS\Core\App;

$db = App::db();   // FloCMS\Core\Database, or null when not configured

if ($db !== null) {
    $count = $db->table('posts')->count();
}
```

The connection uses real prepared statements, throws an exception on every SQL error, and gives up connecting after 2 seconds. When the connection fails, the failure is remembered for the rest of the request, so a slow or missing server costs that time only once.

Build queries with the [query builder](/docs/{{version}}/queries), or run SQL with `query()`. See [models](/docs/{{version}}/models#raw-sql).

## When the database is missing

| Situation | What happens |
|---|---|
| `DB_NAME` or `DB_USERNAME` empty | `App::db()` returns `null`. A model's first query throws `DatabaseNotConfiguredException`. |
| Server unreachable | `DatabaseConnectionException`. The visitor sees `nodbserver.html` with status 503. |
| Wrong user or password, unknown database | `DatabaseConnectionException`. The visitor sees `dberror.html`. |

You don't need to catch these exceptions; the error handler shows the page. In debug mode the page also shows the driver's own message. See [error pages](/docs/{{version}}/errors#database-errors).

## Checking the connection

`App::dbStatus()` reports the state without throwing, for setup screens and health checks:

```php
$status = App::dbStatus();
// ['configured' => true, 'connected' => false, 'reason' => 'unknown_database',
//  'code' => 1049, 'message' => 'Database not found.', 'detail' => '...']
```

`reason` is `not_configured`, `server_unavailable`, `access_denied`, `unknown_database` or `other`. `message` is safe to show to visitors. `detail` is the driver's message, which names hosts and users, so only show it in debug mode.

From the command line:

```bash
php flo db:check    # is the database configured and reachable?
php flo db:show     # the connection and every table with its row count
php flo doctor      # the whole installation, the database included
```

## Creating tables

Tables are created with [migrations](/docs/{{version}}/migrations): PHP files in `database/migrations/` that every copy of the site runs the same way. A new site's migrations create the users table and the API tables:

```bash
php flo migrate
```

Starting data, such as categories or a demo account, comes from [seeders](/docs/{{version}}/seeding).
