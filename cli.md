# Commands

`php flo` is the FloCMS command line. It creates code, runs migrations, manages users and checks your installation. Every command comes from Composer packages, so `composer update` brings new commands and fixes.

## Getting help

```bash
php flo                          # every command, grouped
php flo help make:controller     # usage, arguments, options and examples
php flo make:controller --help   # the same
php flo list api                 # the commands of one group
php flo list --format=json       # for scripts
```

- **Abbreviations** work when they're unambiguous: `php flo mig` runs `migrate`, and `php flo m:st` runs `migrate:status`.
- **Typos** get a suggestion of the closest command.
- **Exit codes:** `0` success, `1` failure, `2` invalid usage, such as a missing argument. Errors go to STDERR, so scripts and cron jobs can rely on them.
- **Any folder.** The project is the folder of the `flo` file, so `php ../flo make:model Post` from a subfolder still writes to `models/`.

Options every command accepts:

| Option | Meaning |
|---|---|
| `-h, --help` | Show the command's usage, arguments, options and examples |
| `-q, --quiet` | Only print errors |
| `-v`, `-vv`, `-vvv` | More output; `-vvv` adds stack traces |
| `-V, --version` | The CLI and framework versions |
| `--ansi`, `--no-ansi` | Force or disable colors (`NO_COLOR` is respected) |
| `-n, --no-interaction` | Never ask; use defaults. Commands that need an answer fail. |
| `--env=ENV` | Run with `APP_ENV=ENV` |

## Application

