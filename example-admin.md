# Build your own admin

Add a page at `/admin/team` where administrators list the site's users and change their name and role, with the same safety rules a full admin panel uses. It shows how to build any admin page yourself, without an admin package.

> [!NOTE]
> For a ready-made admin panel (dashboard, users management, profile and pages for your own tables), install [flocms-admin](https://github.com/hostkurd/flocms-admin): `composer require hostkurd/flocms-admin` and `php flo admin:install`. This guide is for sites that build their own.

## What FloCMS already does for you

Every controller method whose name starts with `admin_` is served under `/admin/...`, and FloCMS protects it before your code runs:

| Protection | Where |
|---|---|
| Visitors who are not logged in are sent to the login page | The admin login gate in core |
| Users who were suspended, demoted or deleted lose access on their next request | Session refresh (`auth.user_loader` in `config/config.php`) |
| Forms without a valid CSRF token are refused with 419 | Core, for every `POST` |
| Each action needs the permission you declare in `$actionPermissions` | Core, before the action |

Your controller only decides what the page shows, and who may change which user. See [permissions](/docs/{{version}}/permissions) and [authentication](/docs/{{version}}/authentication).

## Before you start

This guide needs the database, unlike the other examples. In your project:

1. Set `DB_NAME`, `DB_USERNAME` and `DB_PASSWORD` in `.env`.
2. Run `php flo migrate` to create the `users` table.
3. Create an Admin and an Editor, for example `php flo user:create --role=admin` and `php flo user:create --role=editor`.
4. Log in at `/admin/users/login` with the Admin account.

The rules use the roles and permissions in `config/config.php`. With the skeleton's defaults, Admins and Super Admins hold `users.manage`; Editors do not.

## Files to create

| File | Purpose |
|---|---|
| `controllers/TeamController.php` | List users, and edit one with the role rules |
| `views/team/admin_index.html` | The list |
| `views/team/admin_edit.html` | The edit form |

The pages use your site's existing admin layout, `templates/default/layouts/admin.html`.

## The controller

**File: `controllers/TeamController.php`**

```php
<?php
namespace FloCMS\Controllers;

use FloCMS\Core\App;
use FloCMS\Core\Auth;
use FloCMS\Core\Config;
use FloCMS\Core\Controller;
use FloCMS\Core\Database;
use FloCMS\Core\HttpException;
use FloCMS\Core\Router;
use FloCMS\Core\Session;
use FloCMS\Core\ValidationException;
use FloCMS\Core\Validator;

class TeamController extends Controller
{
    // Every action needs users.manage (Admins and Super Admins in the skeleton).
    // FloCMS checks it, and the login, before the action runs.
    protected array $actionPermissions = ['*' => 'users.manage'];

    // GET /admin/team
    public function admin_index(): void
    {
        $users = $this->db()->table('users')
            ->select(['id', 'full_name', 'email', 'role'])
            ->orderBy('id')
            ->get();

        $roles = $this->roles();
        foreach ($users as $user) {
            $user->roleName = $roles[(int) $user->role] ?? 'Role ' . $user->role;
            $user->canEdit = $this->canEdit($user);
        }

        $this->data['users'] = $users;
        $this->data['flash'] = Session::getFlash();
    }

    // GET and POST /admin/team/edit/{id}
    public function admin_edit(): void
    {
        $user = $this->findUser();
        if (!$this->canEdit($user)) {
            throw new HttpException(403);
        }

        $choices = $this->assignableRoles();
        $values = ['full_name' => (string) $user->full_name, 'role' => (string) $user->role];
        $errors = [];

        if ($this->request->isMethod('POST')) {
            $values = [
                'full_name' => trim((string) $this->text('full_name')),
                'role' => (string) $this->text('role'),
            ];

            try {
                Validator::validate($values, ['full_name' => 'required|max:190', 'role' => 'required|integer']);
            } catch (ValidationException $e) {
                $errors = $e->getErrors();
            }

            // Never trust the form: check the role again on the server
            if ($errors === [] && !array_key_exists((int) $values['role'], $choices)) {
                $errors['role'][] = 'You cannot give a role higher than your own.';
            }

            if ($errors === []) {
                $this->db()->table('users')->where('id', '=', (int) $user->id)->update([
                    'full_name' => $values['full_name'],
                    'role' => (int) $values['role'],
                ]);
                Session::setFlash('Saved ' . $values['full_name'] . '.', 'success');
                Router::redirect(SITE_URI . '/' . ACTIVE_LANG . '/admin/team');
                return;
            }

            http_response_code(422);
        }

        $this->data['user'] = $user;
        $this->data['values'] = $values;
        $this->data['errors'] = $errors;
        $this->data['roles'] = $choices;
    }

    /** A user you may edit: a lower role than yours (or users.manage_any), never yourself. */
    private function canEdit(object $user): bool
    {
        return (int) $user->id !== (int) Session::get('user_id')
            && Auth::canManageUser((int) $user->role);
    }

    /** @return array<int, string> the roles you may give: up to your own */
    private function assignableRoles(): array
    {
        return array_filter(
            $this->roles(),
            static fn (int $role): bool => Auth::canAssignRole($role),
            ARRAY_FILTER_USE_KEY
        );
    }

    /** @return array<int, string> role number => name, from config/config.php */
    private function roles(): array
    {
        return (array) Config::get('roles', []);
    }

    private function findUser(): object
    {
        $id = $this->params[0] ?? '';
        if (!ctype_digit((string) $id)) {
            throw new HttpException(404);
        }

        return $this->db()->table('users')->where('id', '=', (int) $id)->first()
            ?? throw new HttpException(404);
    }

    private function text(string $key): string
    {
        $value = $this->request->input($key, '');

        return is_string($value) ? $value : '';
    }

    private function db(): Database
    {
        return App::db() ?? throw new \RuntimeException('Set up the database first (DB_NAME in .env).');
    }
}
```

`$actionPermissions = ['*' => 'users.manage']` protects both actions: a user without that permission gets 403 before `admin_index()` or `admin_edit()` runs.

Two methods of `Auth` carry the rules for users:

- `Auth::canManageUser($role)` is true only for users of a **lower** role than yours, unless you hold `users.manage_any` (Super Admins hold `*`, so they can manage everyone). The list hides the Edit link for the others, and `admin_edit()` answers 403 if someone opens the URL anyway.
- `Auth::canAssignRole($role)` is true for roles up to your own. The form only offers those, and the server checks the submitted role again, because a form can be changed in the browser.

Nobody edits themselves here; a profile page is a separate, simpler page.

After a successful save, `Router::redirect()` sends the browser back to the list, so reloading the page does not submit the form twice. `Session::setFlash()` carries the message to that next page.

## The list

**File: `views/team/admin_index.html`**

```html
<section class="team">
    <h1>Team</h1>
    @if($flash !== null)
    <p class="alert alert-{{ $flash['type'] }}">{{ $flash['message'] }}</p>
    @endif
    <table>
        <thead>
            <tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr>
                <td>{{ $user->full_name }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ $user->roleName }}</td>
                <td>
                    @if($user->canEdit)
                    <a href="{{ SITE_URI . '/' . ACTIVE_LANG . '/admin/team/edit/' . $user->id }}">Edit</a>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</section>
```

`{{ ... }}` escapes every value, so a user named `<script>` is shown as text.

## The edit form

**File: `views/team/admin_edit.html`**

```html
<section class="team">
    <h1>Edit {{ $user->email }}</h1>
    <form method="post" action="{{ SITE_URI . '/' . ACTIVE_LANG . '/admin/team/edit/' . $user->id }}">
        @csrf
        <p>
            <label for="full_name">Name</label>
            <input id="full_name" name="full_name" value="{{ $values['full_name'] }}">
            @foreach($errors['full_name'] ?? [] as $message)
            <span class="field-error">{{ $message }}</span>
            @endforeach
        </p>
        <p>
            <label for="role">Role</label>
            <select id="role" name="role">
                @foreach($roles as $number => $name)
                <option value="{{ $number }}"{{ (string) $number === $values['role'] ? ' selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
            @foreach($errors['role'] ?? [] as $message)
            <span class="field-error">{{ $message }}</span>
            @endforeach
        </p>
        <button type="submit">Save</button>
        <a href="{{ SITE_URI . '/' . ACTIVE_LANG . '/admin/team' }}">Cancel</a>
    </form>
</section>
```

`@csrf` adds the hidden token that FloCMS checks on every `POST`.

## Try it

Open `/admin/team` as the Admin:

- the Editor has an Edit link; you (Admin) and any Super Admin do not;
- edit the Editor: the role list stops at Admin;
- give a role above your own by changing the form in the browser's developer tools: the page answers with "You cannot give a role higher than your own." and nothing is saved;
- save a new name: you are back on the list with "Saved …".

Log in as the Editor and open `/admin/team`: FloCMS answers 403, because Editors do not hold `users.manage`.

## Going further

- Add `admin_` actions for creating and deleting users the same way: check `Auth::canManageUser()` on the target, `Auth::canAssignRole()` on the role, hash passwords with `password_hash($password, PASSWORD_DEFAULT)`, and delete only on `POST`.
- Generate a protected page with `php flo make:controller Reports --admin`.
- Move the queries into a [model](/docs/{{version}}/models) when the controller grows.
