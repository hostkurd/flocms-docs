# Admin panel

FloCMS gives every project signing in, roles and a protected `/admin` area. The admin screens come from a separate, optional package, [flocms-admin](https://github.com/hostkurd/flocms-admin): a dashboard, users management, website settings, a media library, pages built from blocks, and list and edit pages for your own tables.

The admin panel has its own documentation, versioned with the package: [flocms-admin documentation](/docs/admin).

## Adding it

The default choice of `composer create-project` is a website with the admin panel, so a new project usually has it already. See [choose what you build](/docs/{{version}}/setups). To add it to an existing project:

```bash
composer require hostkurd/flocms-admin
php flo admin:install
php flo migrate
```

`admin:install` writes `config/admin.php` and `admin/Resources/`, and copies its migrations. It changes nothing else. `php flo admin:doctor` checks the setup at any time. Log in at `/admin` and you land on the panel's dashboard.

flocms-admin 0.39 needs flocms-core 2.7 or newer, and brings flocms-reactive and flocms-uploader with it.

## How it fits together

| The project keeps | flocms-admin adds |
|---|---|
| Login and logout, login throttling, sessions | The dashboard (`/admin/dashboard`) |
| The `users` table and `php flo user:*` | Users management (`/admin/accounts`) and the profile page |
| Roles and permissions in `config/config.php` | A Roles page that can change them |
| The `/admin` route and its login check | List, create, edit and view pages for your resources |

The package plugs into core through the hooks described in [extending from a package](/docs/{{version}}/extending), so the site's files stay as they are. Your own controllers always win: if the site has `controllers/PropertiesController.php`, `/admin/properties` runs it, not the panel. `admin:doctor` reports such clashes.

`composer remove hostkurd/flocms-admin` takes the panel away again. `/admin` then shows the project's own small page, and the tables the panel created stay in the database.

## Your own admin instead

You don't need the package to have an admin area. Every controller action named `admin_...` is served under `/admin/...` for signed-in users with admin access. See [controllers](/docs/{{version}}/controllers#admin-actions) and the [build your own admin](/docs/{{version}}/example-admin) example.
