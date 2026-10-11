# Requests and validation

Everything a visitor sends, form fields, query strings, uploaded files and JSON bodies, reaches your controller through the request object. Check it before you use it.

## Reading the request

In a controller, `$this->request` is the current request (`FloCMS\Core\Http\Request`):

```php
public function store(): void
{
    if (!$this->request->isMethod('POST')) {
        return;
    }

    $email = $this->request->input('email');
    $all   = $this->request->all();
}
```

| Method | Returns |
|---|---|
| `input('name', $default)` | One value from the query string, the form fields or a JSON body |
| `all()` | All of them merged: query string, then form fields, then JSON (later ones win) |
| `query()`, `queryValue('page', 1)` | Only the query string |
| `file('photo')` | The uploaded file's `$_FILES` entry, or `null` |
| `json()` | The decoded JSON body, or `[]`; `jsonError()` tells you why decoding failed |
| `method()`, `isMethod('POST', 'PUT')` | The HTTP method |
| `header('Accept')`, `headers()` | Request headers (names are case-insensitive) |
| `cookie('name')`, `server('REMOTE_ADDR')` | Cookies and server values |
| `path()`, `uri()` | The URL path, and the full request URI |
| `acceptsJson()` | Whether the client asked for JSON |

JSON bodies are only read by `input()` and `all()` when the request's `Content-Type` is JSON. URL parameters aren't in the request; they're in `$this->params`. See [routing](/docs/{{version}}/routing#parameters).

## Cleaning single values

`FloCMS\Core\Input` turns a raw value into the type you expect, with a fallback when it doesn't fit:

```php
use FloCMS\Core\Input;

$name  = Input::str($this->request->input('name'));          // trimmed string, '' when missing
$page  = Input::int($this->request->queryValue('page'), 1);  // 1 unless it's an integer
$email = Input::email($this->request->input('email'));       // '' unless it's a valid address
$agree = Input::bool($this->request->input('agree'));        // "1", "true", "on", "yes" are true
```

## Validating input

`Validator::validate()` checks an array against rules, and throws a `ValidationException` when any rule fails:

```php
use FloCMS\Core\Validator;
use FloCMS\Core\ValidationException;

try {
    Validator::validate($this->request->all(), [
        'name'  => 'required|string|max:100',
        'price' => 'required|integer|min:1000',
        'type'  => 'required|in:sale,rent',
    ]);
} catch (ValidationException $e) {
    $this->data['errors'] = $e->getErrors();
    return;
}
```

`getErrors()` returns the messages per field, for example `['price' => ['price must be at least 1000.']]`.

| Rule | Passes when |
|---|---|
| `required` | The field is present and not an empty string |
| `string` | The value is a string |
| `integer` | An integer, or a string like `"5"` or `"-12"` |
| `numeric` | A number, or a numeric string like `"12.5"` |
| `min:n`, `max:n` | See below |
| `in:a,b,c` | The value is one of the listed values |

**`min` and `max`** compare the value as a number when the field also has `integer` or `numeric`: `integer|min:1000` for a price. Otherwise they count characters, so `min:4|max:4` accepts a PIN like `"0123"`, and for arrays they count items.

> [!IMPORTANT]
> Every rule of a field runs, even when the field is missing. `'phone' => 'string'` fails when there is no phone, so only add rules for optional fields when they were sent. The messages are in English and use the field name.

## Showing errors in a form

A common pattern is to validate, flash a message, and send the visitor back:

```php
use FloCMS\Core\Router;
use FloCMS\Core\Session;

public function admin_add(): void
{
    if (!$this->request->isMethod('POST')) {
        return;
    }

    try {
        Validator::validate($this->request->all(), ['title' => 'required|string|max:190']);
    } catch (ValidationException $e) {
        $this->data['errors'] = $e->getErrors();
        $this->data['old'] = $this->request->all();
        return;   // shows the form again with the errors
    }

    $this->model()->create($this->request->all());
    Session::setFlash('Post saved.', 'success');
    Router::redirect(SITE_URI . '/admin/posts');
}
```

In the view, print the errors next to the fields, and the old values back into the inputs with `{{ $old['title'] ?? '' }}`.

## More rules

The API package, installed on every FloCMS site, has a larger validator: `email`, `url`, `bool`, `array`, `date`, `between`, `regex`, `nullable`, `sometimes`, `exists` and `unique` on top of the rules above. It returns only the validated fields, with integers and booleans already converted, and works on plain arrays in page controllers too:

```php
use FloCMS\Api\Validation\Validator;
use FloCMS\Api\Exceptions\ValidationException;

try {
    $data = (new Validator())->validate($this->request->all(), [
        'email' => 'required|email|unique:subscribers,email',
        'phone' => 'nullable|string|max:30',
    ]);
} catch (ValidationException $e) {
    $this->data['errors'] = $e->errors;
}
```

See [API validation](/docs/{{version}}/api-validation) for every rule and custom messages.
