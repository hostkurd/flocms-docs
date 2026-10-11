# Roles and permissions

Admin access is controlled by roles. Each user has one role, and each role has a set of permissions that controllers check before an action runs.

## Roles

FloCMS ships with four roles, defined in `config/config.php`:

| Role | Name | Permissions |
|---|---|---|
| 0 | User | none |
| 1 | Editor | `content.*` |
| 2 | Admin | `content.*`, `users.manage`, `users.assign_role`, `settings.*` |
| 3 | Super Admin | `*` (everything) |

```php
Config::set('roles', array(
    0 => 'User',
    1 => 'Editor',
    2 => 'Admin',
    3 => 'Super Admin',
));

Config::set('permissions', array(
    0 => array(),
    1 => array('content.*'),
    2 => array('content.*', 'users.manage', 'users.assign_role', 'settings.*'),
    3 => array('*'),
));
```

A permission is a name (`users.manage`), a prefix with a wildcard (`content.*`) or `*`. Roles that aren't listed get no permissions. `admin_access_roles` lists the roles that may open the admin panel at all.

## Protecting actions

Controllers declare the permission each action needs. Actions that aren't listed fall back to `'*'`, and `null` means no check:

```php
protected array $actionPermissions = [
    '*'           => 'users.manage',
    'admin_login' => null,
];
```

A user without the permission gets a 403 page.

## Checking inside an action

```php
use FloCMS\Core\Auth;

if (Auth::can('settings.edit')) {
    // show the settings form
}

Auth::authorize('settings.edit'); // throws a 403 when not allowed
```

## Managing users

User management only lets you assign roles up to your own, and only edit, delete or suspend users with a lower role. Super Admins can manage everyone.

From the command line:

```bash
php flo user:create --role=editor
php flo user:role editor@example.com admin
php flo user:suspend someone@example.com
php flo user:list
php flo permission:list
```

`permission:list` shows each role's permissions and which roles can run each controller action.

## Changes apply at once

On every admin request, FloCMS reloads the user's role and status from the database (the `auth.user_loader` setting). Suspended or deleted users are logged out right away, and role changes apply on their next request.

## Login protection

The admin login is throttled: by default 5 failed attempts per email and 20 per IP address in 15 minutes. Adjust `login_throttle` in `config/config.php`. To unlock someone:

```bash
php flo login:unlock admin@example.com
```
