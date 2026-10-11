# Module lifecycle

Module source, installation state and runtime availability are separate. A directory can exist while its migrations are pending or its module is disabled. Start with [modules](/docs/{{version}}/modules) to configure the runtime.

## Discovery and synchronization

`ModuleDiscovery` reads `module.php` from immediate subdirectories of each configured module root. Directories without a manifest are skipped; invalid directory names, escaping paths and invalid manifests are rejected.

`ModuleSynchronizer::sync()` validates the discovered registry and writes an atomic compiled PHP cache:

```php
use FloCMS\Core\Modules\ModuleSystem;

$report = ModuleSystem::synchronizer()->sync();
// count, modules (IDs), cache (path), fingerprint
```

Synchronize after adding, removing or changing a module manifest. Synchronization does not migrate or enable modules. Run it before resolving the registry in that process; a registry already loaded by `ModuleSystem` remains in memory until reset/reconfiguration or the next process.

With `allowRuntimeDiscovery: false`, a missing compiled registry causes a `LogicException`. Production requests use that registry rather than scanning module roots. Development can opt into discovery when the compiled file is missing, but an existing cache is still used.

The registry fingerprint describes the compiled definitions; it is not a scan for changes to source manifests. Deployment synchronization publishes those changes.

## Dependencies and ordering

A dependency can name another module or require a minimum version:

```php
'dependencies' => [
    'system',
    ['id' => 'media', 'minimum_version' => '1.2.0'],
],
```

All dependencies must be present in the discovered registry. Self-dependencies and cycles are rejected. A core module cannot depend on an optional module.

`installationOrder()` puts dependencies first; priority and ID provide the base ordering. Use it for migrations and provider booting. Registry validation also checks source versions, duplicate IDs/namespaces/class ownership and the existence of declared directories and files.

```php
$registry = ModuleSystem::registry();
$module = $registry->get('news');          // ModuleDefinition or null
$module = $registry->require('news');      // throws when unknown
$dependencies = $registry->dependencies('news');
$dependents = $registry->dependents('news');
```

`all()` returns definitions keyed by ID; `coreIds()` returns the core IDs. `moduleForController($class)` finds a module's declared controller ownership.

## Inspecting state

```php
$manager = ModuleSystem::manager();
$available = $manager->isEnabled('news');
$states = $manager->runtimeStates();
```

Each runtime state includes `installed`, `enabled`, source/installed versions, source/installed schema versions, `status`, dependencies, `missingDependencies`, `enabledDependents`, `canEnable`, `canDisable`, `locked` and a `reason`.

| Status | Meaning |
|---|---|
| `discovered` | Infrastructure exists, but the module has not been installed |
| `migration-required` | Infrastructure is missing, or the installed schema is behind |
| `update-required` | Installed module version is behind the source version |
| `enabled` | Stored enabled state, with version/schema status current |
| `disabled` | Installed and switched off |
| `incompatible` | A required module is disabled, missing from installed state or outdated |

`isEnabled()` is the availability check: it includes installed version/schema and recursive dependency checks. Use it rather than treating the raw `enabled` field as proof that code can run.

Before database infrastructure exists, core modules and optional modules marked `default_enabled` may be available from their manifests. This fallback does not mean they have been installed. Once infrastructure exists, even core modules need an installed record and current schema/version.

`storedStates()` returns persisted rows keyed by module ID. `infrastructureReady()` checks the state backend. Pass `true` to their refresh parameter, or call `refresh()`, to reload state instead of using the cached result. `publicState()` provides enabled IDs, versions and schema versions; authorization is still the application's responsibility.

## Changing module state

After [synchronizing and migrating](/docs/{{version}}/module-migrations), change an optional module with:

```php
$state = ModuleSystem::manager()->setEnabled('news', true, $actorId);
$state = ModuleSystem::manager()->setEnabled('news', false, $actorId);
```

`$actorId` is an optional user ID for the audit log. Protect any admin endpoint that calls this method with your application's permissions and CSRF/authentication rules.

State changes require a repository, installed infrastructure and an installed module record. Core modules cannot be disabled. Enable dependencies first; disable enabled dependents before disabling a dependency. Apply pending schema/version updates before enabling a module.

For a local application command, generate:

```bash
php flo make:command ModuleToggle --command=modules:toggle
```

Replace `commands/ModuleToggleCommand.php` with:

```php
<?php
declare(strict_types=1);

namespace App\Commands;

use FloCMS\CLI\Console\Command;
use FloCMS\CLI\Console\Input\Argument;
use FloCMS\CLI\Console\Input\Definition;
use FloCMS\Core\Modules\ModuleSystem;

final class ModuleToggleCommand extends Command
{
    protected string $name = 'modules:toggle';
    protected string $description = 'Enable or disable an installed module';

    protected function configure(Definition $definition): void
    {
        $definition
            ->argument('id', Argument::REQUIRED, 'Module ID')
            ->argument('state', Argument::REQUIRED, 'on or off');
    }

    protected function handle(): int
    {
        $value = (string) $this->argument('state');
        if (!in_array($value, ['on', 'off'], true)) {
            $this->invalid('State must be on or off.');
        }

        $this->project()->boot();
        $state = ModuleSystem::manager()->setEnabled((string) $this->argument('id'), $value === 'on');
        $this->success($state['id'] . ': ' . $state['status']);

        return self::SUCCESS;
    }
}
```

```bash
php flo modules:toggle news on
php flo modules:toggle news off
```

`modules:toggle` is the application command you just created, not a built-in CLI command.

## Caches and long-running processes

By default, `modules.php` holds the compiled definitions and `modules-state.json` holds state tied to the registry fingerprint. State changes and migrations refresh the manager's cache.

`php flo cache:clear` preserves module caches. `cache:clear --all` can remove them; synchronize again before serving requests. `php flo optimize` compiles templates and does not synchronize modules.

The provider bootstrapper boots once. Changing state does not undo a provider's already registered services. In a long-running worker, rebuild the module runtime and container when module code/state changes. Likewise, clear an `ExtensionRegistry` or `PageBlockRegistry` cache when reusing it after a state change.

## Deployment order

1. Put the application into maintenance mode and deploy code/dependencies.
2. Run the application's preparation command to synchronize and migrate modules.
3. Enable newly installed optional modules if needed.
4. Compile templates and bring the site back.

See [module migrations](/docs/{{version}}/module-migrations) for a preparation command and [deployment](/docs/{{version}}/deployment) for the surrounding deployment steps.

## Troubleshooting

| Error or behavior | Check |
|---|---|
| `ModuleSystem has not been configured` | Load `config/modules.php` before using the facade |
| Compiled registry missing/invalid | Synchronize in a fresh deployment process; check writable cache paths |
| Declared file missing | Create the manifest's route, extension and migration files first |
| Module cannot be enabled | Inspect `runtimeStates(true)` and apply migrations/enable dependencies |
| Module API route still runs when disabled | Pass the manager to the API kernel and tag/load module routes |
| Module page still runs when disabled | Add an availability check to the page action; conventional page dispatch does not add one automatically |
| Removed module's data remains | Disabling or removing source does not uninstall tables or delete content |
