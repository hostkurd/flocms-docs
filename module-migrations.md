# Module migrations

Module migrations install module state and advance a module's schema. They are run by `FloCMS\Core\Modules\ModuleMigrationRunner`, separately from the CLI's application and Composer package migrator.

## Which migration system?

| System | Reads | Tracks in | Runs through |
|---|---|---|---|
| Application/Composer package migrations | `database/migrations/` and package `extra.flocms.migrations` folders | `migrations` | `php flo migrate` |
| Module migrations | Each module manifest's ordered `migrations` files | `app_module_migrations` by default | `ModuleSystem::migrationRunner()->migrateAll()` |

The module runner also maintains `app_modules` and `app_module_audit_log`. `tablePrefix:` in `ModuleSystem::configure()` changes the `app_` prefix.

The bundled state repository and migration ledger need MySQL/MariaDB. SQLite support in CLI application migrations does not apply here. Don't register the same migration in both systems.

## Writing a migration

For the [news module](/docs/{{version}}/modules), create `modules/news/database/001_create_news_posts.php`:

```php
<?php
declare(strict_types=1);

return [
    'id' => '001_create_news_posts',
    'transactional' => false,
    'up' => [
        'CREATE TABLE IF NOT EXISTS news_posts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
];
```

List it in `module.php`:

```php
'schema_version' => 1,
'migrations' => ['database/001_create_news_posts.php'],
```

The migration's ID is explicit, unique within its module, and contains letters, digits, `_`, `.` or `-`, starting with a letter or digit, up to 190 characters. The filename alone does not supply the ID. Files execute in manifest order, with dependencies' modules first.

### Callable migrations

An array's `up` may be a callable receiving `FloCMS\Core\Database`:

```php
use FloCMS\Core\Database;

return [
    'id' => '002_fill_news_titles',
    'transactional' => true,
    'up' => static function (Database $db): void {
        $db->table('news_posts')->where('title', '=', '')->update(['title' => 'Untitled']);
    },
];
```

Or return an object implementing `FloCMS\Core\Modules\Contracts\MigrationInterface`, with `id(): string` and `up(Database $database): void`. Interface migrations run without an automatic transaction. Array migrations default to `transactional: false`; opt in for suitable data changes. MySQL DDL can commit immediately regardless of a surrounding transaction.

## Running migrations

After configuring and synchronizing the module system:

```php
use FloCMS\Core\Modules\ModuleSystem;

$report = ModuleSystem::migrationRunner()->migrateAll();
// applied, skipped, pending: lists of "module-id:migration-id"
```

The runner acquires a MySQL advisory lock, prepares the infrastructure, executes pending migrations and records their SHA-256 checksums in a numbered batch. The lock timeout defaults to 10 seconds; `migrateAll(lockTimeoutSeconds: 30)` changes it.

It processes all discovered modules, including disabled ones. After each successfully processed module, it records the manifest's installed version/schema and audits the migration. Existing optional enabled states are preserved; a new module starts from `default_enabled`, and core modules are enabled. The manager is refreshed afterwards.

`pending` lists migrations found pending at the start of their processing, including those applied by this run. `skipped` lists unchanged migrations already applied.

### Previewing

```php
$report = ModuleSystem::migrationRunner()->migrateAll(dryRun: true);
```

This lists pending IDs without executing their `up` code or updating installed module versions. It still connects to the database, acquires the lock and creates missing infrastructure/ledger tables. It is not a completely read-only database operation and does not print SQL.

## An application preparation command

The CLI includes `make:module`, but has no built-in module synchronization or migration command. Add one to your application:

```bash
php flo make:command ModulePrepare --command=modules:prepare
```

Replace `commands/ModulePrepareCommand.php` with:

```php
<?php
declare(strict_types=1);

namespace App\Commands;

use FloCMS\CLI\Console\Command;
use FloCMS\CLI\Console\Input\Definition;
use FloCMS\CLI\Console\Input\Option;
use FloCMS\Core\Modules\ModuleSystem;

final class ModulePrepareCommand extends Command
{
    protected string $name = 'modules:prepare';
    protected string $description = 'Synchronize the module registry and apply module migrations';

    protected function configure(Definition $definition): void
    {
        $definition
            ->option('dry-run', null, Option::VALUE_NONE, 'List pending module migrations')
            ->option('force', 'f', Option::VALUE_NONE, 'Skip production confirmation');
    }

    protected function handle(): int
    {
        if ($this->project()->isProduction() && !$this->option('force')
            && !$this->confirm('Synchronize modules and prepare the database?', false)) {
            return self::FAILURE;
        }

        $this->project()->boot();
        $registry = ModuleSystem::synchronizer()->sync();
        $this->info('Synchronized ' . $registry['count'] . ' module(s).');

        $dryRun = (bool) $this->option('dry-run');
        $report = ModuleSystem::migrationRunner()->migrateAll(dryRun: $dryRun);
        foreach ($report['pending'] as $id) {
            $this->line(($dryRun ? 'Pending: ' : 'Applied: ') . $id);
        }

        $this->success($dryRun ? 'Preview complete.' : 'Modules prepared.');

        return self::SUCCESS;
    }
}
```

This expects [config/modules.php](/docs/{{version}}/modules#configuring-the-runtime) to be loaded by `config/config.php` without booting providers. Synchronization happens before the registry is loaded.

```bash
php flo modules:prepare --dry-run
php flo modules:prepare --force
```

These commands exist only after adding the class above. Even with `--dry-run`, synchronization writes the registry cache and the migration runner may create its infrastructure tables.

## Updating a module

Add a new migration file instead of editing an applied one. Add its path to the manifest, advance `schema_version` for a schema change and update the module's `version`. Synchronize and run module migrations before serving the new code.

An applied file whose checksum changed causes `ModuleMigrationException`; it is not silently rerun. A failed migration stops the run. Earlier completed migrations remain recorded, so a later run skips them. Plan non-transactional DDL to recover safely after partial execution.

This runner has no automatic `down()` or rollback command. Disabling a module preserves its tables and content. Use a reviewed forward migration or an application-specific recovery procedure, with backups, when reversing a change.
