# Choose what you build

A FloCMS project can be a website, an admin panel, an API, or a mix. You choose once, when the project is created, and FloCMS installs only the packages that choice needs. There is no mode setting to keep in sync: what is installed is what the project does.

## The five setups

At the end of `composer create-project`, FloCMS asks:

```text
  What are you building?
    [1] Website + admin panel
    [2] Website only
    [3] Admin panel only
    [4] API + admin panel
    [5] API only
```

| Setup | Packages | The home page (`/`) | Admin "View site" link |
|---|---|---|---|
| Website + admin panel (the default) | skeleton, flocms-admin | the website | shown |
| Website only | skeleton | the website | no admin panel |
| Admin panel only | skeleton, flocms-admin | goes to `/admin` | hidden |
| API + admin panel | skeleton, flocms-admin, flocms-api | goes to `/admin` | hidden |
| API only | skeleton, flocms-api | a short JSON welcome | no admin panel |

Every setup keeps the skeleton's sign-in: the login page, the `users` table, roles and permissions, the `php flo user:*` commands and the small `/admin` page. The admin panel and the API both use it.

## What setup does

FloCMS shows every step before it runs it and asks "Go ahead?". For API + admin panel:

```text
  API + admin panel:
    composer require hostkurd/flocms-admin:^0.39.3 hostkurd/flocms-api:^1.3
    php flo admin:install
    php flo api:install
    remove views/pages/index.html, templates/dark/layouts/default.html, public/themes/dark/css/style.css
    config/config.php: home = admin
    config/admin.php: site_link = false
```

- **Packages** are added with `composer require`, then each package's own installer runs: `php flo admin:install` and `php flo api:install`.
- **The example pages** (the welcome page and the `dark` example template) are removed for the admin-only and API setups, and only while they are still the skeleton's own files. A file you changed is kept, and setup names it.
- **Two settings** are written, both plain lines you can change later: `home` in `config/config.php` and, with the admin panel, `site_link` in `config/admin.php`.

Going back to a website, for example from admin panel only to website + admin panel, writes a simple `views/pages/index.html` when an earlier setup removed the welcome page.

If a command fails, setup stops and says so. Fix the problem and run `php flo setup` again: the installers only add what is missing.

## Running setup yourself

```bash
php flo setup                          # ask
php flo setup --as=admin               # site-admin, site, admin, api-admin or api
php flo setup --as=api-admin --dry-run # print the plan, change nothing
```

> [!STUDIO]
> Choose under **What are you building?** when you start a **New project**: Studio runs `php flo setup --as=` with your choice. See [your first project in Studio](/docs/studio/first-project#step-1-project).

Without a terminal, for example `composer create-project -n`, a CI job or a host's one-click installer, setup changes nothing, so the project is **website only**. Run `php flo setup` later, or pass `--as` in your script.

> [!IMPORTANT]
> On Windows, Composer gives its scripts no terminal, so the question isn't asked during `create-project`. Run it as a second step: `cd my-site`, then `php flo setup`.

> [!NOTE]
> Setup only adds. Going from website to website + admin, or from admin panel to API + admin panel, is fine. When a package you have isn't part of the setup you choose, setup leaves it installed and prints the steps to remove it yourself, because it can't know what you built on it.

`commands/SetupCommand.php` is part of your project. You can delete it once the project is set up.

## The home page setting

`config/config.php` decides what `/` does:

```php
Config::set('home', 'site');   // 'site', 'admin' or 'api'
```

- `site`: the website. When flocms-admin's Pages has a home page, that page is shown.
- `admin`: a redirect to `/admin`, which shows the login page to visitors who aren't signed in.
- `api`: a short JSON welcome, `{"name": "My site", "api": "https://example.com/api/v1"}`.

Any other value means `site`, so a typo never hides your website.

## The admin panel's website link

`config/admin.php` decides where the panel's **View site** link goes:

```php
'site_link' => true,                    // the site's address (the default)
'site_link' => false,                   // no link: the panel is the whole project
'site_link' => 'https://example.com',   // a website on another address
```

`php flo admin:doctor` warns when Pages are on while `site_link` is `false`, since Pages are website pages.

## Add the admin panel later

```bash
php flo setup --as=site-admin
```

or by hand:

```bash
composer require hostkurd/flocms-admin
php flo admin:install
php flo migrate
```

> [!STUDIO]
> Open the project's **Packages** tab and click **Add** next to **Add the admin panel** or **Add the API**. Studio shows the commands first. See [packages and updates in Studio](/docs/studio/packages#add-the-admin-panel-or-the-api).

## Add an API later

```bash
composer require hostkurd/flocms-api
php flo api:install
php flo migrate
```

`php flo api:install` writes `public/api.php`, `api/routes.php`, two example controllers and a resource. It never changes a file that already exists, so it is safe to run again; `--minimal` writes only `public/api.php` and a routes file with the health check. The skeleton's `public/.htaccess` already sends `/api/v1` to `public/api.php`, and its `composer.json` already loads `App\Api\` from `api/`. See [building an API](/docs/{{version}}/api).
