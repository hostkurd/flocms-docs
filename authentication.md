# Authentication

New FloCMS projects include a working admin login: a users table, a login page with brute-force protection, logout, and sessions that end as soon as an account is suspended. This page explains how it works and how to adjust it.

## Creating users

On a new site, run the migrations, which create the `users` table, and then create the first admin:

```bash
php flo migrate
php flo user:create --role=super-admin
```

`user:create` asks for the name, email and password. The password is hidden, asked twice, and must be at least 8 characters. Users created this way are active right away.

In a deployment script, read the password from STDIN instead of putting it on the command line:

```bash
echo "$ADMIN_PASSWORD" | php flo user:create -n --name="Admin" --email=admin@example.com --role=super-admin --password-stdin
```

More user commands:

```bash
php flo user:list                          # --status=active|pending|suspended
php flo user:password admin@example.com    # set a new password
php flo user:role editor@example.com admin
php flo user:suspend someone@example.com
php flo user:unsuspend someone@example.com
```

Roles are `user` (0), `editor` (1), `admin` (2) and `super-admin` (3), or the names in the `roles` setting. See [roles and permissions](/docs/{{version}}/permissions).

## Logging in

The login page is `/admin/users/login`. Every page under `/admin` sends visitors without admin access there, and sends signed-in admins from the login page to the dashboard at `/admin/`.

A login succeeds when the email exists, the password matches, and the account is active. The `status` column decides:

| Status | Meaning | At login |
|---|---|---|
| 1 | Active | Signed in |
| 2 | Pending | "Your account is pending verification" |
| 3 | Suspended | "Your account has been suspended" |
| 0 | Not activated | "Your account has not been activated" |

On success, FloCMS gives the visitor a new session ID and stores `user_id`, `role`, `email`, `fullname`, `username` and `isloggedin` in the session. `admin_access` is set when the role may open the admin panel (roles 1 to 3 by default). Then the visitor goes to `/admin/`.

Passwords are stored with PHP's `password_hash()`. When PHP's default algorithm changes, a user's hash is upgraded automatically at their next login.

`/admin/users/logout` ends the session.

## Checking who is signed in

```php
use FloCMS\Core\Auth;
use FloCMS\Core\Session;

if (Session::get('isloggedin')) {
    $name = Session::get('fullname');
}

$role = Auth::role();                // 0-3, or null when nobody is signed in
if (Auth::can('content.edit')) {
    // ...
}
```

Check permissions rather than role numbers, so your code keeps working when roles change. See [roles and permissions](/docs/{{version}}/permissions).

## Sessions end at once

A suspended or deleted user should lose access immediately, not when their session happens to expire. `config/config.php` sets a user loader for that:

```php
Config::set('auth.user_loader', static fn (int $id) => (new \FloCMS\Models\UsersModel())->getByID($id));
```

On every admin request, FloCMS calls the loader with the session's `user_id` and:

- ends the session when the user no longer exists or isn't active
- updates the session's role, so a demoted admin loses permissions on their next click
- updates `admin_access` from `admin_access_roles`

When it ends a session, the next page shows the `auth.session_ended` message from the language file.

| Setting | Default | Meaning |
|---|---|---|
| `auth.user_loader` | set by the skeleton | Returns the user (array or object with `role` and `status`), or `null` when gone |
| `auth.active_status` | `1` | The `status` value of active users |
| `auth.refresh_interval` | `0` | Seconds between reloads. `0` reloads on every admin request. |

Without a loader, nothing is reloaded and sessions keep their role until logout. API tokens of a user are checked with the same loader, so a suspended user's tokens stop working too.

## Login throttling

The login page counts sign-in attempts, to stop password guessing:

```php
Config::set('login_throttle', array(
    'max_per_ip'    => 20,    // attempts per client IP
    'max_per_email' => 5,     // attempts per email address
    'window'        => 900,   // in seconds: 15 minutes
));
```

Every attempt counts, successful or not. A successful login resets the counter of that email, but not of the IP, so one valid account can't be used to reset an attacker's budget. Over the limit, the page answers with status 429 and the `auth.throttled` message, which says how many minutes to wait.

The counters are files in `storage/cache/login-throttle`, so the web server must be able to write there. To lift a block right away:

```bash
php flo login:unlock admin@example.com
php flo login:unlock 203.0.113.7
```

## Behind a proxy or CDN

Login throttling counts per client IP. Behind a reverse proxy, a load balancer or a CDN, every request seems to come from the proxy, so list its addresses in `.env`:

```ini
TRUSTED_PROXIES=10.0.0.0/8,192.0.2.10
```

FloCMS then takes the client IP from the `X-Forwarded-For` header, but only on requests that come from a listed proxy. Without the setting, the header is ignored, because any client could send it. The API's rate limits and maintenance mode use the same setting.

## The users table

The migration `database/migrations/2026_10_10_000000_create_users_table.php` creates the `users` table:

| Column | |
|---|---|
| `id` | Primary key |
| `full_name`, `login`, `email` | Name, login name and email (unique) |
| `password` | The password hash |
| `role` | 0 to 3, see [roles](/docs/{{version}}/permissions#roles) |
| `status` | 1 active, 2 pending, 3 suspended |
| `is_verified`, `verify_type`, `token` | Email verification |
| `image`, `address`, `phone`, `gender` | Profile fields |

`models/UsersModel.php` reads and writes it, and `controllers/UsersController.php` holds the login, logout and user management actions. Both are your code, so change them as your site needs.
