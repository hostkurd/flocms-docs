# Module extensions

Extensions let application code collect integrations from modules without hard-coding every module name. A manifest maps an extension name to a file, and the application decides what that file's returned value means.

## Declaring an extension

Add to the [news module's manifest](/docs/{{version}}/modules#the-manifest):

```php
'extensions' => ['navigation' => 'Integrations/navigation.php'],
```

Create `modules/news/Integrations/navigation.php` before synchronizing:

```php
<?php
return [
    ['label' => 'News', 'path' => '/news'],
];
```

Then load integrations:

```php
use FloCMS\Core\Modules\ModuleSystem;

$navigation = ModuleSystem::extensions()->all('navigation');
// ['news' => [['label' => 'News', 'path' => '/news']]] when news is enabled
```

`all($extension, $enabledOnly = true)` returns values keyed by module ID. Only enabled modules are included by default. `all('navigation', false)` includes disabled modules too; use this deliberately for administration, not public availability checks.

An extension file must return an array, callable or object. The registry loads it but does not invoke a returned callable or merge arrays for you. Invalid values throw `RuntimeException`. `clear()` drops loaded values so the next call reads them again.

## Page-block integration

`PageBlockRegistry` is a compatibility integration for applications with a page builder. It supplies block definitions, resource lists and resolvers; the application must supply the editing interface, persistence and rendering.

Declare the integration in `module.php`:

```php
'extensions' => ['page-blocks' => 'Integrations/page-blocks.php'],
```

The key `page_blocks` is also supported. For older modules without either declaration, the registry looks for `Integrations/page-blocks.php`. Prefer an explicit declaration for new modules.

### Definitions and resolvers

Create `modules/news/Integrations/page-blocks.php`:

```php
<?php
declare(strict_types=1);

return [
    'module_id' => 'news',
    'definitions' => [
        [
            'type' => 'news_list',
            'label' => 'News list',
            'default_settings' => ['limit' => 5],
        ],
    ],
    'resolvers' => [
        'news_list' => static function (array $settings, string $lang): array {
            $limit = max(1, min(20, (int) ($settings['limit'] ?? 5)));

            return ['title' => 'News', 'limit' => $limit, 'items' => []];
        },
    ],
    'disabled_data' => [
        'news_list' => ['title' => 'News', 'items' => []],
    ],
    'resources' => [
        'news_categories' => static fn (string $lang): array => [],
    ],
];
```

Replace the empty item/category lists with your application's queries. This example does not assume a category table or a page-builder schema.

The returned `module_id` must match the owner. Block types are lowercase letters, digits and underscores, starting with a letter, and must be unique across modules. Other definition fields, such as `label`, belong to the consuming application.

### Reading blocks

```php
use FloCMS\Core\Modules\ModuleSystem;
use FloCMS\Core\Modules\PageBlockRegistry;

$manager = ModuleSystem::manager();
$definitions = PageBlockRegistry::definitions($manager);
$resources = PageBlockRegistry::resources($manager, 'en');
$owner = PageBlockRegistry::moduleIdForType('news_list');
$defaults = PageBlockRegistry::defaultSettingsForType('news_list');
$data = PageBlockRegistry::resolve($manager, 'news_list', ['limit' => 3], 'en');
```

| Method | Behavior |
|---|---|
| `definitions($manager = null)` | Flattened definitions from enabled modules |
| `resources($manager, $lang)` | Resource lists from enabled modules; providers receive the language |
| `moduleIdForType($type)` | Owning module ID, or `null`; includes disabled modules |
| `defaultSettingsForType($type)` | Defaults or `null`; includes disabled modules |
| `resolve($manager, $type, $settings, $lang)` | Call the enabled module's resolver; use `disabled_data` when disabled; `null` for an unknown type |
| `clear()` | Clear the static integration cache |

Pass `null` for `$manager` to use `ModuleSystem::manager()`. Missing resolvers and non-array resolver results become `[]`; invalid resource providers throw. Resource providers should return arrays. Keep resource names unique when combining integrations, since a later value replaces an earlier one with the same key.

## State changes and caching

`ExtensionRegistry` caches loaded values per extension and enabled-only mode. `PageBlockRegistry` caches integration definitions statically, then checks module availability when resolving data. In a long-running process, clear the relevant registry after changing source/state and rebuild providers when needed. See [module lifecycle](/docs/{{version}}/module-lifecycle#caches-and-long-running-processes).
