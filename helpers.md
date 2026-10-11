# Helpers and utilities

Core's global helpers are loaded by Composer. Include `vendor/autoload.php`; you don't need to require `Helpers.php` yourself. Helpers that use the active template, language or site URL also need the application bootstrap.

## Escaping HTML

```php
echo e($name);
```

`e($value)` converts the value to a string and escapes HTML quotes and special characters with UTF-8. `null` becomes an empty string. `FloCMS\Core\Functions::e()` does the same. Template `{{ $value }}` output is escaped too; see [views](/docs/{{version}}/views).

`FloCMS\Core\Security::secureText()` is an older HTML-escaping utility. It does not validate input, sanitize arbitrary HTML or protect SQL. Use validation for accepted values, bound database parameters for SQL, and escaping at the point where you output HTML.

## Translations

```php
echo __('welcome', ['name' => $name], 'Welcome');
```

`__($key, $replace = [], $default = null)` returns the translated string. A missing key returns the default, or the key when no default is supplied. If language loading fails, the helper also returns that fallback. See [localization](/docs/{{version}}/localization).

`FloCMS\Core\Functions::getLangPath($lang)` returns `''` for the `.env` default language and `'/'.$lang` otherwise. It does not build the complete URL or check that a language is enabled.

## Theme assets and partials

| Helper | Returns |
|---|---|
| `template_asset('css/app.css')` | The asset URL under the active theme |
| `template_partial('header.html')` | The filesystem path of a partial |
| `render_partial('header.html', $data)` | The rendered partial as a string |

```html
<link rel="stylesheet" href="<?= e(template_asset('css/app.css')) ?>">
<?= render_partial('header.html', ['title' => 'My site']) ?>
```

`template_partial()` only finds the path; it does not render anything. `render_partial()` uses the template engine and throws `RuntimeException` if the file is missing. See [views and templates](/docs/{{version}}/views).

## Static pages

`render_static_page()` is for a simple fallback page, including when the normal application cannot boot:

```php
render_static_page([
    'template' => ROOT . '/templates/default/errors/file-missing.html',
    'status' => 500,
    'contentType' => 'text/html; charset=UTF-8',
    'vars' => ['message' => 'The application is not configured.'],
]);
```

It reads the file, replaces `{{key}}` placeholders with escaped values, prints the result and exits. It does not run template directives or PHP. When the template is missing, it prints `<h1>Page</h1>`. Omitting `status` leaves the current HTTP status unchanged.

## Converting input

`FloCMS\Core\Input` helps convert individual scalar values:

```php
use FloCMS\Core\Input;

$page = Input::int($this->request->queryValue('page'), 1);
$email = Input::email($this->request->input('email'));
$enabled = Input::bool($this->request->input('enabled'), false);
```

| Method | Behavior |
|---|---|
| `str($value, $default = '')` | Trim a string; use the default for `null` |
| `int($value, $default = 0)` | Validate an integer; use the default for invalid/empty input |
| `email($value, $default = '')` | Trim and validate an email; use the default when invalid |
| `bool($value, $default = false)` | Convert PHP filter boolean values; use the default when invalid |

These conversions do not return field errors or enforce your application's rules. For required fields, ranges and structured input, use [validation](/docs/{{version}}/validation). Check the input's shape before passing it to a scalar helper.

## Related services

- [HTTP requests and responses](/docs/{{version}}/http) for headers, files, cookies and request attributes
- [Sessions, cookies and CSRF](/docs/{{version}}/session) for browser state and form tokens
- [Errors and logging](/docs/{{version}}/errors) for error responses and logs
- [Service container](/docs/{{version}}/container) for constructing application services
