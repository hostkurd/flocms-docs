# Service container

The service container creates objects and supplies their constructor dependencies. Use it to connect an interface to its implementation, share a service, or replace a dependency in a test.

The class is `FloCMS\Core\Support\Container`. It implements `ContainerInterface`, which the API accepts. Conventional page controllers are created by the page runtime; adding a binding does not automatically give those controllers constructor injection.

## Resolving a class

```php
use FloCMS\Core\Support\Container;

final class PriceFormatter
{
    public function format(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}

final class InvoicePresenter
{
    public function __construct(public readonly PriceFormatter $prices)
    {
    }
}

$container = new Container();
$presenter = $container->get(InvoicePresenter::class);
```

An instantiable class needs no registration when its constructor dependencies can also be resolved. Each unbound `get()` creates a new object.

## Binding an interface

```php
interface Clock
{
    public function now(): DateTimeImmutable;
}

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}

$container->bind(Clock::class, SystemClock::class);
$clock = $container->get(Clock::class);
```

The concrete value can be a class name or a factory receiving the container:

```php
$container->bind('report.directory', static fn (Container $container): string => ROOT . '/storage/reports');
```

An interface cannot be instantiated on its own. Bind it before resolving a class whose constructor needs it.

## Sharing instances

| Method | Behavior |
|---|---|
| `bind($id, $classOrFactory)` | Run the binding each time `get($id)` is called |
| `singleton($id, $classOrFactory)` | Resolve once on first use and reuse the result |
| `instance($id, $value)` | Use an object or value you already have |
| `alias($abstract, $target)` | Resolve the target whenever the alias is requested |

```php
$container->singleton(Clock::class, SystemClock::class);
$container->instance('site.name', 'My site');
$container->alias('clock', Clock::class);

$first = $container->get(Clock::class);
$second = $container->get('clock');   // the same shared object
```

An alias shares an object only when its target does. `bind()` removes an existing instance for that ID; `instance()` removes its factory. Register services before first use: adding a singleton binding does not replace an instance already cached under the same ID.

## Constructor arguments

For each constructor parameter, the container tries:

1. A named class or interface type, resolved through `get()`.
2. The parameter's default value.
3. `null` when the parameter allows it.
4. Otherwise, a `ContainerException`.

Scalar parameters are not filled from named bindings or `.env` automatically. Use a factory:

```php
use FloCMS\Core\Env;

final class ReportService
{
    public function __construct(public readonly string $directory)
    {
    }
}

$container->singleton(ReportService::class, static fn (Container $container): ReportService =>
    new ReportService((string) Env::get('REPORT_DIRECTORY', ROOT . '/storage/reports'))
);
```

Autowiring does not resolve union or intersection types. A nullable class parameter is still resolved as a class first; bind its interface explicitly rather than expecting `null` to bypass resolution.

## Using it with the API

The API kernel resolves controller constructors and typed handler dependencies through its container. Use one container for both your bindings and the kernel:

```php
use FloCMS\Api\Kernel;
use FloCMS\Api\Router;
use FloCMS\Core\Support\Container;

$container = new Container();
$container->singleton(Clock::class, SystemClock::class);
$router = new Router();
$kernel = new Kernel(router: $router, container: $container);
```

In a skeleton project, add bindings to the container already created in `public/api.php`, before the kernel handles the request. See [API controllers](/docs/{{version}}/api-controllers#arguments).

## Using it with modules

Pass the container to `ModuleSystem::configure()`. Module providers receive it in `register()` and `boot()`:

```php
use FloCMS\Core\Modules\ModulePaths;
use FloCMS\Core\Modules\ModuleSystem;

ModuleSystem::configure(ModulePaths::fromRoot(ROOT), container: $container);
```

`ModuleSystem::container()` also registers `ModulePaths`, `ModuleRegistry`, `ModuleManager` and, when configured, `Database`. Use the same container for module API routes. See [modules](/docs/{{version}}/modules).

## Inspection and errors

| Method | Does |
|---|---|
| `has($id)` | Checks for an instance, binding or existing class |
| `get($id)` | Uses the instance/binding, or autowires the class |
| `build($class)` | Creates that class directly, resolving its dependencies through `get()` |

`has()` does not prove that a class's dependencies can be resolved. `build()` bypasses a binding for the class itself.

`FloCMS\Core\Support\ContainerException` reports unknown services, non-instantiable classes, unresolved constructor arguments and circular dependencies detected by `get()`. Correct the binding or constructor; don't turn a container failure into a successful response.
