# Installation

Create a new FloCMS project with Composer, check that your server is ready, and set up the database and your first admin account.

## Requirements

- PHP 8.1 or newer, with the `pdo`, `mbstring`, `fileinfo` and `gd` extensions
- Composer 2
- MySQL or MariaDB, only if your site uses a database. The welcome page works without one.
- A web server that points to the `public/` folder: Apache with `.htaccess`, or Nginx

## Create a project

Run `create-project` with the name of the folder you want:

```bash
composer create-project hostkurd/flocms my-site
cd my-site
```

Composer installs the framework packages, copies `.env.example` to `.env`, creates the `storage` folders and generates a fresh `APP_KEY` for you.

Then it asks what you are building: a website, an admin panel, an API, or a mix. It adds only the packages that choice needs. See [choose what you build](/docs/{{version}}/setups).

> [!STUDIO]
> Click **New project**, pick a folder and what you are building, and Studio runs `create-project` and the setup for you. It also downloads PHP, Composer and MariaDB when you don't have them. See [your first project in Studio](/docs/studio/first-project).

> [!NOTE]
> `composer.lock` records the exact versions of the FloCMS packages a project installs. Deploy with `composer install` to preserve them, and change versions deliberately with `composer update`. See [framework and packages](/docs/{{version}}/packages#versions-in-this-guide) and the [upgrade guide](/docs/{{version}}/upgrade).

## Start a local server

Use the built-in server while you develop, then open the address it prints:

```bash
php flo serve
```

> [!STUDIO]
> Click **Start server** on the project page. Studio runs `php flo serve` and shows the address, and the **Logs** tab shows what the server prints. See [the server and the database](/docs/studio/server-and-database).

If `APP_URL` in `.env` doesn't match the address you opened, the welcome page tells you, and on a local install it offers a button that writes the right URL to `.env`.

## Check your installation

`php flo doctor` checks PHP and its extensions, that `storage/` and `views/cache/` are writable, your `.env` file, `APP_KEY`, `APP_URL`, the database and pending migrations. It exits with 1 when a check fails, so you can use it in scripts:

```bash
php flo doctor
```

> [!STUDIO]
> The project's **Doctor** tab shows these checks, and the admin panel's, each with what to do about it. Studio runs them when you open the project. See [Doctor in Studio](/docs/studio/doctor).

## Database and first admin

Create a database (in phpMyAdmin, for example), then add its details to `.env`:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=my_site
DB_USERNAME=root
DB_PASSWORD=
```

Run the migrations, which create the users table (and the tables of the admin panel and the API, when you added them), then create the first admin account:

```bash
php flo migrate
php flo user:create --role=super-admin
```

`user:create` asks for the name, email and password. The password is hidden and asked twice. Log in at `/admin/users/login`.

> [!STUDIO]
> A new project in Studio can make the database on Studio's own MariaDB, run the migrations and create the first admin in one go: fill in the **Database** and **First admin** steps. See [your first project in Studio](/docs/studio/first-project#step-2-database).

## Next steps

- [Configuration](/docs/{{version}}/configuration): the `.env` file and `config/config.php`
- [Routing](/docs/{{version}}/routing): how URLs reach your controllers
- Generate your first page with `php flo make:route Blog`, which creates a controller, a model and a view
