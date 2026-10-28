# Configuration

FloCMS reads environment-specific values from `.env` and keeps application settings in `config/config.php`.

## The .env file

`.env` holds everything that differs between your computer and your server: URLs, database passwords, debug mode. It is never committed to Git. `composer create-project` creates it from `.env.example`.

| Key | Meaning |
|---|---|
| `APP_NAME` | The application's name, used for example as the title of the OpenAPI document |
| `APP_VERSION` | The skeleton version, shown on the welcome page |
| `APP_ENV` | `local` while developing, `production` on the live site |
| `APP_DEBUG` | `true` shows detailed error pages. Always `false` in production. |
| `APP_KEY` | Random application key, set by `php flo key:generate` |
| `APP_URL` | The site's address, e.g. `https://example.com` or `http://localhost/my-site` |
| `API_URL` | Base address of an external API, available as the `API_URI` constant |
| `APP_TEMPLATE` | The active template folder in `templates/` and `public/themes/` |
| `SITE_NAME`, `SITE_NAME_ADMIN` | Names shown in the site and the admin panel |
| `PRIMARY_EMAIL` | The sender address of emails the site sends |
| `LANGUAGES`, `DEFAULT_LANG` | Enabled languages, e.g. `en,ar,ku`, and the default one. See [localization](/docs/{{version}}/localization). |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET` | MySQL/MariaDB website connection. Leave `DB_NAME` empty until the database exists. See [database](/docs/{{version}}/database). |
| `DB_TYPE` | `mysql` (default), `mariadb`, or `sqlite`. The website follows it since core 2.9; before, only `php flo` did. See [supported databases](/docs/{{version}}/database#supported-databases). |
| `DB_DSN` | CLI only: an explicit PDO DSN, overriding `DB_TYPE`. Other drivers are not fully supported by the query builder and migration tools. See [supported databases](/docs/{{version}}/database#supported-databases). |
| `TRUSTED_PROXIES` | Reverse proxies (IPs or CIDR ranges) whose `X-Forwarded-For` header is trusted |
| `API_CORS_ORIGINS` | Origins allowed to call the API, comma separated. `https://*.example.com` patterns work. |
| `API_RATE_LIMIT` | API requests per minute per client |
| `API_MAINTENANCE_ALLOWED_IPS` | IPs or CIDR ranges that can use the API during [maintenance mode](/docs/{{version}}/maintenance) |
| `LEGACY_API` | The old `/api/<controller>/<action>` route. Leave `false` for new sites. |

`php flo env:check` lists keys that exist in `.env.example` but are missing from your `.env`, which is useful after an upgrade.

Lines starting with `#` are comments, and values may be wrapped in single or double quotes. A real environment variable with the same name wins over the `.env` value, so a host's own settings are never overwritten.

Read a value anywhere with `Env::get()`:

```php
use FloCMS\Core\Env;

$debug = Env::get('APP_DEBUG', false);
```

`true`, `false`, `null` and numbers are converted to PHP values.

> [!WARNING]
> Set `APP_DEBUG=false` on a live site. Debug pages show file paths, code and database details. `php flo doctor` warns when debug mode is on in production.

## config/config.php

Application settings live in `config/config.php`. Read them with `Config::get()`:

```php
use FloCMS\Core\Config;

$perPage = Config::get('LIMIT_PER_PAGE');
$roles = Config::get('roles', []);
```

The main settings:

| Setting | Meaning |
|---|---|
| `routes` | Route prefixes and their method prefix, e.g. `'admin' => 'admin_'`. See [routing](/docs/{{version}}/routing). |
| `default_controller`, `default_action` | What `/` runs: `pages` and `index` |
| `languages`, `default_language` | Filled from `LANGUAGES` and `DEFAULT_LANG` |
| `admin_access_roles` | Roles that may open the admin panel |
| `roles` | Display names of the roles |
| `permissions` | What each role may do. See [roles and permissions](/docs/{{version}}/permissions). |
| `login_throttle` | Admin login attempts allowed per IP and per email in 15 minutes |
| `trusted_proxies` | Filled from `TRUSTED_PROXIES` |

Keys are plain strings: `db.host` is one key, not a nested array. Set your own settings the same way, in `config/config.php` or anywhere before you read them:

```php
Config::set('blog.per_page', 10);
```

| More settings | |
|---|---|
| `db.host`, `db.port`, `db.name`, `db.user`, `db.pass`, `db.charset` | Filled from the `DB_*` keys. See [database](/docs/{{version}}/database). |
| `auth.user_loader` | Reloads the signed-in user on admin requests. See [authentication](/docs/{{version}}/authentication). |
| `view.cache_path` | Where compiled templates go, `views/cache/` by default |
| `view.cache` | Compiled template caching; `true` by default, set the boolean `false` to disable it. See [views](/docs/{{version}}/views#compiled-templates). |

Scheduled tasks are configured in `config/schedule.php`. See the [scheduler](/docs/{{version}}/scheduler).

Uploads use a separate array loaded from `config/upload.php`. Modules need explicit runtime configuration in `config/modules.php`; there is no automatic module discovery/boot configuration in the skeleton. See [uploads](/docs/{{version}}/uploader#setting-it-up) and [modules](/docs/{{version}}/modules#configuring-the-runtime).

## Settings stored in the database

`Config::getSetting('name')` reads a value from a `settings` table (columns `param`, `lang` and `value`) for the current language. It returns the default you pass, or `false`, when there is no database or no such table. The skeleton's migrations create this table (since 1.8.5; older sites can copy `database/migrations/2026_10_17_000000_create_settings_table.php`). `php flo down` and `up` write the `offline_mode` setting there, and the admin panel's settings pages store their values in the same table.
