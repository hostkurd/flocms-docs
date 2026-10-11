# Scheduler

The scheduler runs recurring tasks, like clean-ups, reports and imports, from a single cron job. You define the tasks in PHP instead of adding a cron entry for each one.

## The cron job

Add **one** cron job that runs every minute. In cPanel, use **Cron Jobs → Once Per Minute**:

```bash
* * * * * php /home/USER/my-site/flo schedule:run >> /dev/null 2>&1
```

If your host's default `php` is a different version, use the full path it gives you, such as `/usr/local/bin/ea-php83`. Every minute, `schedule:run` starts the tasks that are due and exits.

## Defining tasks

Tasks live in `config/schedule.php`:

```php
<?php
use FloCMS\CLI\Scheduling\Schedule;

return static function (Schedule $schedule): void {
    $schedule->defaults(); // api:gc, log rotation, stale upload cleanup

    $schedule->command('reports:send', ['--weekly'])->weeklyOn(1, '08:00');
    $schedule->command('backup:run')->dailyAt('02:30')->environments('production');
    $schedule->call(static fn () => touch(__DIR__ . '/../storage/heartbeat'), 'heartbeat')->everyFiveMinutes();
    $schedule->command('feeds:import')->cron('*/20 6-22 * * mon-fri')->timezone('Asia/Baghdad');
};
```

- `command('name', [arguments])` runs a `php flo` command, your own included. See [writing commands](/docs/{{version}}/writing-commands).
- `call($function, 'name')` runs a PHP function. The name identifies it in the log and in `--task`.

## Frequencies

| Method | Runs |
|---|---|
| `everyMinute()`, `everyFiveMinutes()`, `everyTenMinutes()`, `everyFifteenMinutes()`, `everyThirtyMinutes()` | At that interval |
| `hourly()`, `hourlyAt(25)` | Every hour, at minute 0 or the given minute |
| `daily()`, `dailyAt('02:30')` | Every day, at midnight or the given time |
| `twiceDaily(1, 13)` | At 01:00 and 13:00, or the hours you give |
| `weekly()`, `weeklyOn(1, '08:00')` | Sunday at midnight, or the given weekday (0 = Sunday) and time |
| `monthly()`, `monthlyOn(15, '09:00')` | The 1st at midnight, or the given day and time |
| `yearly()` | January 1st at midnight |
| `weekdays()` | Limits the frequency before it to Monday to Friday: `->dailyAt('07:00')->weekdays()` |
| `cron('*/20 6-22 * * mon-fri')` | Any cron expression |

## More options

| Method | Does |
|---|---|
| `timezone('Asia/Baghdad')` | Reads the times in that time zone instead of the server's |
| `environments('production')` | Only runs when `APP_ENV` is one of these |
| `allowOverlapping()` | Starts the task even when its previous run hasn't finished |
| `description('...')` | A description for `schedule:list` |

## How tasks run

- **No overlaps.** A task that's still running isn't started again, unless you call `allowOverlapping()`.
- **In-process.** Tasks run inside the `schedule:run` process, so shared hosts without `proc_open` work.
- **Results** go to `storage/logs/schedule.log`.

```bash
php flo schedule:list                     # each task with its next run and last result
php flo schedule:run --task="heartbeat"   # run one task now, whether it's due or not
```

## Built-in tasks

`$schedule->defaults()` adds three tasks. Without a `config/schedule.php`, these are the only tasks:

| Task | When |
|---|---|
| `api:gc`: delete stale API rate-limit and idempotency files (when flocms-api is installed) | Daily at 03:10 |
| Rotate logs over 10 MB in `storage/logs`, keeping 5 copies | Daily at 03:20 |
| Delete chunked uploads older than 24 hours in `storage/uploads/.chunks` | Hourly at minute 25 |

To change one of them, leave out `defaults()` and add the tasks yourself:

```php
$schedule->command('api:gc', ['--database'])->dailyAt('04:00');
$schedule->rotateLogs(20 * 1024 * 1024, 10)->dailyAt('03:20');      // 20 MB, keep 10
$schedule->cleanDirectory('storage/uploads/.chunks', 48)->hourly();  // older than 48 hours
```
