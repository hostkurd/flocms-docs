# Upgrade guide

FloCMS packages are pinned to exact versions, so nothing changes until you decide. Upgrade one site at a time, and test before you deploy.

## From 1.7.x to 1.7.4

1. Require the core security release:

   ```bash
   composer require hostkurd/flocms-core:2.2.1
   ```

2. Optional: add the admin dashboard from 1.7.3, so `/admin/` no longer answers 404 after login. Copy `admin_index()` from the skeleton's `controllers/PagesController.php`, the view `views/pages/admin_index.html`, and the `roles` block from `config/config.php`.

3. Check your controllers for public helper methods. Every public method declared on a controller can be reached from a URL, so make helpers `protected` or `private`. Since core 2.2.1, inherited, protected, private and static methods answer 404.

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
