# Extending FloCMS from a package

A package can add pages, admin screens and sign-in steps to a FloCMS site without asking the site to edit its controllers. Core offers a small set of hooks for this. flocms-admin is built on them, and your own packages can use them the same way.

Every hook does nothing until something registers it, so a site that uses none of them behaves exactly as before. The site's own code always wins: its controllers come before any package's, and its routes and checks still apply.

| Hook | Since core | What it lets a package do |
|---|---|---|
| `App::resolveControllersUsing()` | 2.3.0 | Supply page and admin controllers from the package |
| Returning a `Response` from an action | 2.3.2 | Answer with JSON, a fragment or an empty response, without a view or layout |
| `Auth::loadRolesUsing()` | 2.4.0 | Supply roles and permissions at runtime, for example from a table |
| `App::allowGuestAdminActions()` | 2.5.0 | Open a few admin pages to visitors who are not signed in |
| `Auth::checkSessionsUsing()` | 2.5.0 | End sessions that should no longer be valid |
| `App::fallbackUsing()` | 2.6.0 | Answer public addresses that no controller answers |
| `Auth::holdSessionsUsing()` | 2.7.0 | Hold a fresh sign-in at one page until a step is done |

## Where to register them

Register hooks once per request, before `App::run()`. The simplest place is a file that Composer loads for every request, through the package's `autoload.files`:

```json
{
    "autoload": {
        "psr-4": { "Acme\\Reports\\": "src/" },
        "files": ["bootstrap.php"]
    }
}
```

The site then only runs `composer require`; it edits no files, and `composer remove` takes the hooks away again. A site can also register a hook itself in `config/config.php`.

## Controllers from a package

When a URL names a controller the site doesn't have, core asks each resolver for a class. A resolver gets the controller segment of the URL and the route (`default` or `admin`), and returns a class name or `null` to pass:

```php
use FloCMS\Core\App;

App::resolveControllersUsing(static function (string $controller, string $route): ?string {
    return $route === 'admin' && $controller === 'reports'
        ? \Acme\Reports\ReportsController::class
        : null;
});
```

`/admin/reports` now runs `Acme\Reports\ReportsController::admin_index()`. The rules are the same as for the site's own controllers:

- A `FloCMS\Controllers\ReportsController` in the site always wins, and the resolver isn't asked.
- Resolvers are asked after the admin login check, so admin actions still need a signed-in user with admin access.
- The class must extend `FloCMS\Core\Controller`. Anything else answers 404.
- Only the class's own public actions can be reached, and its `$actionPermissions` apply. See [request lifecycle](/docs/{{version}}/lifecycle#7-the-controller-action).

`App::clearControllerResolvers()` removes every resolver, which tests use between cases.

## Returning a response

An action may return a `FloCMS\Core\Http\Response` instead of a view path. Core sends it as it is, without a view or layout, after the usual login, permission and CSRF checks:

```php
use FloCMS\Core\Http\Response;

public function admin_status(): Response
{
    return Response::json(['status' => 'ok']);
}
```

`Response::html()`, `Response::text()`, `Response::json()`, `Response::make()` and `Response::noContent()` build one. A HEAD request gets the status and headers without the body. Actions that return a view path, or nothing, work as before.

## Roles from a table

`Auth::loadRolesUsing()` gives core the roles and permissions at runtime instead of only from `config/config.php`. The loader runs once per request and returns any of these keys:

```php
use FloCMS\Core\Auth;

Auth::loadRolesUsing(static function (): ?array {
    return [
        'roles' => [0 => 'User', 1 => 'Editor', 2 => 'Admin', 3 => 'Super Admin', 4 => 'Translator'],
        'permissions' => [
            0 => [],
            1 => ['content.*'],
            2 => ['content.*', 'users.manage', 'users.assign_role', 'settings.*', 'activity.view'],
            3 => ['*'],
            4 => ['content.posts.update'],
        ],
        'admin_access_roles' => [1, 2, 3, 4],
        'levels' => [4 => 1],
    ];
});
```

Each key it returns replaces that whole setting, so return every role, not only the new one. Keys it leaves out, or a `null` result, keep the values from `config/config.php`. A loader that throws or returns something else is logged and ignored, so the configured roles apply.

