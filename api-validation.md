# API validation

Validate every request body and query string before you use it. A failed validation answers 422 with a message per field, which API clients can show next to their form fields.

## Validating a request

Use the `ValidatesRequests` trait in a controller:

```php
use FloCMS\Api\Controller;
use FloCMS\Api\Validation\ValidatesRequests;
use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;

final class ContactController extends Controller
{
    use ValidatesRequests;

    public function store(Request $request): Response
    {
        $message = $this->validate($request, [
            'name'    => 'required|string|max:100',
            'email'   => 'required|email|max:190',
            'phone'   => 'nullable|string|max:30',
            'message' => 'required|string|min:10|max:5000',
        ]);

        // $message holds only these four fields

        return $this->success(['received' => true], 202);
    }
}
```

This is the skeleton's `ContactController`. Sending `{"name": "A"}` gives:

```json
{"success": false, "message": "Validation failed.",
 "errors": {"email": ["email is required."], "message": ["message is required."]},
 "request_id": "5f9b9e9f..."}
```

`validate()` reads the query string, form fields and JSON body. It returns only the fields in your rules, and converts `integer`, `numeric` and `bool` fields to PHP types, so `"12"` comes back as `12`.

## Validating the query string

`validateQuery()` checks only the query string, for list filters:

```php
$filters = $this->validateQuery($request, [
    'min_price' => 'sometimes|integer|min:0',
    'city'      => 'sometimes|string|max:100',
]);
```

Without it, `?min_price=abc` would silently become `0` somewhere in your code. With it, the client gets a 422.

## Rules

| Rule | Passes when |
|---|---|
| `required` | The field is present and not empty (`null`, `''` and `[]` fail) |
| `string` | A string |
| `integer` | An integer, or a string like `"5"` |
| `numeric` | A number, or a numeric string like `"12.5"` |
| `bool` | `true`, `false`, `1`, `0`, `"1"`, `"0"`, `"true"` or `"false"` |
| `array` | An array |
| `email` | A valid email address |
| `url` | A valid `http` or `https` URL |
| `date`, `date:Y-m-d` | A date. Without a format: `Y-m-d`, `Y-m-d H:i:s` or ISO 8601. |
| `min:n`, `max:n` | At least / at most `n`: the value for `integer` and `numeric` fields, otherwise characters for strings and items for arrays |
| `between:min,max` | Both of the above |
| `in:a,b,c` | One of the listed values |
| `regex:pattern` | Matches the pattern |
| `exists:table,column` | A row with this value exists |
| `unique:table,column` | No row has this value yet |
| `nullable` | `null` or `''` is accepted, and returned as `null` |
| `sometimes` | The rules only run when the field is present |

A field that isn't `required` and wasn't sent is skipped and left out of the result. When a pattern contains `|`, use the array form: `'code' => ['required', 'regex:/^[A-Z]{2}\d+$/']`.

### unique on update

When a record is updated, its own value isn't a duplicate. Pass its ID to ignore it:

```php
'email' => 'required|email|unique:users,email,' . $id,          // ignores the row with id = $id
'slug'  => 'required|string|unique:posts,slug,' . $id . ',post_id', // when the key column isn't "id"
```

`exists` and `unique` run prepared queries on the site's database. Table and column names must be plain names.

## Custom messages

The third argument replaces messages, per field and rule, or per rule with `:field` for the field name:

```php
$data = $this->validate($request, $rules, [
    'email.required' => 'Please enter your email address.',
    'max'            => ':field is too long.',
]);
```

## Custom rules

Override `validator()` in your controller to add rules. A rule returns an error message, or `null` when the value is fine:

```php
use FloCMS\Api\Validation\Validator;

protected function validator(): Validator
{
    return (new Validator())->extend('even', static fn (mixed $value): ?string
        => (int) $value % 2 === 0 ? null : 'Must be an even number.');
}
```

```php
'quantity' => 'required|integer|even',
```

The callback also receives the rule's parameter, the field name and all the data: `fn ($value, $parameter, $field, $data)`.

## Request classes

For long rule lists, keep the rules out of the controller:

```bash
php flo make:request StorePost
```

creates `api/Requests/StorePostRequest.php` with `rules()`, `messages()` and a static `validate()`:

```php
use App\Api\Requests\StorePostRequest;

public function store(Request $request): Response
{
    $data = StorePostRequest::validate($request);
    // ...
}
```

## Outside controllers

The validator works on any array:

```php
use FloCMS\Api\Validation\Validator;

$data = (new Validator())->validate($input, ['email' => 'required|email']);
```

It throws `FloCMS\Api\Exceptions\ValidationException`, which the API turns into a 422 response. Its errors are in `$e->errors`. Core's `Validator::validate()` throws a different `ValidationException`, which the API also answers with a 422. See [requests and validation](/docs/{{version}}/validation) for the core validator.
