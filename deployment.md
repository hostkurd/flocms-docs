# Deployment

FloCMS runs on ordinary shared hosting. This page walks through a cPanel deployment; the same steps apply to any server with PHP 8.1 and Apache or Nginx.

## Prepare the server

1. In **MultiPHP Manager**, set the domain to PHP 8.1 or newer.
2. In **Select PHP Version → Extensions**, enable `pdo_mysql`, `mbstring`, `fileinfo` and `gd`.
3. In **SSL/TLS Status**, run AutoSSL so the site has HTTPS.

## Upload the files

With **Terminal** in cPanel (most hosts have it):

```bash
cd ~
composer create-project hostkurd/flocms my-site
```

Without Terminal, run the same command on your computer, zip the folder including `vendor/`, upload it with File Manager and extract it.

## Point the domain at public/

In **Domains → Manage**, set the document root to `/home/USER/my-site/public`. Then `.env`, `vendor/` and your code can never be reached from the web.

> [!NOTE]
> If you can only use `public_html`, the `.htaccess` file in the project root sends every request into `public/`. A separate document root is still the safer setup.

## Configure .env

Create the database and its user with **MySQL Database Wizard**, then edit `.env`:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com

DB_HOST=localhost
DB_NAME=user_mysite
DB_USERNAME=user_mysite
DB_PASSWORD=your-password
```

On cPanel the database host is usually `localhost`, and database and user names start with your account name.

## Finish the installation

```bash
cd ~/my-site
php flo migrate --force
php flo user:create --role=super-admin
php flo doctor
```

`--force` is needed because `migrate` asks for confirmation when `APP_ENV=production`. If `doctor` reports that `storage/` or `views/cache/` isn't writable, fix the permissions in File Manager or run `php flo storage:check --fix`.

## The cron job

Add one cron job under **Cron Jobs → Once Per Minute**:

```bash
* * * * * php /home/USER/my-site/flo schedule:run >> /dev/null 2>&1
```

If your host's default `php` is a different version, use the full path it gives you, such as `/usr/local/bin/ea-php83`. See the [scheduler](/docs/{{version}}/scheduler) for what it runs.

## Force HTTPS

Use the hosting panel's HTTPS redirect or configure it in the web server serving the domain. For the project-root Apache rewrite setup, the root `.htaccess` has commented "force ssl" lines. For a `public/` document root or Nginx, configure the redirect for that active setup instead. Keep `APP_URL` set to the public HTTPS address.

## Nginx

Nginx does not read `.htaccess`. For a site at the domain root, the application locations can be configured as follows inside its server block. Replace the root and PHP-FPM socket with your server's actual paths:

```nginx
root /srv/my-site/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ ^/api/v[0-9]+(?:/.*)?$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/api.php;
    fastcgi_param SCRIPT_NAME /api.php;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}

location ~ \.php$ {
    try_files $uri =404;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}

location ~ /\.(?!well-known/) {
    deny all;
}
```

The API location must precede the generic PHP regex location. `SCRIPT_NAME /api.php` keeps the API's base-path calculation consistent while `REQUEST_URI` remains the client's API URL. This example is for a domain-root install; adapt the prefixes and script name for a subdirectory. Configure TLS/redirects in the domain's server blocks and test the configuration before reloading Nginx.

If the application accepts uploads, align the server's request limit with [PHP and API upload limits](/docs/{{version}}/uploader#request-size-limits).

## Deploying updates

1. Put the site in [maintenance mode](/docs/{{version}}/maintenance): `php flo down --allow=YOUR.IP`
2. Upload the new code, or `git pull`.
3. Run `composer install --no-dev` and `php flo migrate --force`.
4. Run `php flo optimize` to compile templates ahead of time.
5. Bring the site back: `php flo up`

Applications using modules also synchronize and migrate their module registry before step 4, using their own [preparation command](/docs/{{version}}/module-migrations#an-application-preparation-command). `php flo migrate` and `optimize` do not perform that module preparation. Keep private uploads and chunk staging outside `public/`; see [storage](/docs/{{version}}/storage).
