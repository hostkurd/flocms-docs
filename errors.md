# Error pages and logging

When something goes wrong, FloCMS logs the error and shows a page that fits it: a 404 for a missing page, a 403 for a missing permission, a database page when the database is down. In debug mode you get a detailed page for developers instead.

## Showing an error page

Throw an `HttpException` with the status code:

```php
use FloCMS\Core\HttpException;

$post = $this->model()->find($id);

if ($post === null) {
    throw new HttpException(404);
}
```

FloCMS stops the request, sends the status code and renders the matching page. `Auth::authorize()` throws a 403 the same way. See [roles and permissions](/docs/{{version}}/permissions).

## The error templates

Error pages are files in `templates/<template>/errors/`. Like other template files, a template only needs the ones it changes; the rest come from `templates/default/errors/`.

| File | Used for |
|---|---|
| `404.html` | `HttpException(404)`: unknown pages, controllers and actions |
| `403.html` | `HttpException(403)`: missing permissions |
| `500.html` | Every other error, and every other status code, such as 419 for a missing CSRF token |
| `nodbserver.html` | The database server can't be reached (status 503) |
| `dberror.html` | Database setup problems: wrong password, unknown database, not configured (status 500) |
| `queryerror.html` | A query failed, for example because a table doesn't exist |
| `debug.html` | The developer page in debug mode |
| `503.html` | [Maintenance mode](/docs/{{version}}/maintenance) |

Error templates are views, so the template syntax works. They receive:

| Variable | Contains |
|---|---|
| `$status` | The HTTP status code |
| `$message` | A short, safe message, such as "Database not found." |
| `$errorCode` | The database driver's error code, when there is one |
| `$detail` | The database driver's own message. Only set in debug mode, because it names hosts and users. |

If a template file is missing or fails itself, FloCMS falls back to `500.html`, and finally to a plain "Internal Server Error".

## Debug mode

With `APP_DEBUG=true` in `.env`, errors show the debug page instead: the exception, the code around the line that failed, the stack trace, and the request with its session, GET, POST and server values. Passwords, cookies and `Authorization` headers are filtered out of the server values.

- In debug mode, a 404 or 403 also shows the debug page, which helps you see why a URL didn't match.
- Database setup errors keep their own page in debug mode, with `$detail` filled in.
- PHP notices and warnings are turned into exceptions, so an undefined variable or array key stops the page with an error instead of passing silently. This applies in both modes.

> [!WARNING]
> Always set `APP_DEBUG=false` on a live site. The debug page shows your code, file paths and session data. `php flo doctor` warns when debug mode is on in production.

## Database errors

Models connect to the database on first use, so a page that doesn't query the database works without one. When a query does need it and it isn't there:

- an unreachable server shows `nodbserver.html` with status 503
- a wrong password, an unknown database or empty `DB_*` settings show `dberror.html`

To check the database without an error page, for example on a setup screen, use `App::dbStatus()`, which never throws. See [database](/docs/{{version}}/database#checking-the-connection).

## Logging

Every error that reaches the error handler is written to `storage/logs/app.log`, with the exception class, file and line:

```text
[2026-10-11 14:03:12] error: View file does not exist: /home/user/site/views/blog/show.html {"type":"RuntimeException","file":"...","line":31}
```

Write your own messages with `App::logger()`, a PSR-3 logger:

```php
use FloCMS\Core\App;

App::logger()->info('Order placed', ['order' => $orderId]);
App::logger()->warning('Payment provider slow', ['ms' => $duration]);
```

All PSR-3 levels work: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert` and `emergency`. The context array is written as JSON. If the log file can't be written, the line goes to PHP's error log instead.

Other logs in `storage/logs/`:

| File | Written by |
|---|---|
| `app.log` | The error handler and `App::logger()` |
| `api.log` | The API: errors and router warnings |
| `schedule.log` | The [scheduler](/docs/{{version}}/scheduler) |

### Log commands

```bash
php flo log:tail                    # the last 50 lines of the newest log
php flo log:tail api --lines=100    # the last 100 lines of api.log
php flo log:tail app --follow       # keep printing new lines (Ctrl+C to stop)
php flo log:clear app               # empty app.log; without a name, every log (asks first)
php flo log:rotate                  # app.log -> app.log.1 ... for logs over 10 MB
```

`log:rotate` takes `--max-size=` in MB (default 10) and `--keep=` (default 5 old copies). The scheduler runs it daily, so with the cron job in place you don't need to rotate by hand.
