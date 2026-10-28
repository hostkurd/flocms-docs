# Framework and packages

FloCMS has an application skeleton and a set of Composer packages. The skeleton gives you a ready-to-run project; the packages provide the runtime and tools that project uses. A project installs the four framework packages, and adds the admin panel and the API when it needs them.

## Versions in this guide

These pages describe the FloCMS 2.0 skeleton. A new project installs these versions:

| Package | Version | Responsibility |
|---|---|---|
| `hostkurd/flocms-core` | `2.8.0` (2.9.0 with `composer update`) | Page routing, controllers, models, database access, templates, configuration, authentication, rate limits, HTTP classes, the service container and modules |
| `hostkurd/flocms-cli` | `2.0.1` | `php flo`, code generators, migrations, seeders, scheduling, maintenance and project inspection |
| `hostkurd/flocms-uploader` | `1.2.1` | File validation, storage disks, image versions, videos and resumable uploads |
| `hostkurd/flocms-captcha` | `1.1.0` | Session-backed, single-use image challenges and custom renderers |

Optional packages, added by [choose what you build](/docs/{{version}}/setups) or with `composer require`:

| Package | Version | Responsibility |
|---|---|---|
| `hostkurd/flocms-admin` | `0.39` | The [admin panel](/docs/{{version}}/admin-panel): dashboard, users, settings, media, pages and your own resources |
| `hostkurd/flocms-api` | `1.3.0` | Explicit API routes, middleware, JSON responses, authentication, resources, pagination, idempotency, OpenAPI and API testing |
| `hostkurd/flocms-reactive` | `0.5.1` | Server-rendered interactive components; the admin panel uses it |

`composer.json` requires the framework packages with caret ranges from a tested minimum (`"hostkurd/flocms-core": "^2.8.0"`), and `composer.lock` records the exact installed versions. Deploy with `composer install` to keep them; a site changes versions only when you run `composer update`. The ranges let a package you add, such as the admin panel, bring a newer release it needs. See [upgrading](/docs/{{version}}/upgrade).

The skeleton's version and the package versions are separate. FloCMS 2.0 uses core 2.8 or newer; a module's own `version` is separate again.

## What the skeleton sets up

`composer create-project hostkurd/flocms my-site` installs the framework packages and supplies:

- `public/index.php`, bootstrap files, page controllers, models, views and layouts
- the `flo` launcher, application migrations, sample commands and scheduled tasks
- admin login, login throttling, the `users` table and role configuration
- `php flo setup`, which adds the admin panel or the API (`commands/SetupCommand.php`)
- PHPUnit configuration and application testing helpers

The skeleton supplies application behavior, such as the login controller and its throttle. Installing core alone does not create those files. Follow the [request lifecycle](/docs/{{version}}/lifecycle) to see how they connect. Packages extend a project through the hooks described in [extending from a package](/docs/{{version}}/extending).

## Features you configure yourself

| Feature | Setup |
|---|---|
| Uploads | Copy and load `config/upload.php`. See [uploads](/docs/{{version}}/uploader). |
| CAPTCHA | Add an image endpoint and verification to your form. See [CAPTCHA](/docs/{{version}}/captcha). |
| Modules | Configure `ModuleSystem`, synchronize its registry, migrate and boot providers. See [modules](/docs/{{version}}/modules). |
| Custom services | Bind interfaces or factories in a container used by the API or modules. See [service container](/docs/{{version}}/container). |
| Database settings | Create your application's `settings` table if you use `Config::getSetting()`. See [configuration](/docs/{{version}}/configuration#settings-stored-in-the-database). |
| Production scheduler | Install the cron entry. See [scheduler](/docs/{{version}}/scheduler). |

Module extensions provide integration points for application code. For example, the page-block registry loads definitions and resolvers; it does not install a complete page-builder interface.

## Using packages separately

In a Composer project, require the package you need and load `vendor/autoload.php`. API and CLI use core services; Composer installs their declared dependencies. Read the package's `composer.json` for its requirements.

For an API without the skeleton, construct a router, a [container](/docs/{{version}}/container) and a kernel, and explicitly register middleware and route files. See [building an API](/docs/{{version}}/api#using-the-api-without-the-skeleton).

Uploads can receive configuration directly, without the skeleton's `ROOT` constant. See [storage](/docs/{{version}}/storage#using-storage-outside-the-skeleton). CAPTCHA needs a working PHP session and, for its default renderer, GD.

The CLI's project commands expect a FloCMS project layout and environment. Its console classes can also be used to build commands; see [writing commands](/docs/{{version}}/writing-commands).

## Runtime requirements

The skeleton needs PHP 8.1 or newer. Enable PDO and `pdo_mysql` for MySQL/MariaDB (or `pdo_sqlite` for SQLite), `fileinfo` for upload validation, and GD or Imagick for image processing. CAPTCHA's default renderer needs GD. S3 storage additionally needs `aws/aws-sdk-php`.

The website connection uses MySQL/MariaDB, or SQLite with core 2.9 or newer. The module database implementations use MySQL/MariaDB only. See [database](/docs/{{version}}/database).

## Older API code

`FloCMS\Core\Api` and `FloCMS\Core\ApiController` are deprecated compatibility classes. New code uses `FloCMS\Api\Kernel`, `FloCMS\Api\Controller` and the API package's router. The old `api_` page actions are also separate from this router. See [upgrading older API code](/docs/{{version}}/upgrade#moving-older-api-code).
