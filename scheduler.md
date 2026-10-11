# Scheduler

The scheduler runs recurring tasks, like clean-ups, reports and imports, from a single cron job. You define the tasks in PHP instead of adding a cron entry for each one.

## The cron job

Add **one** cron job. In cPanel, use **Cron Jobs → Once Per Minute**:

```bash
* * * * * php /home/USER/my-site/flo schedule:run >> /dev/null 2>&1
```

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

`command()` runs a `php flo` command, and `call()` runs a PHP function.

## Frequencies

`everyMinute`, `everyFiveMinutes`, `everyTenMinutes`, `everyFifteenMinutes`, `everyThirtyMinutes`, `hourly`, `hourlyAt(m)`, `daily`, `dailyAt('HH:MM')`, `twiceDaily`, `weekly`, `weeklyOn(day, time)`, `monthly`, `monthlyOn(day, time)`, `yearly`, `weekdays` and `cron('...')`.

## How tasks run

- **No overlaps.** A task that's still running isn't started again. Use `allowOverlapping()` to change that.
- **In-process.** Tasks run inside the `schedule:run` process, so shared hosts without `proc_open` work.
- **Results** go to `storage/logs/schedule.log`.

```bash
php flo schedule:list                     # next run and last result of each task
php flo schedule:run --task="heartbeat"   # run one task now
```

## Built-in tasks

`$schedule->defaults()`, and a missing `config/schedule.php`, run:

- `api:gc` daily, which cleans up API rate-limit and idempotency files
- log rotation: logs over 10 MB, keeping 5 copies
- deletion of chunked uploads older than 24 hours in `storage/uploads/.chunks`