| Command | Description |
|---|---|
| `about [--format=json]` | PHP, extensions, package versions, environment, database and paths |
| `doctor [--format=json]` | Health check: PHP version and extensions, writable `storage/` and `views/cache/`, `.env`, `APP_KEY`, `APP_URL`, debug mode in production, database, pending migrations, rewrite rules and document root. Exits with 1 when a check fails. |
| `key:generate [--force] [--show]` | Set `APP_KEY` in `.env`; `--force` replaces an existing key |
| `env:check` | Keys of `.env.example` missing from `.env` |
| `serve [--host=127.0.0.1] [--port=8000]` | PHP's built-in server for `public/`, routed like `.htaccess` (development only) |
| `down`, `up` | [Maintenance mode](/docs/{{version}}/maintenance) |
| `completion bash\|zsh\|fish\|powershell` | A shell completion script. See [writing commands](/docs/{{version}}/writing-commands#shell-completion). |

## Generators

| Command | Creates |
|---|---|
| `make:controller <Name> [--admin] [--resource] [--model] [--api]` | `controllers/NameController.php` |
| `make:model <Name> [--table=] [--migration]` | `models/NameModel.php` |
| `make:view <controller> [view] [--admin]` | A view and its controller action |
| `make:route <Name> [view]` | Controller, model and view |
| `make:api-controller <Name> [--resource]` | `api/Controllers/NameController.php` |
| `make:resource <Name>` | `api/Resources/NameResource.php` |
| `make:middleware <Name>` | `api/Middleware/Name.php` |
| `make:request <Name>` | `api/Requests/NameRequest.php` |
| `make:migration <name> [--create=] [--table=]` | A migration in `database/migrations/` |
| `make:seeder <Name>` | `database/seeders/NameSeeder.php` |
| `make:command <Name> [--command=app:name]` | `commands/NameCommand.php` |
| `make:test <Name> [--feature]` | A test in `tests/Unit/` or `tests/Feature/` |
| `make:module <id>` | `modules/<id>/module.php` and a service provider |
| `stub:publish [stubs...] [--force]` | Copies the templates to `stubs/` |
| `delete:controller\|model\|view\|route` | Removes generated code, after showing what and asking |

All `make:` commands take `--dry-run` and `--force`. See [generators and stubs](/docs/{{version}}/generators).

For `make:module`, see [module setup](/docs/{{version}}/modules). Module preparation and state changes use application code; the [module guides](/docs/{{version}}/module-migrations#an-application-preparation-command) show custom commands you can add.

## Database

| Command | Description |
|---|---|
| `migrate [--package=] [--pretend] [--seed] [--force]` | Run pending migrations |
| `migrate:status [--package=]` | Which migrations ran, are pending, changed or missing |
| `migrate:rollback [--step=N] [--pretend]` | Undo the last batch, or the last N migrations |
| `migrate:fresh [--seed] [--force]` | Drop **every** table and migrate again |
| `db:seed [--class=DatabaseSeeder] [--force]` | Run a seeder |
| `db:show [--format=json]` | The connection and every table with its row count |
| `db:check` | Is the database configured and reachable? |

On `APP_ENV=production`, commands that change data ask first; deployment scripts pass `--force`. See [migrations](/docs/{{version}}/migrations) and [seeding](/docs/{{version}}/seeding).

## Cache

| Command | Description |
|---|---|
| `cache:clear [--all]` | Empty `storage/cache` (module caches are kept unless `--all`) |
| `view:clear` | Remove compiled templates from `views/cache` |
| `optimize` | Compile every view and template ahead of time |
| `optimize:clear` | `view:clear` and `cache:clear` together |

Clearing all caches can remove a configured module registry. After `cache:clear --all`, [synchronize modules again](/docs/{{version}}/module-lifecycle#caches-and-long-running-processes) before serving requests. `optimize` only compiles templates.

## Inspection

| Command | Description |
|---|---|
| `route:list [--method=] [--path=] [--name=] [--source=] [--format=json]` | Page routes of `controllers/`, with the permission of each admin action, and the `/api/v1` routes in one table |
| `permission:list [--format=json]` | Roles and their permissions, and which roles can run each controller action |

## Users

| Command | Description |
|---|---|
| `user:create [--role=] [--name=] [--email=] [--login=] [--password-stdin]` | Create a user; asks for anything not given, the password hidden |
| `user:password <email>` | Set a new password |
| `user:role <email> <role>` | Change a role: `user`, `editor`, `admin`, `super-admin` or a number |
| `user:suspend <email>`, `user:unsuspend <email>` | Block or allow a user |
| `user:list [--status=active\|pending\|suspended]` | List users |
| `login:unlock <email\|ip>` | Lift a [login throttle](/docs/{{version}}/authentication#login-throttling) block |

## Translations

| Command | Description |
|---|---|
| `lang:missing [--lang=ar] [--base=en] [--format=json]` | Missing, empty and extra keys per language |
| `lang:add <code> [--rtl] [--base=en] [--force]` | A new language file |

See [localization](/docs/{{version}}/localization).

## Scheduler and logs

| Command | Description |
|---|---|
| `schedule:run [--task=]` | Run the tasks that are due; cron calls it every minute |
| `schedule:list` | Every task with its next run and last result |
| `log:tail [log] [--lines=50] [--follow]` | The end of a log in `storage/logs` |
| `log:clear [log] [--force]` | Empty one log, or all of them |
| `log:rotate [--max-size=10] [--keep=5]` | Rotate large logs |
| `storage:check [--fix]` | Are the storage folders writable? `--fix` creates them. |

See [scheduler](/docs/{{version}}/scheduler) and [logging](/docs/{{version}}/errors#logging).

## API

With flocms-api installed, as on every new site, `php flo list api` shows its commands:

| Command | Description |
|---|---|
| `api:install-schema` | Create only the API tables |
| `api:token:create <user-id> <name> [--scopes=] [--expires=]` | Issue a personal access token |
| `api:token:list [--user=]`, `api:token:revoke <id>\|--user=` | List and revoke tokens |
| `api:key:create <name> [--scopes=] [--user=] [--expires=]` | Create an API key |
| `api:key:list`, `api:key:revoke <id>` | List and revoke keys |
| `api:routes` | API routes with names and middleware |
| `api:docs [--out=]` | The [OpenAPI document](/docs/{{version}}/api-openapi) |
| `api:gc [--database]` | Delete stale rate-limit and idempotency data |

## Your own commands

Classes in `commands/` are commands too, and packages can add their own. See [writing commands](/docs/{{version}}/writing-commands).
