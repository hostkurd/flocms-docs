# Commands

`php flo` is the FloCMS command line. It creates code, runs migrations, manages users and checks your installation. Every command comes from Composer packages, so `composer update` brings new commands and fixes.

## Getting help

```bash
php flo                          # every command, grouped
php flo help make:controller     # usage, arguments, options and examples
php flo list api                 # the commands of one group
```

- **Abbreviations** work when they're unambiguous: `php flo mig` runs `migrate`.
- **Typos** get a suggestion of the closest command.
- **Exit codes:** `0` success, `1` failure, `2` invalid usage. Errors go to STDERR, so scripts and cron jobs can rely on them.

Options every command accepts:

| Option | Meaning |
|---|---|
| `-h, --help` | Show the command's usage |
| `-q, --quiet` | Only print errors |
| `-v`, `-vv`, `-vvv` | More output; `-vvv` adds stack traces |
| `-n, --no-interaction` | Never ask; use defaults |
| `--env=ENV` | Run with `APP_ENV=ENV` |

## Application

| Command | Description |
|---|---|
| `about` | PHP, extensions, package versions, environment, database and paths |
| `doctor` | Health check of the installation; exits with 1 when a check fails |
| `key:generate` | Set `APP_KEY` in `.env` |
| `env:check` | Keys of `.env.example` missing from `.env` |
| `serve` | PHP's built-in server for `public/` (development only) |
| `down` / `up` | [Maintenance mode](/docs/{{version}}/maintenance) |

## Generators

All generators take `--dry-run` (show what would be written) and `--force` (overwrite).

| Command | Creates |
|---|---|
| `make:controller <Name>` | `controllers/NameController.php` (`--admin`, `--resource`, `--model`) |
| `make:model <Name>` | `models/NameModel.php` (`--table=`, `--migration`) |
| `make:view <controller> [view]` | `views/<controller>/<view>.html` and the controller action |
| `make:route <Name>` | Controller, model and view |
| `make:migration <name>` | A migration in `database/migrations` |
| `make:seeder <Name>` | A seeder in `database/seeders` |
| `make:api-controller <Name>` | `api/Controllers/NameController.php` |
| `make:resource <Name>` | `api/Resources/NameResource.php` |
| `make:command <Name>` | `commands/NameCommand.php` |
| `make:test <Name>` | A unit test, or a feature test with `--feature` |

`delete:controller`, `delete:model`, `delete:view` and `delete:route` show every file they will delete or edit, and ask first.

To change what the generators write, run `php flo stub:publish` and edit the copies in `stubs/`.

## Database, cache and inspection

| Command | Description |
|---|---|
| `migrate`, `migrate:status`, `migrate:rollback`, `migrate:fresh` | See [migrations](/docs/{{version}}/migrations) |
| `db:seed`, `db:show`, `db:check` | Seed, list tables, check the connection |
| `cache:clear`, `view:clear` | Empty `storage/cache` or the compiled templates |
| `optimize`, `optimize:clear` | Compile every template ahead of time, or clear both caches |
| `route:list` | Page routes and API routes in one table, with permissions |
| `permission:list` | Roles, their permissions, and who can run each action |

## Users, translations and logs

| Command | Description |
|---|---|
| `user:create`, `user:password`, `user:role`, `user:suspend`, `user:unsuspend`, `user:list` | Manage users |
| `login:unlock <email\|ip>` | Unlock a throttled admin login |
| `lang:missing`, `lang:add` | Check and add translation files |
| `log:tail`, `log:clear`, `log:rotate`, `storage:check` | Logs and storage folders |
| `schedule:run`, `schedule:list` | The [scheduler](/docs/{{version}}/scheduler) |

## Your own commands

Classes in `commands/` (namespace `App\Commands`) are commands too. `php flo make:command SendReports` creates one:

```php
<?php
declare(strict_types=1);

namespace App\Commands;

use FloCMS\CLI\Console\Command;
use FloCMS\CLI\Console\Input\Definition;
use FloCMS\CLI\Console\Input\Option;

final class SendReportsCommand extends Command
{
    protected string $name = 'reports:send';

    protected string $description = 'Email the weekly reports';

    protected function configure(Definition $definition): void
    {
        $definition->option('dry-run', null, Option::VALUE_NONE, 'Do not send');
    }

    protected function handle(): int
    {
        $this->success('Reports sent.');

        return self::SUCCESS;
    }
}
```

Inside `handle()`, read input with `argument()` and `option()`, write with `info()`, `success()`, `warn()`, `error()` and `table()`, and ask with `ask()`, `confirm()`, `choice()` and `secret()`.
