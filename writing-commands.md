# Writing commands

Your own `php flo` commands are ordinary classes in `commands/`. Use them for imports, reports, clean-ups and anything else you run by hand or from the [scheduler](/docs/{{version}}/scheduler).

## Creating a command

```bash
php flo make:command SendReports
php flo make:command SendReports --command=reports:send
```

creates `commands/SendReportsCommand.php` (namespace `App\Commands`). Every class there is found automatically; there is nothing to register. The skeleton's `commands/LoginUnlockCommand.php`, which adds `php flo login:unlock`, is a complete example.

```php
<?php
declare(strict_types=1);

namespace App\Commands;

use FloCMS\CLI\Console\Command;
use FloCMS\CLI\Console\Input\Argument;
use FloCMS\CLI\Console\Input\Definition;
use FloCMS\CLI\Console\Input\Option;

final class SendReportsCommand extends Command
{
    protected string $name = 'reports:send';

    protected string $description = 'Email the weekly reports';

    protected array $examples = ['php flo reports:send --dry-run'];

    protected function configure(Definition $definition): void
    {
        $definition
            ->argument('team', Argument::OPTIONAL, 'Only this team', 'all')
            ->option('dry-run', null, Option::VALUE_NONE, 'Do not send')
            ->option('to', 't', Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, 'Extra recipients');
    }

    protected function handle(): int
    {
        $rows = $this->project()->database()->table('reports')->get();

        $bar = $this->progress(count($rows));
        foreach ($rows as $row) {
            // ...
            $bar->advance();
        }
        $bar->finish();

        $this->success(count($rows) . ' reports sent.');

        return self::SUCCESS;
    }
}
```

`$name`, `$description` and `$examples` appear in `php flo` and `php flo help reports:send`. A longer explanation goes in `protected string $help`.

`handle()` returns the exit code: `self::SUCCESS` (0) or `self::FAILURE` (1).

## Arguments and options

```php
$definition
    ->argument('email', Argument::REQUIRED, 'The user')
    ->argument('files', Argument::IS_ARRAY, 'Any number of files')     // takes the rest
    ->option('force', 'f', Option::VALUE_NONE, 'Do not ask')             // a flag
    ->option('limit', null, Option::VALUE_REQUIRED, 'How many', '50')    // with a default
    ->option('format', null, Option::VALUE_OPTIONAL, 'txt or json')
    ->option('to', 't', Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, 'Repeatable');
```

| Type | |
|---|---|
| `Argument::REQUIRED`, `Argument::OPTIONAL` | One value; optional ones can have a default |
| `Argument::IS_ARRAY` | All remaining values, as an array |
| `Option::VALUE_NONE` | A flag: `--force` |
| `Option::VALUE_REQUIRED`, `Option::VALUE_OPTIONAL` | `--limit=10` |
| `Option::VALUE_IS_ARRAY` | Repeatable: `--to=a --to=b` |

The second argument of `option()` is a one-letter shortcut, such as `-f`. Read the values in `handle()` with `$this->argument('email')` and `$this->option('limit')`.

## Output

| Helper | Prints |
|---|---|
| `line()`, `info()`, `comment()` | Plain, green and yellow text |
| `success()`, `warn()`, `note()`, `error()` | Messages with a label; `warn()` and `error()` go to STDERR, and `error()` shows even with `--quiet` |
| `title()`, `section()` | Headings |
| `table($headers, $rows)`, `definitionList(...)` | Tables and key-value lists |
| `progress($total)`, `spin($message, $work)` | A progress bar, and a spinner while `$work` runs |

Inline styles work in any text: `<info>`, `<comment>`, `<error>`, `<warning>`, `<success>`, `<muted>`, `<b>`, and `<fg=red;bg=white;options=bold>`. Colors switch off automatically when the output isn't a terminal, or when `NO_COLOR` is set.

## Asking questions

```php
$name  = $this->ask('Project name', 'my-site');
$email = $this->ask('Email', null, fn (string $v): ?string => str_contains($v, '@') ? null : 'Enter an email address.');
$sure  = $this->confirm('Delete all drafts?', false);
$env   = $this->choice('Environment', ['local', 'production'], 'local');
$token = $this->secret('API token');   // hidden input
```

`ask()` and `secret()` take a check as the last argument: return a message to ask again, or `null` to accept the answer.

With `--no-interaction`, prompts return their default without asking, so commands still work in cron jobs and scripts.

## Stopping with an error

```php
$this->fail('The import file is empty.');       // exit code 1, no stack trace
$this->invalid('--limit must be a number.');    // exit code 2, invalid usage
```

Both stop the command immediately.

## Using the application

- `$this->project()` gives you the project: `root()`, `path('storage/logs')`, `env('KEY')`, `isProduction()` and `database()`, the same `Database` your models use.
- `$this->project()->boot()` loads the application like a page request does, with `.env`, `config/config.php` and the path constants. Call it before using models or `Config`.
- `$this->call('cache:clear')` runs another command.

## Commands, migrations and routes from packages

A Composer package adds commands, migrations and `route:list` rows through its `composer.json`. `php flo` reads `vendor/composer/installed.json`, so nothing needs to be copied or registered:

```json
"extra": {
    "flocms": {
        "commands": ["Vendor\\Package\\Console\\SyncCommand"],
        "migrations": ["database/migrations"],
        "route-providers": ["Vendor\\Package\\Console\\Routes"]
    }
}
```

- `commands` are `FloCMS\CLI\Console\Command` classes.
- `migrations` are folders that `php flo migrate` runs straight from `vendor/`, tracked per package (`--package=vendor/package`).
- `route-providers` implement `FloCMS\CLI\Routing\RouteProvider` and add rows to `route:list`.

flocms-api registers its `api:*` commands this way. Your project can use the same keys in its own `composer.json`. A later registration with the same name wins, so a class in `commands/` can replace a built-in command.

## Testing commands

`ApplicationTester` runs commands with the output and exit code captured, and answers prompts for you:

```php
use FloCMS\CLI\Console\Testing\ApplicationTester;

$tester = ApplicationTester::forProject(dirname(__DIR__, 2));
$tester->run(['user:create', '--role=editor'], ['Ada', 'ada@example.com', 'secret-pass', 'secret-pass']);

self::assertSame(0, $tester->getStatusCode());
self::assertStringContainsString('created', $tester->getDisplay());
```

To test one command on its own, use `CommandTester`:

```php
use FloCMS\CLI\Console\Testing\CommandTester;
use FloCMS\CLI\Project;

$tester = new CommandTester(new SendReportsCommand(), new Project($root));
$tester->execute(['team' => 'sales', '--dry-run' => true]);
```

`php flo make:test Reports --feature` creates a feature test that starts with `ApplicationTester`.

## Shell completion

```bash
php flo completion bash >> ~/.bashrc                               # bash
php flo completion zsh > ~/.zfunc/_flo                             # zsh
php flo completion fish > ~/.config/fish/completions/flo.fish      # fish
php flo completion powershell | Out-String | Invoke-Expression     # PowerShell; add to $PROFILE
```

Command names, aliases and options complete, your own commands and package commands included.
