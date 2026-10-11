# Directory structure

The skeleton installs five Composer packages. See [framework and packages](/docs/{{version}}/packages) for their responsibilities and versions. Applications that enable modules also add the module layout described in [modules](/docs/{{version}}/modules#creating-a-module).

A new FloCMS project is small. Here is what each folder is for. To see how these pieces work together on a request, read the [request lifecycle](/docs/{{version}}/lifecycle).

## The project root

```text
my-site/
├── api/            JSON API: routes.php, Controllers/, Resources/
├── commands/       Your own php flo commands (App\Commands)
├── config/         config.php, schedule.php and bootstrap.php
├── controllers/    Page controllers (FloCMS\Controllers)
├── database/       migrations/ and seeders/
├── includes/       Startup helpers: maintenance mode, class aliases (compat.php)
├── lang/           Translations: en.php, ar.php, ku.php
├── models/         Models (FloCMS\Models)
├── public/         The web root: index.php, api.php, themes/, uploads/
├── storage/        Logs, cache and framework state (not in Git)
├── support/        Application classes such as LoginThrottle (FloCMS\Support)
├── templates/      Layouts, partials and error pages per template
├── tests/          PHPUnit tests
├── vendor/         Composer packages (not in Git)
├── views/          One folder of views per controller
├── .env            Your environment settings (not in Git)
├── composer.json   Package requirements (exact pins and compatible ranges)
└── flo             The command-line launcher: php flo
```

## public/

`public/` is the only folder the web server should serve. Point your domain's document root at it.

- `index.php` handles every page request.
- `api.php` handles `/api/v1/...` requests.
- `conf_global.php` defines constants every request uses, such as `ACTIVE_LANG` and `VIEWS_PATH`.
- `themes/<template>/` holds CSS, JavaScript, images and fonts. In templates, link to them with `template_asset('css/style.css')`.
- `uploads/` receives uploaded files.

## controllers/, models/ and views/

A page is a controller action plus a view:

- `controllers/BlogController.php` handles `/blog`.
- `views/blog/index.html` is the view for its `index()` action.
- `models/BlogModel.php` talks to the database.

`php flo make:route Blog` creates all three. See [controllers](/docs/{{version}}/controllers) and [views](/docs/{{version}}/views).

## templates/

Each template is a folder, and `APP_TEMPLATE` in `.env` picks the active one. A template can override any file of the `default` template; files it doesn't have come from `default`.

```text
templates/default/
├── layouts/    default.html (site) and admin.html (admin panel)
├── partials/   Pieces you include in layouts
├── errors/     403, 404, 500, 503 and database error pages
└── offline.html
```

## storage/

`storage/` holds files the application writes: `logs/`, `cache/` and `framework/` (maintenance mode and scheduler state). The web server must be able to write to it, and to `views/cache/`, where compiled templates go. `php flo storage:check --fix` checks and creates these folders.
