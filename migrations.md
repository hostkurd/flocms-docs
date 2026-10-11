# Migrations and seeders

Migrations create and change your database tables from code, so every copy of the site, on your computer or on the server, has the same structure.

## Connecting

The database comes from `.env`:

- **MySQL or MariaDB:** `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET`
- **SQLite:** `DB_TYPE=sqlite` with `DB_NAME=storage/database.sqlite`
- **Any PDO driver:** `DB_DSN`

`php flo db:check` tells you whether the database is configured and reachable, and `php flo db:show` lists its tables with row counts.

## Creating a migration

```bash
php flo make:migration create_posts_table
```

creates `database/migrations/<timestamp>_create_posts_table.php`:

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

## Running migrations

```bash
php flo migrate              # run pending migrations
php flo migrate:status       # what ran, what's pending, changed or missing
php flo migrate:rollback     # undo the last batch
php flo migrate:fresh --seed # drop all tables, migrate again, then seed
```

- Migrations run in file-name order, in numbered batches. Each one is recorded in the `migrations` table with a checksum, so `migrate:status` notices edited or missing files.
- Only one `migrate` runs at a time.
- With `APP_ENV=production`, commands that change data ask first. Deployment scripts pass `--force`.
- `--pretend` shows the SQL without running it.

> [!CAUTION]
> `migrate:fresh` drops **every** table in the database. It asks first, and refuses on production without `--force`.

The users table and the API tables are migrations too, so on a new site `php flo migrate` sets up everything.

## Seeders

Seeders fill tables with starting data. They live in `database/seeders` (namespace `Database\Seeders`):

```bash
php flo make:seeder PostsSeeder
```

```php
final class DatabaseSeeder extends \FloCMS\CLI\Database\Seeder
{
    public function run(\FloCMS\Core\Database $db): void
    {
        $db->table('posts')->insert(['title' => 'Hello']);
        $this->call(PostsSeeder::class);
    }
}
```

Run them with `php flo db:seed`, or one class with `php flo db:seed --class=PostsSeeder`.
