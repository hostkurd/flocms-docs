# Modules

A module groups one application's services, controllers, routes, migrations and extensions behind a manifest. Modules can declare dependencies and be enabled or disabled without removing their source files.

The module classes live in `FloCMS\Core\Modules`. The skeleton does not configure or boot them automatically. This guide adds a `news` module and the setup it needs.

## Creating a module

```bash
php flo make:module news
```

This creates `modules/news/module.php` and `modules/news/src/NewsServiceProvider.php`, using the namespace `App\Modules\News`. It does not synchronize a registry, run migrations or enable the module.

Add the files used in this guide:

| File | Purpose |
|---|---|
| `modules/news/module.php` | Manifest |
| `modules/news/src/NewsServiceProvider.php` | Register and boot services |
| `modules/news/src/NewsService.php` | Example application service |
| `modules/news/routes/api.php` | API route registration |
| `modules/news/database/001_create_news_posts.php` | Module migration |
| `modules/news/Integrations/page-blocks.php` | Optional extension, covered in [module extensions](/docs/{{version}}/module-extensions) |

Create the directories before synchronizing. Every declared route, extension and migration file must exist.

## The manifest

Replace the generated manifest with:

```php
<?php
declare(strict_types=1);

return [
    'id' => 'news',
    'name' => 'News',
    'description' => 'News posts and their public API.',
    'version' => '1.0.0',
    'schema_version' => 1,
    'kind' => 'optional',
    'default_enabled' => false,
    'priority' => 1000,
    'dependencies' => [],
    'autoload' => ['App\\Modules\\News\\' => 'src'],
    'providers' => [App\Modules\News\NewsServiceProvider::class],
    'routes' => ['api' => 'routes/api.php'],
    'migrations' => ['database/001_create_news_posts.php'],
];
```

| Field | Meaning |
|---|---|
| `id` | Required; matches the directory name; lowercase letters, digits and dashes, starting with a letter |
| `name` | Required; display name, 1–150 bytes |
| `version` | Required; semantic version, such as `1.0.0` |
| `schema_version` | Required; non-negative integer identifying the required database schema |
| `kind` | Required; `core` or `optional` |
| `default_enabled` | Initial state; defaults to `false`; must be `true` for a core module |
| `description` | Optional text |
| `priority` | Ordering among modules, lower first; defaults to `1000`; dependencies still come first |
| `dependencies` | Module IDs or maps with `id` and `minimum_version` |
| `autoload` | PSR-4 namespace prefixes mapped to module-relative directories |
| `providers` | Service provider class names |
| `routes` | Channel names mapped to route files; the API loader uses `api` by default |
| `extensions` | Extension names mapped to files |
| `migrations` | Ordered list of migration files, not directories |
| `controllers`, `models` | Class ownership declarations, including compatibility short names |
| `metadata` | Optional application-defined array |

Paths are relative to the module and cannot be absolute or contain `..`. IDs, namespace prefixes and declared class ownership must not conflict with another module. See [module lifecycle](/docs/{{version}}/module-lifecycle) for dependencies and validation.

## Registering a service

Create `modules/news/src/NewsService.php`:

```php
<?php
declare(strict_types=1);

namespace App\Modules\News;

final class NewsService
{
    public function title(): string
    {
        return 'News';
    }
}
```

Replace `modules/news/src/NewsServiceProvider.php` with:

```php
<?php
declare(strict_types=1);

namespace App\Modules\News;

use FloCMS\Core\Modules\Contracts\ModuleProviderInterface;
use FloCMS\Core\Support\Container;

final class NewsServiceProvider implements ModuleProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(NewsService::class, NewsService::class);
    }

    public function boot(Container $container): void
    {
        // Services from all enabled providers have been registered here.
    }
}
```

The bootstrapper registers the module autoloader, then calls `register()` on enabled providers in dependency order. Only after all registrations does it call their `boot()` methods. Repeated `boot()` calls on the same bootstrapper do nothing. See [service container](/docs/{{version}}/container).

## Configuring the runtime

Create `config/modules.php`:

```php
<?php
declare(strict_types=1);

use FloCMS\Core\App;
use FloCMS\Core\Modules\ModulePaths;
use FloCMS\Core\Modules\ModuleSystem;
use FloCMS\Core\Support\Container;

ModuleSystem::configure(
    paths: ModulePaths::fromRoot(ROOT),
    database: App::db(),
    container: new Container(),
    allowRuntimeDiscovery: false,
    tablePrefix: 'app_'
);
```