`levels` decides who may manage whom: `Auth::canAssignRole()` and `Auth::canManageUser()` compare levels, not role numbers. Without a level, a role's level is its number, so existing sites behave as before. Read the current values with `Auth::roles()`, `Auth::permissionMap()`, `Auth::adminAccessRoles()`, `Auth::hasAdminAccess($role)` and `Auth::level($role)`.

flocms-admin's Roles page uses this hook. See [roles and permissions](/docs/{{version}}/permissions).

## Admin pages for guests

Admin actions require a signed-in user. A "forgot password" page has to open without one. `App::allowGuestAdminActions()` opens the listed actions, the way the login page is open:

```php
App::allowGuestAdminActions('password', ['admin_forgot', 'admin_reset'], \Acme\Auth\PasswordController::class);
```

- Use exact controller and method names; there are no wildcards.
- Pass the class as the third argument. Guests then reach only that class, and a site controller with the same name stays behind the login.
- CSRF on POST, the action checks and `$actionPermissions` still apply. Every other admin action still sends guests to the login page.

`App::isGuestAdminAction()` tells whether an action was opened, and `App::clearGuestAdminActions()` removes them all.

## Ending sessions

When `auth.user_loader` is set (the skeleton sets it), core reloads the signed-in user on admin requests and ends the session of a suspended user. `Auth::checkSessionsUsing()` adds your own check on that fresh user row. Return `false` to end the session:

```php
Auth::checkSessionsUsing(static function (array $user): bool {
    // The password changed since this session signed in
    return ($_SESSION['password_stamp'] ?? null) === substr(hash('sha256', (string) $user['password']), 0, 16);
});
```

A check that throws is logged and also ends the session. `Auth::clearSessionChecks()` removes them.

## Public addresses from a package

`App::fallbackUsing()` answers website addresses that no controller answers, such as pages kept in a database (`/about`, `/services/web-design`). Each fallback gets the path after the language (`''` for the home page) and the request, and returns a `Response` or `null` to pass:

```php
use FloCMS\Core\App;
use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;

App::fallbackUsing(static function (string $path, Request $request): ?Response {
    $page = \Acme\Pages\Pages::findByPath($path);

    return $page !== null ? Response::html($page->render()) : null;
});
```

Fallbacks are asked only on website URLs, never for `/admin` or the API, after CSRF and maintenance mode, and only when the URL would otherwise answer 404. Site controllers and resolvers always come first. When no fallback answers, the visitor gets the 404 page as before. Returning anything other than a `Response` or `null` is an error. `Router::getPath()` gives the same path anywhere in the request; `App::clearFallbacks()` removes them.

flocms-admin's Pages use this hook.

## Sign-in steps

`Auth::holdSessionsUsing()` holds a fresh sign-in at one admin page until a step is done: a two-factor code, accepting new terms, or changing a temporary password. The hold gets the user row and returns an admin path, or `null` for no hold:

```php
use FloCMS\Core\App;
use FloCMS\Core\Auth;

Auth::holdSessionsUsing(static fn (array $user): ?string => !empty($user['must_accept_terms']) ? 'terms' : null);
App::allowGuestAdminActions('terms', ['admin_index'], \Acme\Terms\TermsController::class);
```

While the sign-in is held, the site and the admin area see a visitor who isn't signed in, and every admin request goes to `/admin/terms`. Only guest admin actions and logout still open, so the step's own page must be a guest admin action, as above. When the step is done, the page calls `Auth::releaseHold()`, which signs the person in with a new session id and asks the next hold, if any. `Auth::cancelHold()` signs out instead.

- The holds are asked once per sign-in, on the request that signs in or the next one.
- A hold that throws, or returns a path that isn't valid, ends the session. A hold never lets a session through by mistake.
- A held sign-in older than `auth.hold_lifetime` seconds (default 600) ends, and the person starts again at the login page.
- `Auth::heldPath()` and `Auth::heldUserId()` tell a page which step and which user it is serving.

flocms-admin's two-factor sign-in and email verification use this hook.
