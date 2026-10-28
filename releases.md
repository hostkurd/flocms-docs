# Release notes

FloCMS follows semantic versioning. The project skeleton has its own version (2.0.0) and requires the framework packages, each with its own version, with caret ranges such as `^2.8.0`; `composer.lock` records the exact versions a project installs. The full history is in the [changelog](https://github.com/hostkurd/FloCMS/blob/main/CHANGELOG.md). Moving an existing site to a new release is described in the [upgrade guide](/docs/{{version}}/upgrade).

## Core 2.9.0

Released October 24, 2026. A core release; the skeleton is unchanged.

- **SQLite for websites.** With `DB_TYPE=sqlite` in `.env` (or `db.type` in the configuration), `App::db()` opens the SQLite file named by `DB_NAME`, so pages, models and the admin panel can run on SQLite, not only `php flo`. See [database](/docs/{{version}}/database#sqlite).

Existing sites get it with `composer update hostkurd/flocms-core`.

## 2.0.0

Released October 23, 2026. Requires core 2.8.0.

- **Choose what you build.** `composer create-project` ends by asking what the project is: a website with the admin panel (the default), a website only, an admin panel only, an API with the admin panel, or an API only. `php flo setup` adds just the packages that choice needs. See [choose what you build](/docs/{{version}}/setups).
- **The API is optional.** flocms-api is no longer installed by default, and `public/api.php` and `api/` left the skeleton. `php flo api:install` (flocms-api 1.3) writes them when you add the API. See [building an API](/docs/{{version}}/api).
- **A home setting.** `Config::set('home', 'site')` decides what `/` shows: the website, the admin panel (`admin`) or a short JSON welcome (`api`).
- **Rate limits and client IPs in core.** Login throttling and maintenance mode use core 2.8's `FloCMS\Core\RateLimit` and `FloCMS\Core\Security` classes, so a website without the API has them. flocms-api 1.3 keeps its old class names as aliases.

Existing sites keep working as they are. See [from 1.8 to 2.0](/docs/{{version}}/upgrade#from-18-to-20).

## 1.8.8

Released October 21, 2026. Requires core 2.6.0.

- Ready for the admin panel's **Pages**: the home page can be a page chosen in the panel, and both layouts print `page_meta()` and `blocks_assets()`. Without flocms-admin nothing changes. Core 2.6 adds `App::fallbackUsing()`; see [extending from a package](/docs/{{version}}/extending#public-addresses-from-a-package).

## 1.8.7

Released October 18, 2026. Requires core 2.4.0.

- **Roles at runtime.** Core 2.4's `Auth::loadRolesUsing()` lets a package supply roles and permissions, and role levels decide who may manage whom. The admin login asks `Auth::hasAdminAccess()`, so a role added in the admin panel can sign in at once.

## 1.8.6

Released October 18, 2026.

- The Admin role (2) gets `activity.view`, for the admin panel's activity log.

## 1.8.5

Released October 17, 2026.

- A migration for the `settings` table that `Config::getSetting()`, maintenance mode and the admin panel's settings pages share. See [configuration](/docs/{{version}}/configuration#settings-stored-in-the-database).

## 1.8.4

Released October 16, 2026.

- **Caret ranges.** The framework packages are required from a tested minimum (`^2.3.2`, `^1.2.1`, …) instead of exact versions, so adding a package such as flocms-admin can bring the newer release it needs. `composer.lock` still fixes the versions a project installs.
- flocms-uploader 1.2.1: the Imagick driver no longer pads resized images to a square.

## 1.8.3

Released October 14, 2026.

- The admin layout's `lang` and `dir` follow the active language. An optional `auth.login_view` setting lets a package present the login page; the login itself is unchanged.

## 1.8.2

Released October 13, 2026. Requires core 2.3.2.

- Controllers may return a `FloCMS\Core\Http\Response` (JSON, a fragment or an empty response) instead of a view. See [extending from a package](/docs/{{version}}/extending#returning-a-response).
- A malformed CSRF token answers 419, and a signed-in user without admin access can log out and sign in as someone else (core 2.3.1).

## 1.8.0

Released October 12, 2026.

- **The admin screens move to a package.** The skeleton keeps signing in, the users table, roles, `php flo user:*` and a small `/admin` page. The unfinished user pages of `UsersController` were removed; manage users with [flocms-admin](/docs/{{version}}/admin-panel), the `user:*` commands or your [own admin](/docs/{{version}}/example-admin). `/admin` follows the `admin.home` setting, which the admin panel sets to its dashboard.

## 1.7.8

Released October 12, 2026.

- The admin layout loads its stylesheets and scripts with `template_asset()`, so the login page and dashboard are styled again.

## 1.7.7

Released October 12, 2026. Requires core 2.3.0.

- Packages can supply controllers with `App::resolveControllersUsing()`. Your own controllers always come first. See [extending from a package](/docs/{{version}}/extending#controllers-from-a-package).

## 1.7.6

Released October 12, 2026. Requires core 2.2.3.

- The `max` validation rule passes on a missing or empty value, so an empty required field shows only its "required" message. `min` is unchanged.

## 1.7.5

Released October 11, 2026.

- **Multi-word page controllers.** Requires `hostkurd/flocms-core` 2.2.2: `/blog-post` runs `BlogPostController` and renders `views/blog-post/`, which is what `php flo make:route BlogPost` generates. In 1.7.4 these URLs answered 404.
- **Template directives accept parentheses.** `@if(count($posts) > 0)`, `@foreach(array_slice($posts, 0, 3) as $post)` and `@lang('key', ['name' => strtoupper($name)])` compile correctly, and `@forelse ... @empty ... @endforelse` works. Existing templates compile to the same PHP as before.
- **Security: single-use captcha codes.** Requires `hostkurd/flocms-captcha` 1.1.0, now pinned exactly like the other packages: `verify()` removes the code on every check, so a code can't be guessed by retrying or reused by submitting the form again.

Existing sites get both with:

```bash
composer require hostkurd/flocms-core:2.2.2 hostkurd/flocms-captcha:1.1.0
```

## 1.7.4

Released October 11, 2026.

- **Security.** Requires `hostkurd/flocms-core` 2.2.1: only public, non-static actions on your controllers or your own base controllers can be reached from a URL. Methods inherited from `FloCMS\Core\Controller` are blocked. Before, a URL could reach that base controller's methods.
- `APP_VERSION` and the version checks in the test suite now match the release.

Existing sites get the security fix with:

```bash
composer require hostkurd/flocms-core:2.2.1
```

## 1.7.3

Released October 11, 2026.

- **Admin dashboard.** Logging in redirects to `/admin/`, which answered 404. It is now a small dashboard with the signed-in user, their role, a link to user management for roles allowed to manage users, and log out.
- Role display names live in `config/config.php` (`Config::set('roles', ...)`), shared by the dashboard and user management.

## 1.7.2

Released October 10, 2026.

- Requires `hostkurd/flocms-cli` 2.0.1, which fixes the hidden password prompt on Windows in `user:create` and `user:password`.

## 1.7.1

Released October 10, 2026.

- **Welcome page.** A new minimal design with the animated FloCMS logo. It still renders when a wrong `APP_URL` breaks theme asset links.
- **One-click APP_URL fix.** On an `APP_URL` mismatch, a local install offers a button that writes the detected URL to `.env`.

## 1.7.0

Released October 10, 2026.

- **Commands come from Composer.** The root `flo` file is a three-line launcher for flocms-cli, so `composer update` delivers new commands and fixes. `php flo help <command>` explains any command.
- **Your own commands.** Classes in `commands/` are commands, starting with `php flo login:unlock`.
- **Migrations and seeders** in `database/`. The users table and the API tables are migrations.
- **Scheduler.** One cron job runs the tasks in `config/schedule.php`.
- **Maintenance mode** with `php flo down` and `php flo up`.
- `composer create-project` works on PHP 8.1 again.

To upgrade an existing site, see the [upgrade guide](/docs/{{version}}/upgrade).