Load this file at the end of `config/config.php`, after database settings:

```php
require_once ROOT . '/config/modules.php';
```

This configures paths and services without booting providers. Keep configuration separate from booting so a deployment command can synchronize a missing registry first. The example uses your configured MySQL/MariaDB database; complete [database setup](/docs/{{version}}/database) first.

`ModuleSystem::configure()` resets the previous module runtime. Call it once per application bootstrap, before resolving module services.

### Paths and custom repositories

`ModulePaths::fromRoot(ROOT)` selects `modules/` as the discovery root and `storage/cache/` for `modules.php` and `modules-state.json`. Pass its `$modules` and `$cache` arguments to change those relative directories.

For several module roots or separate cache paths:

```php
$paths = ModulePaths::custom(
    root: ROOT,
    moduleDirectories: ['modules', 'packages/site-modules'],
    registryCache: 'storage/cache/modules.php',
    stateCache: 'storage/cache/modules-state.json'
);
```

The facade also accepts `repository:` implementing `ModuleStateRepositoryInterface`. Without one, a configured database supplies `DatabaseModuleStateRepository`. Other database drivers need their own state repository and migration runner; the bundled implementations use MySQL/MariaDB.

## Preparing and enabling the module

Add the [module migration](/docs/{{version}}/module-migrations#writing-a-migration) and the route file below before synchronizing. Then follow [the preparation command](/docs/{{version}}/module-migrations#an-application-preparation-command) to synchronize and migrate, and [changing module state](/docs/{{version}}/module-lifecycle#changing-module-state) to enable `news`.

## Booting page requests

In `public/index.php`, after the application bootstrap and before `App::run()`, add:

```php
\FloCMS\Core\Modules\ModuleSystem::bootstrapper()->boot();
```

Module autoloading alone does not create conventional page URLs. The page router still resolves `FloCMS\Controllers\NameController`. To keep an existing page controller inside a module, declare a short name in `controllers`, put its class in `Http/NameController.php`, and keep the `FloCMS\Controllers` namespace. Models have the equivalent `models` declaration and `Models/NameModel.php` layout.

New service and API classes should use their own PSR-4 namespace. Declaring a namespaced controller does not change how the conventional page router selects classes.

The page runtime does not automatically check module state. A module-owned page action can turn an unavailable module into a 404:

```php
if (!\FloCMS\Core\Modules\ModuleSystem::manager()->isEnabled('news')) {
    throw new \FloCMS\Core\HttpException(404);
}
```

`assertEnabled()` and `assertEnabledForController()` instead throw `ModuleUnavailableException`; applications decide how to handle it.

## Loading module API routes

Create `modules/news/routes/api.php`:

```php
<?php
declare(strict_types=1);

use App\Modules\News\NewsService;
use FloCMS\Api\Router;

return static function (Router $router): void {
    $router->get('/v1/news', static fn (NewsService $news): array => ['title' => $news->title()])
        ->name('news.index');
};
```

In `public/api.php`, after bootstrap, use the module container instead of creating another container, and boot providers before loading routes:

```php
use FloCMS\Core\Modules\ModuleSystem;

$container = ModuleSystem::container();
ModuleSystem::bootstrapper()->boot();
```

Inside the existing `/api` router group, after loading the application's route file, add:

```php
(new \FloCMS\Api\RouteLoader($router, $container))->loadModules(
    ModuleSystem::registry(),
    ModuleSystem::manager()
);
```

Add `modules: ModuleSystem::manager()` to the existing kernel constructor, retaining its debug and logger arguments:

```php
$kernel = new \FloCMS\Api\Kernel(
    router: $router,
    container: $container,
    modules: ModuleSystem::manager(),
    debug: $debug,
    logger: $logger
);
```

The enabled `news` module now responds at `/api/v1/news`. The loader reads the `api` channel, tags each route with its module ID and skips disabled modules by default. The kernel's module manager also checks tagged routes at dispatch and returns 404 when unavailable. Keep the existing middleware and authentication aliases.

Route files may return a registration callable taking the router and optionally the container, or a list of definitions with `method`/`methods`, `path`, `handler`, `name`, `module` and `middleware`. Channel selection is `loadModules($registry, $manager, $channel = 'api', $enabledOnly = true)`; it does not create a page-channel dispatcher.
