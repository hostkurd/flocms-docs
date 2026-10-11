# Request lifecycle

You can build a FloCMS site without knowing what happens between the browser's request and your controller. But when something behaves unexpectedly, it helps to know the order of the steps. This page follows one page request from start to finish.

## The short version

```text
Browser
  → public/index.php            session cookie settings, Composer autoloader
  → config/bootstrap.php        .env, error handler, APP_URL, config/config.php
  → maintenance check           503 while `php flo down` is active
  → App::run()
      → Router                  language, area, controller, action, parameters
      → conf_global.php         constants such as ACTIVE_LANG
      → language file           lang/<language>.php
      → CSRF check              POST, PUT, PATCH and DELETE requests
      → admin checks            session freshness, login redirect
      → controller action       permission check, then your method
      → view                    views/<controller>/<action>.html
      → layout                  templates/<template>/layouts/<area>.html
  → Response sent
```

API requests take a different path, through `public/api.php`. See [API requests](#api-requests) below.

## 1. The entry point

The web server's document root is `public/`. Its `.htaccess` file sends `/api/v1/...` to `api.php`, serves existing files (CSS, images, uploads) directly, and sends everything else to `index.php`.

`public/index.php` then:

- sets the session cookie to `HttpOnly` and `SameSite=Lax`, and `Secure` when the request uses HTTPS
- loads Composer's autoloader
- starts the session
- loads `config/bootstrap.php`

## 2. Bootstrap

`config/bootstrap.php` prepares the application:

1. **`.env`** is loaded. If the file is missing, FloCMS shows an error page that says so, with status 500.
2. **The error handler** is registered. From here on, every uncaught error or exception ends on an [error page](/docs/{{version}}/errors).
3. **`APP_URL`** is checked and becomes the `SITE_URI` constant, which you use to build links.
4. **Paths** and the class aliases of `includes/compat.php` are defined, so `Config` works as well as `FloCMS\Core\Config`.
5. **`config/config.php`** is loaded. See [configuration](/docs/{{version}}/configuration).

## 3. Maintenance mode

If `php flo down` is active, the request stops here with a 503 page, unless the visitor's IP is allowed. Nothing else runs, not even the database connection. See [maintenance mode](/docs/{{version}}/maintenance).

## 4. Routing

`App::run()` creates the router, which reads the URL from left to right: an optional language, an optional area such as `admin`, the controller, the action and the parameters. See [routing](/docs/{{version}}/routing).

Then `public/conf_global.php` defines constants that views and controllers use, among them `ACTIVE_LANG`, `CURRENT_CONTROLLER`, `CURRENT_ACTION`, `IS_ADMIN` and `VIEWS_PATH`. The language file of the active language is loaded, so `__()` works from now on.

## 5. CSRF protection

A POST, PUT, PATCH or DELETE request must carry the session's CSRF token, in the `_token` field or the `X-CSRF-TOKEN` header. Without a valid token, the request stops with status 419. See [sessions and CSRF](/docs/{{version}}/session).

## 6. Admin checks

For requests under `/admin`:

- **Session freshness.** When `auth.user_loader` is configured, the signed-in user's role and status are reloaded from the database, so a suspended user loses access on their next click. See [authentication](/docs/{{version}}/authentication).
- **Login.** Visitors without admin access are redirected to `/admin/users/login/`. Signed-in admins who open the login page go to `/admin/`.

## 7. The controller action

The router's controller name becomes a class: `blog_post` becomes `FloCMS\Controllers\BlogPostController`. FloCMS then checks, in this order:

1. The controller and action names contain only letters, digits and `_`.
2. The class exists and extends `FloCMS\Core\Controller`.
3. The action is a public, non-static method declared on your controller.
4. The current user has the permission that `$actionPermissions` requires for it (403 otherwise).

A failed name, class or method check answers 404, and a class that doesn't extend `Controller` is a 500 error. Then your action runs. See [controllers](/docs/{{version}}/controllers).

> [!NOTE]
> The controller's constructor runs before the action is checked. Keep constructors light, and create models on first use, as generated controllers do.

## 8. View and layout

After the action, FloCMS renders a view:

- the path the action returned, if it returned one
- otherwise `views/<controller>/<prefix><action>.html`, for example `views/blog/admin_index.html`

The values in `$this->data` become variables in the view. The rendered view is then placed in the layout of the area: `templates/<template>/layouts/default.html` for site pages, `admin.html` under `/admin`. The layout prints it with `$data['content']`. See [views and templates](/docs/{{version}}/views).

## 9. Errors along the way

An exception at any step goes to the error handler. It writes the error to `storage/logs/app.log` and shows the matching error page: 404, 403, 500, or a database error page. With `APP_DEBUG=true`, most errors show a detailed debug page instead. See [error pages and logging](/docs/{{version}}/errors).

## API requests

Requests to `/api/v1/...` go to `public/api.php`. It loads the same bootstrap and checks maintenance mode, but then hands the request to the [flocms-api](/docs/{{version}}/api) kernel:

- no session and no CSRF check by default
- routes come from `api/routes.php`, not from the URL convention
- a middleware pipeline adds security headers, CORS, the locale and rate limits
- every response, errors included, is JSON
