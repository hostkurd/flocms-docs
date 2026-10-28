# Migrations

Migrations create and change your database tables from code, so every copy of the site, on your computer or on the server, has the same structure.

## Connecting

Migrations use the database in `.env`. See [database](/docs/{{version}}/database) for the settings. `php flo db:check` tells you whether the database is configured and reachable, and `php flo db:show` lists its tables with row counts.

## Creating a migration

```bash
php flo make:migration create_posts_table
```

> [!STUDIO]
> Open the **Generate** tab and choose **Migration**. Studio shows the command and the file before it writes it. See [generators in Studio](/docs/studio/generators#migration).

creates `database/migrations/<timestamp>_create_posts_table.php`. A name like `create_posts_table` gives you a `CREATE TABLE` template for `posts`; `--create=posts` does the same for any name, and `--table=posts` gives an `ALTER TABLE` template for changing an existing table. The file name, without `.php`, is the migration's ID, and the timestamp keeps files in order.

A migration returns an object with `up()`, which makes the change, and `down()`, which undoes it:

```php
<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

return new class extends Migration
{
    public function up(Database $db): void
    {
        $db->query('CREATE TABLE IF NOT EXISTS `posts` (
            ' . $this->id($db) . ',
            `title` VARCHAR(190) NOT NULL,
            `created_at` DATETIME NULL
        )' . $this->tableOptions($db));
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS `posts`');
    }
};
```

Inside a migration:

- `$db` is the same `Database` class your models use, so `query()` and the [query builder](/docs/{{version}}/queries) both work.
- `$this->id($db)` writes an auto-increment primary key, and `$this->tableOptions($db)` adds InnoDB and `utf8mb4` on MySQL.
- `up()` and `down()` run in a transaction. MySQL commits `CREATE`, `ALTER` and `DROP` immediately anyway; set `public bool $transactional = false;` when a migration must not run in one.
- A migration without `down()` can't be rolled back; `migrate:rollback` stops with an error.

## Running migrations

```bash
php flo migrate              # run pending migrations
php flo migrate:status       # what ran, what's pending, changed or missing
php flo migrate:rollback     # undo the last batch
php flo migrate:fresh --seed # drop all tables, migrate again, then seed
```

> [!STUDIO]
> Open the project's **Database** tab: it lists every migration with its status and every table with its row count, and runs, rolls back and seeds with one click. Studio asks before it changes data, and on a production site before every change. See [migrations and seeding in Studio](/docs/studio/migrations).

- Migrations run in file-name order, in numbered batches. Each one is recorded in the `migrations` table with a checksum, so `migrate:status` notices edited or missing files.
- Only one `migrate` runs at a time.
- With `APP_ENV=production`, commands that change data ask first. Deployment scripts pass `--force`.
- `--pretend` lists the migrations that would run, without executing them; it does not print SQL. `--seed` runs the seeders after a normal migration run, not a pending-migration preview.
- `migrate:rollback --step=3` undoes the last three migrations instead of the last batch.

> [!CAUTION]
> `migrate:fresh` drops **every** table in the database. It asks first, and refuses on production without `--force`.

> [!WARNING]
> Never edit a migration that has already run on another copy of the site. `migrate:status` reports it as changed there, but the change is never applied. Create a new migration for the next change instead.

## Migrations from packages

The users table is a migration in your `database/migrations/`. Packages ship their own migrations: flocms-api's create the API tables. `php flo migrate` runs them straight from `vendor/`, so on a new site one command sets up everything. They're tracked per package:

```bash
php flo migrate:status --package=hostkurd/flocms-api
php flo migrate --package=hostkurd/flocms-api
```

A package registers its migration folders in its `composer.json`. See [writing commands](/docs/{{version}}/writing-commands#commands-migrations-and-routes-from-packages).

Core's module migration format also works: a file that returns `['up' => [...SQL...], 'down' => [...]]`.

Accepting that array format does not make `php flo migrate` discover module manifests. The core module runner reads the manifest's explicit migration files and uses its own state and ledger tables. See [module migrations](/docs/{{version}}/module-migrations).

## Seeders

Starting data, such as categories or a demo account, is added by seeders. See [seeding](/docs/{{version}}/seeding).
