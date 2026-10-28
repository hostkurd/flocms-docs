# Upgrade guide

`composer.lock` records the exact versions of the FloCMS packages a site installs. Use `composer install` for repeatable deployments and update dependencies deliberately. Upgrade one site at a time, and test before you deploy.

Composer updates packages but never your project's own files (controllers, views, `config/`), so each section says which files to copy from the new skeleton. A site that copies nothing keeps working; it just doesn't get those changes.

## Core 2.9: SQLite for websites

```bash
composer update hostkurd/flocms-core
```

Nothing else changes. To run a site on SQLite, see [database](/docs/{{version}}/database#sqlite). Sites with `DB_TYPE=sqlite` in `.env` only for `php flo` now connect to that file on the website too: set `DB_TYPE=mysql` if the website should keep using MySQL.

## From 1.8 to 2.0

Existing sites keep working as they are: each site owns its `composer.json`, and flocms-api 1.3 keeps the old class names. To follow 2.0:

1. Update core and the API together:

   ```bash
   composer require "hostkurd/flocms-core:^2.8" "hostkurd/flocms-api:^1.3" -W
   ```

2. Change the rate-limit and client IP imports from the API to core in `support/LoginThrottle.php`, `controllers/UsersController.php` and `includes/maintenance.php`:

   ```php
   use FloCMS\Core\RateLimit\FileRateLimiter;      // was FloCMS\Api\RateLimit\FileRateLimiter
   use FloCMS\Core\RateLimit\RateLimiterInterface; // was FloCMS\Api\RateLimit\RateLimiterInterface
   use FloCMS\Core\Security\ClientIpResolver;      // was FloCMS\Api\Security\ClientIpResolver
   use FloCMS\Core\Security\IpMatcher;             // was FloCMS\Api\Security\IpMatcher
   ```

3. **A site without an API** can then remove it: `composer remove hostkurd/flocms-api`, and delete `public/api.php` and `api/`. Keep them if anything calls `/api/v1`.

4. Optional: copy `commands/SetupCommand.php` to add the admin panel or the API later with `php flo setup`, and the `home` setting: the `Config::set('home', 'site')` line of `config/config.php` and the lines that read it at the start of `PagesController::index()`, with its `home()` method. See [choose what you build](/docs/{{version}}/setups).

## From 1.7 to 1.8

1. Move to caret ranges and a current core. In `composer.json`, change the exact versions of the framework packages to ranges (`"hostkurd/flocms-core": "^2.6"`, `"hostkurd/flocms-cli": "^2.0.1"`, `"hostkurd/flocms-uploader": "^1.2.1"`, `"hostkurd/flocms-captcha": "^1.1.0"`, `"hostkurd/flocms-api": "^1.2.0"`), then:

   ```bash
   composer update -W
   ```

2. **Admin pages.** The skeleton's unfinished user pages were removed in 1.8.0. If your site uses `UsersController::admin_index`, `admin_add`, `admin_edit`, `admin_profile`, `admin_delete`, `admin_verify` or `admin_suspend`, keep your copies; Composer doesn't delete them. To use the admin panel instead, see [admin panel](/docs/{{version}}/admin-panel).

3. **`/admin` and the admin panel.** Copy `admin_index()` and `adminHome()` from the skeleton's `controllers/PagesController.php`, so `/admin` opens the admin panel's dashboard (the `admin.home` setting).

4. **Admin layout.** In `templates/default/layouts/admin.html`, load the stylesheets and scripts with `template_asset()` (1.7.8), and copy the `lang` and `dir` attributes of the `<html>` tag (1.8.3).

5. **Roles.** Copy the `Auth::hasAdminAccess()` lines of `admin_login()` in `controllers/UsersController.php` (1.8.7), so roles added in the admin panel can sign in at once. Add `'activity.view'` to role 2 in `config/config.php` if Admins should see the admin panel's activity log (1.8.6).

6. **The settings table.** If the site has no `settings` table, copy `database/migrations/2026_10_17_000000_create_settings_table.php` and run `php flo migrate` (1.8.5).

7. **Pages from the admin panel.** Copy the `home_page()` lines at the start of `PagesController::index()` and the `page_meta()` and `blocks_assets()` lines of `templates/*/layouts/default.html` (1.8.8). Without flocms-admin's Pages they do nothing.

If you're on 1.7.5 or older, also follow the steps below.

## From 1.7.5 to 1.7.8

```bash
composer require hostkurd/flocms-core:2.3.0
```

- **1.7.6** (core 2.2.3): the `max` rule passes on an empty value. If you relied on `max` to reject empty input, add `required`.
- **1.7.7** (core 2.3.0): controller resolvers. Nothing changes until a package registers one.
- **1.7.8**: copy the five changed lines of `templates/default/layouts/admin.html` (step 4 above).

## From 1.7.x to 1.7.5

1. Require the new core and captcha releases:

   ```bash
   composer require hostkurd/flocms-core:2.2.2 hostkurd/flocms-captcha:1.1.0
   ```

2. Compiled templates are rebuilt automatically, because the template compiler version changed. Run `php flo optimize` after deploying if you compile templates ahead of time.

3. If your code calls the captcha's `verify()` twice for the same answer, the second call now fails, because codes are single-use. Check once and keep the result, or set `'single_use' => false` in the captcha configuration. Calling `clear()` after `verify()` still works; it's just no longer needed. See [captcha](/docs/{{version}}/captcha).

4. If you renamed multi-word view folders to `views/blog_post/` style and link to `/blog_post`, that keeps working. `/blog-post` now works too, with views in `views/blog-post/`.

If you're on 1.7.3 or older, also follow the 1.7.4 steps below.

## From 1.7.x to 1.7.4

1. Require the core security release:

   ```bash
   composer require hostkurd/flocms-core:2.2.1
   ```

2. Optional: add the admin dashboard from 1.7.3, so `/admin/` no longer answers 404 after login. Copy `admin_index()` from the skeleton's `controllers/PagesController.php`, the view `views/pages/admin_index.html`, and the `roles` block from `config/config.php`.

3. Check your controllers for public helper methods. Public methods on your controller or your own base controller can be reached from a URL, so make helpers `protected` or `private`. Since core 2.2.1, methods inherited from `FloCMS\Core\Controller`, protected/private/static methods and magic method names answer 404.

## From 1.6 to 1.7

1. **Composer.** In `composer.json`, require `"hostkurd/flocms-cli": "2.0.1"` and `"hostkurd/flocms-api": "1.2.0"`, add `"App\\Commands\\": "commands/"` to `autoload.psr-4`, and run `composer update`.

2. **Launcher.** Replace the root `flo` file with this one, a one-time step:

   ```php
   #!/usr/bin/env php
   <?php
   require __DIR__ . '/vendor/autoload.php';
   exit(FloCMS\CLI\Kernel::handle(__DIR__, $argv));
   ```

   Then delete `support/KeyGenerator.php`.

3. **Maintenance mode.** Copy `includes/maintenance.php` and `templates/default/errors/503.html`, and the maintenance lines of `public/index.php` and `public/api.php`.

4. **Optional.**
   - Copy `commands/LoginUnlockCommand.php` and the `unlockIp()` method of `support/LoginThrottle.php`.
   - Copy `config/schedule.php` and add the [cron job](/docs/{{version}}/scheduler).

5. **Check.** Run `php flo doctor` and `php flo migrate`. The API tables are only recorded when they already exist.

> [!NOTE]
> Sites created before 1.6 follow the same steps. Until the `flo` file is replaced, `composer update` gives them flocms-cli 1.0.5, and its "command not found" message points to these steps.

## After upgrading

- Run `php flo env:check` to find new `.env` keys.
- Run `php flo view:clear` so templates are compiled again.
- Read the [release notes](/docs/{{version}}/releases) for what changed.

> [!STUDIO]
> The project's **Packages** tab shows the installed versions and the updates, with a link to each changelog. **Review update** shows the commands before Studio runs them, and the **Doctor** tab checks the result. See [packages and updates in Studio](/docs/studio/packages#update-a-package).

## Moving older API code

`FloCMS\Core\Api` and `FloCMS\Core\ApiController` are deprecated. The API package also supplies `FloCMS\Api\Legacy\LegacyApiController` as a compatibility bridge, but new endpoints should use `FloCMS\Api\Controller`.

1. Move handlers into `api/Controllers/` with the `App\Api\Controllers` namespace.
2. Declare each HTTP method/path in `api/routes.php`, with authentication and rate limits where needed.
3. Replace global input/query access with the handler's `Request` argument. Positional page parameters become named API route parameters.
4. Return `Response` objects or resources rather than echoing JSON and exiting. Use the [controller helpers](/docs/{{version}}/api-controllers#controller-helpers) for the response envelope.
5. Test clients against the `/api/v1` URLs, response bodies and status codes, then leave `LEGACY_API=false`.

Keeping a compatibility class does not configure explicit routes, middleware or authorization for you. See [building an API](/docs/{{version}}/api).
