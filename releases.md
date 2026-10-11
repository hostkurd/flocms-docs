# Release notes

FloCMS follows semantic versioning. Core, API, CLI and uploader use exact requirements; CAPTCHA uses `^1.0`. `composer.lock` records the installed versions, and `composer install` preserves them. The full history is in the [changelog](https://github.com/hostkurd/FloCMS/blob/main/CHANGELOG.md).

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
