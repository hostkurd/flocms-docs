# Database

FloCMS sites use MySQL, MariaDB or, since core 2.9, SQLite. A site doesn't need a database to run: the welcome page, static pages and the documentation you're reading work without one. You add a database when your site stores data.

## Supported databases

| Database | Website connection (`App::db()`) | CLI connection (`php flo`) |
|---|---|---|
| MySQL | Supported through `pdo_mysql`; the default | Supported; the default `DB_TYPE` |
| MariaDB | Supported through the MySQL driver (`pdo_mysql`) | Supported with `DB_TYPE=mysql` or `DB_TYPE=mariadb` |
| SQLite | Supported with `DB_TYPE=sqlite` and `pdo_sqlite`, core 2.9 or newer | Supported with `DB_TYPE=sqlite` and `pdo_sqlite` |
| PostgreSQL and other engines | Not supported | A custom `DB_DSN` can open a PDO connection, but this does not provide full query-builder or migration support |

The query builder uses backtick-quoted identifiers, which MySQL, MariaDB and SQLite all accept. PDO connectivity alone does not make PostgreSQL or another engine supported.

### MySQL and MariaDB

Use MySQL or MariaDB for most websites, and on shared hosting such as cPanel. Both use the same connection settings below and require PHP's `pdo_mysql` extension. MariaDB does not need a separate PDO driver.

### SQLite

SQLite keeps the whole database in one file. It suits small sites, local development and tests. Set:

```ini
DB_TYPE=sqlite
DB_NAME=storage/database.sqlite
```

`DB_NAME` is the file, relative to the project root unless it is a full path. SQLite doesn't use `DB_HOST`, `DB_PORT`, a username or a password, and PHP needs `pdo_sqlite`. Create the file with the migrations:

```bash
php flo migrate
php flo db:check
```

- The website connection never creates the file. When it is missing, the page says so and names `DB_NAME`, so a typo can't look like an empty site.
- Foreign keys are switched on, and a write waits up to 5 seconds for another one to finish.
- Each migration's SQL must also work on SQLite. The migration helpers `id()` and `tableOptions()` adapt to MySQL and SQLite; application and package migrations may still contain engine-specific SQL. See [migrations](/docs/{{version}}/migrations).
- Keep the file out of the web root. `storage/` is a good place: the web server serves only `public/`.

> [!IMPORTANT]
> With core 2.8 or older, these settings apply to `php flo` only, and the website keeps connecting to MySQL. Run `composer update hostkurd/flocms-core` to get 2.9.

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

`config/config.php` copies these into the `db.*` settings, which the connection reads. While `DB_NAME` or `DB_USERNAME` is empty, the site runs without a database. With SQLite, only `DB_NAME` is needed.

> [!NOTE]
> `App::db()` reads `DB_TYPE` (or `db.type`) since core 2.9; any value other than `sqlite` means MySQL. `DB_DSN` is read by the CLI connection factory only. A custom `DB_DSN` takes precedence over `DB_TYPE` for CLI connections and requires the corresponding PDO extension. It does not guarantee that CLI database commands, the query builder, or migrations work with that engine.

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
