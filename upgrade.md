# Upgrade guide

Core, API, CLI and uploader use exact version requirements; CAPTCHA uses `^1.0`. The lock file records the exact installed versions. Use `composer install` for repeatable deployments and update dependencies deliberately. Upgrade one site at a time, and test before you deploy.

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

## Moving older API code

`FloCMS\Core\Api` and `FloCMS\Core\ApiController` are deprecated. The API package also supplies `FloCMS\Api\Legacy\LegacyApiController` as a compatibility bridge, but new endpoints should use `FloCMS\Api\Controller`.

1. Move handlers into `api/Controllers/` with the `App\Api\Controllers` namespace.
2. Declare each HTTP method/path in `api/routes.php`, with authentication and rate limits where needed.
3. Replace global input/query access with the handler's `Request` argument. Positional page parameters become named API route parameters.
4. Return `Response` objects or resources rather than echoing JSON and exiting. Use the [controller helpers](/docs/{{version}}/api-controllers#controller-helpers) for the response envelope.
5. Test clients against the `/api/v1` URLs, response bodies and status codes, then leave `LEGACY_API=false`.

Keeping a compatibility class does not configure explicit routes, middleware or authorization for you. See [building an API](/docs/{{version}}/api).
