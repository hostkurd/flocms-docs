# Configuration

FloCMS reads environment-specific values from `.env` and keeps application settings in `config/config.php`.

## The .env file

`.env` holds everything that differs between your computer and your server: URLs, database passwords, debug mode. It is never committed to Git. `composer create-project` creates it from `.env.example`.

| Key | Meaning |
|---|---|
| `APP_ENV` | `local` while developing, `production` on the live site |
| `APP_DEBUG` | `true` shows detailed error pages. Always `false` in production. |
| `APP_KEY` | Random application key, set by `php flo key:generate` |
| `APP_URL` | The site's address, e.g. `https://example.com` or `http://localhost/my-site` |
| `APP_TEMPLATE` | The active template folder in `templates/` and `public/themes/` |
| `SITE_NAME`, `SITE_NAME_ADMIN` | Names shown in the site and the admin panel |
| `LANGUAGES`, `DEFAULT_LANG` | Enabled languages, e.g. `en,ar,ku`, and the default one |
| `DB_TYPE`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD` | Database connection. Leave `DB_NAME` empty until the database exists. |
| `TRUSTED_PROXIES` | Reverse proxies (IPs or CIDR ranges) whose `X-Forwarded-For` header is trusted |
| `API_CORS_ORIGINS` | Origins allowed to call the API, comma separated. `https://*.example.com` patterns work. |
| `API_RATE_LIMIT` | API requests per minute per client |
| `LEGACY_API` | The old `/api/<controller>/<action>` route. Leave `false` for new sites. |

`php flo env:check` lists keys that exist in `.env.example` but are missing from your `.env`, which is useful after an upgrade.

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

Scheduled tasks are configured in `config/schedule.php`. See the [scheduler](/docs/{{version}}/scheduler).
