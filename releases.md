# Release notes

FloCMS follows semantic versioning. All five FloCMS packages, core, API, CLI, uploader and CAPTCHA, use exact version requirements, and `composer.lock` records the installed versions. `composer install` preserves them. The full history is in the [changelog](https://github.com/hostkurd/FloCMS/blob/main/CHANGELOG.md).

> [!NOTE]
> These are the docs for FloCMS 1.7. Releases 1.8 and 2.0 are described in the [2.0 release notes](https://flocms.com/docs/2.0/releases).

## 1.7.8

Released October 12, 2026.

- The admin layout loads its stylesheets and scripts with `template_asset()`, so the login page and dashboard are styled again. Existing sites: copy the five changed lines of `templates/default/layouts/admin.html`.

## 1.7.7

Released October 12, 2026. Requires core 2.3.0.

- Packages can supply page controllers with `App::resolveControllersUsing()`. Your own controllers in `controllers/` always come first, and sites without resolvers behave exactly as before.

## 1.7.6

Released October 12, 2026. Requires core 2.2.3.

- The `max` validation rule passes on a missing or empty value, so an empty required field shows only its "required" message. `min` is unchanged.

Existing sites get both core releases with:

```bash
composer require hostkurd/flocms-core:2.3.0
```

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
