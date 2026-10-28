# Roles and permissions

Admin access is controlled by roles. Each user has one role, and each role has a set of permissions that controllers check before an action runs.

## Roles

FloCMS ships with four roles, defined in `config/config.php`:

| Role | Name | Permissions |
|---|---|---|
| 0 | User | none |
| 1 | Editor | `content.*` |
| 2 | Admin | `content.*`, `users.manage`, `users.assign_role`, `settings.*`, `activity.view` |
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
    2 => array('content.*', 'users.manage', 'users.assign_role', 'settings.*', 'activity.view'),
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

A user without the permission gets a 403 page, before the action runs. A controller without `$actionPermissions` checks nothing; its `admin_` actions are still limited to users with admin access.

`php flo make:controller Blog --admin` generates `'*' => 'blog.manage'`. Until you add that permission to other roles, or change it to one they hold such as `content.edit`, only Super Admins can open the controller.

## Checking inside an action

```php
use FloCMS\Core\Auth;

if (Auth::can('settings.edit')) {
    // show the settings form
}

Auth::authorize('settings.edit'); // throws a 403 when not allowed
```

When `config/config.php` has no `permissions` setting at all, `Auth::can()` allows everything to users with admin access, which is how sites behaved before permissions existed.

## Managing users

Two checks keep admins from promoting themselves or managing their superiors:

```php
Auth::canAssignRole(2);   // may I give someone role 2? Needs users.assign_role, and never above my own role
Auth::canManageUser(1);   // may I edit, delete or suspend a user with role 1? Needs users.manage
```

`canManageUser()` only allows users of a strictly lower role, unless you hold `users.manage_any`. Super Admins hold `*`, so they can manage everyone. Since core 2.4 both checks compare role **levels**: a role's level is its number unless a `levels` setting says otherwise, so roles added later can sit between the built-in ones.

The skeleton has no users pages of its own. The [admin panel](/docs/{{version}}/admin-panel) adds users management and a Roles page that can change roles and permissions at runtime (through `Auth::loadRolesUsing()`, see [extending from a package](/docs/{{version}}/extending#roles-from-a-table)); or build your own, as in the [build your own admin](/docs/{{version}}/example-admin) example.

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

On every admin request, FloCMS reloads the user's role and status from the database. Suspended or deleted users are logged out right away, and role changes apply on their next request. See [authentication](/docs/{{version}}/authentication#sessions-end-at-once).

The admin login is also protected against password guessing. See [login throttling](/docs/{{version}}/authentication#login-throttling).
