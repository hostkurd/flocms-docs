# Sessions, cookies and CSRF

FloCMS uses PHP's own sessions. `public/index.php` starts the session on every page request, so you can read and write it in any controller or view.

## The session cookie

Before the session starts, `public/index.php` makes the session cookie safer than PHP's defaults:

- `HttpOnly`, so JavaScript can't read it
- `SameSite=Lax`, so other sites can't send it with their forms
- `Secure` when the request uses HTTPS
- strict mode, so the server never accepts a session ID it didn't create
- valid until the browser closes

After a successful login, the skeleton also gives the user a new session ID (`session_regenerate_id()`), so a session ID known before the login is useless afterwards.

## Storing values

```php
use FloCMS\Core\Session;

Session::set('cart_id', 42);
$cartId = Session::get('cart_id');   // null when not set
Session::delete('cart_id');
Session::destroy();                  // log out: clear everything and remove the cookie
```

The login stores the user in the session under `user_id`, `role`, `email`, `fullname`, `username`, `isloggedin` and `admin_access`. Read them with `Session::get()`, and check permissions with `Auth::can()` rather than comparing roles yourself. See [roles and permissions](/docs/{{version}}/permissions).

## Flash messages

A flash message is shown once, on the next page, and then removed. Use it after a redirect:

```php
use FloCMS\Core\Router;
use FloCMS\Core\Session;

Session::setFlash('Post saved.', 'success');
Router::redirect(SITE_URI . '/admin/posts');
```

The second argument is a type of your choice, such as `success`, `info`, `warning` or `danger`; the skeleton uses it as a CSS class. There is one flash message at a time, so a second `setFlash()` replaces the first.

In the layout or view:

```html
<?php if (\FloCMS\Core\Session::hasFlash()): ?>
    <div class="alert alert-<?php \FloCMS\Core\Session::flashType(); ?>">
        <?php \FloCMS\Core\Session::flash(); ?>
    </div>
<?php endif; ?>
```

`flashType()` and `flash()` print directly, and `flash()` removes the message. To get it as an array instead, use `Session::getFlash()`, which returns `['message' => ..., 'type' => ...]` or `null`.

> [!WARNING]
> `flash()` prints the message as HTML, so the message can contain markup. Escape anything a visitor typed before you put it in a flash message, for example with `e($value)`.

## Cookies

`FloCMS\Core\Cookie` sets cookies with the same safe defaults as the session cookie: `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, for the whole site.

```php
use FloCMS\Core\Cookie;

Cookie::set('theme', 'dark', 24 * 30);   // name, value, lifetime in hours
$theme = Cookie::get('theme');           // null when not set
Cookie::isValid('theme');                // true when the cookie exists
Cookie::delete('theme');
```

Cookies are stored in the visitor's browser, and the visitor can change them. Don't keep anything in a cookie that you'd trust without checking.

`Cookie::set()` and `delete()` update `$_COOKIE` for subsequent direct reads in the current request. The captured `Request` object keeps its original cookie snapshot; see [HTTP requests](/docs/{{version}}/http#files-and-cookies).

## CSRF protection

Cross-site request forgery is when another website makes a visitor's browser send a request to your site, for example a hidden form that deletes a post. FloCMS blocks it with a token that only your pages know.

**It's automatic.** Every POST, PUT, PATCH and DELETE request to a page must include the session's token, either in a `_token` field or in the `X-CSRF-TOKEN` header. Without a valid token, FloCMS stops the request with status 419, before your controller runs.

### Forms

Put `@csrf` inside every form that posts:

```html
<form method="post" action="">
    @csrf
    <input type="text" name="title">
    <button type="submit">Save</button>
</form>
```

`@csrf` prints `<input type="hidden" name="_token" value="...">`. In plain PHP, use `<?= \FloCMS\Core\Csrf::field() ?>`.

### JavaScript requests

The admin layout prints the token in a meta tag and loads `js/csrf.js`:

```html
<meta name="csrf-token" content="<?= htmlspecialchars(\FloCMS\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
<script src="<?= template_asset('js/csrf.js') ?>"></script>
```

`csrf.js` adds the `X-CSRF-TOKEN` header to every same-origin POST, PUT, PATCH and DELETE request made with `fetch()` or jQuery. For other clients, `FloCsrf.token()` returns the token and `FloCsrf.headers()` the header. Copy both lines into your site layout if your public pages send such requests.

### The token

| Method | Does |
|---|---|
| `Csrf::token()` | The session's token, created on first use |
| `Csrf::field()` | The hidden form field |
| `Csrf::validate($token)` | Checks a token in constant time |
| `Csrf::rotate()` | Replaces the token, for example after a sensitive action |

The token lives in the session, so it stays the same for the whole visit unless you rotate it.

> [!NOTE]
> The API under `/api/v1` has no session and no CSRF check by default, because API clients authenticate with tokens. Admin pages that call the API with the session cookie still send `X-CSRF-TOKEN`. See [API authentication](/docs/{{version}}/api-authentication).
