# Framework and packages

FloCMS has an application skeleton and five Composer packages. The skeleton gives you a ready-to-run project; the packages provide the runtime and tools that project uses.

## Versions in this guide

These pages describe the FloCMS 1.7.5 skeleton and these package versions:

| Package | Version | Responsibility |
|---|---|---|
| `hostkurd/flocms-core` | `2.2.2` | Page routing, controllers, models, database access, templates, configuration, authentication, HTTP classes, the service container and modules |
| `hostkurd/flocms-api` | `1.2.0` | Explicit API routes, middleware, JSON responses, authentication, resources, pagination, idempotency, OpenAPI and API testing |
| `hostkurd/flocms-cli` | `2.0.1` | `php flo`, code generators, migrations, seeders, scheduling, maintenance and project inspection |
| `hostkurd/flocms-uploader` | `1.2.0` | File validation, storage disks, image versions, videos and resumable uploads |
| `hostkurd/flocms-captcha` | `1.1.0` | Session-backed, single-use image challenges and custom renderers |

The skeleton requires exact versions of all five packages; CAPTCHA was pinned exactly in 1.7.5, before that it used `^1.0`. `composer.lock` records the exact installed versions; deploy with `composer install` to keep them. See [upgrading](/docs/{{version}}/upgrade).

The framework version and package versions are separate. FloCMS 1.7.5 uses core 2.2.2; a module's own `version` is separate again.

## What the skeleton sets up

`composer create-project hostkurd/flocms my-site` installs all five packages and supplies:

- `public/index.php`, bootstrap files, page controllers, models, views and layouts
- `public/api.php` and `api/routes.php`, including the global middleware and authentication aliases
- the `flo` launcher, application migrations, sample commands and scheduled tasks
- admin login, user management, login throttling and role configuration
- PHPUnit configuration and application testing helpers

The skeleton supplies application behavior, such as the login controller and its throttle. Installing core alone does not create those files. Follow the [request lifecycle](/docs/{{version}}/lifecycle) to see how they connect.

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

The skeleton needs PHP 8.1 or newer. Enable PDO and `pdo_mysql` for MySQL/MariaDB, `fileinfo` for upload validation, and GD or Imagick for image processing. CAPTCHA's default renderer needs GD. S3 storage additionally needs `aws/aws-sdk-php`.

The bundled web database connection and module database implementations use MySQL/MariaDB. Application CLI migrations can also use SQLite; that does not make the web connection or module migration ledger SQLite-compatible. See [database](/docs/{{version}}/database).

## Older API code

`FloCMS\Core\Api` and `FloCMS\Core\ApiController` are deprecated compatibility classes. New code uses `FloCMS\Api\Kernel`, `FloCMS\Api\Controller` and the API package's router. The old `api_` page actions are also separate from this router. See [upgrading older API code](/docs/{{version}}/upgrade#moving-older-api-code).
