# Seeding

Seeders fill your tables with data: the categories every copy of the site needs, or demo content for development. Where [migrations](/docs/{{version}}/migrations) create the structure, seeders add the rows.

## Creating a seeder

```bash
php flo make:seeder Categories
```

creates `database/seeders/CategoriesSeeder.php` (`make:seeder CategoriesSeeder` gives the same file):

```php
<?php
declare(strict_types=1);

namespace Database\Seeders;

use FloCMS\CLI\Database\Seeder;
use FloCMS\Core\Database;

final class CategoriesSeeder extends Seeder
{
    public function run(Database $db): void
    {
        foreach (['News', 'Guides', 'Releases'] as $name) {
            $db->table('categories')->insert(['name' => $name]);
        }
    }
}
```

`$db` is the database connection, so the [query builder](/docs/{{version}}/queries) and `query()` work as in a model. Seeders live in `database/seeders/` with the namespace `Database\Seeders`.

## Running seeders

```bash
php flo db:seed                          # runs DatabaseSeeder
php flo db:seed --class=CategoriesSeeder # runs one seeder
```

On `APP_ENV=production`, `db:seed` asks before it runs; add `--force` in scripts.

Seeders also run after migrations with `--seed`:

```bash
php flo migrate --seed
php flo migrate:fresh --seed    # rebuild the database from scratch, then seed
```

## The DatabaseSeeder

`php flo db:seed` without `--class` runs `Database\Seeders\DatabaseSeeder`. New projects don't include one, so create it to list the seeders that make up a complete database:

```bash
php flo make:seeder Database
```

```php
final class DatabaseSeeder extends Seeder
{
    public function run(Database $db): void
    {
        $this->call(CategoriesSeeder::class, PostsSeeder::class);
    }
}
```

`call()` runs other seeders in the order you list them.

## Seeding safely

A seeder runs every time you call it, so running it twice inserts its rows twice. Write seeders that can run again without harm, for example by checking first:

```php
public function run(Database $db): void
{
    if ($db->table('categories')->count() > 0) {
        return;
    }

    // insert the categories
}
```

> [!TIP]
> Don't create admin accounts in a seeder with a password in the code. Use `php flo user:create`, which asks for the password. See [authentication](/docs/{{version}}/authentication#creating-users).
