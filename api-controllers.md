# Controllers and resources

A route's handler does the work: it reads the request, talks to the database and returns data. Resources decide what that data looks like in JSON, so internal columns never leak.

## Handlers

A handler can be:

- a controller method: `[PostsController::class, 'show']`, or the string `'PostsController@show'`
- an invokable class name or object: `new HealthController()`
- a closure: `fn () => ['status' => 'ok']`

```bash
php flo make:api-controller Posts
php flo make:api-controller Posts --resource   # index, show, store, update, destroy
```

creates `api/Controllers/PostsController.php` (namespace `App\Api\Controllers`).

### Arguments

FloCMS fills a handler's arguments by name and type:

- **Route parameters** by name. A typed parameter is converted: `int $id` gets an integer, and a value that doesn't fit is a 422.
- **The request**: a `Request` argument, or one named `$request`.
- **Other classes**, such as a model, are created for you, with their own constructor arguments filled the same way.
- Default values and nullable arguments are used when nothing else fits.

```php
use FloCMS\Api\Controller;
use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;

final class PostsController extends Controller
{
    public function __construct(private readonly PostsModel $posts)
    {
    }

    public function show(Request $request, int $id): Response
    {
        $post = $this->posts->find($id) ?? $this->fail('Post not found.', 404);

        return PostResource::make($post)->response($request);
    }
}
```

### Return values

| Return | Response |
|---|---|
| An array or scalar | `200 {"success": true, "data": ...}` |
| A resource or resource collection | `200` with the resource as `data` |
| `null` | `204 No Content` |
| A `Response` | Sent as it is |

## Controller helpers

Controllers that extend `FloCMS\Api\Controller` get these helpers:

| Helper | Does |
|---|---|
| `success($data, $status = 200, $meta = [])` | `{"success": true, "data": ..., "meta": ...}` |
| `created($data)` | The same with status 201 |
| `noContent()` | 204 with no body |
| `fail($message, $status = 400, $errors = [])` | Stops with an error response |
| `invalid($errors)` | Stops with a 422 and per-field errors |
| `routeId($request, 'id')` | The route parameter as a positive integer, or a 422 |

`fail()` and `invalid()` throw, so you can use them after `??`, as in the example above.

## Errors

Every error uses one JSON envelope:

```json
{"success": false, "message": "Validation failed.", "errors": {"email": ["email is required."]}, "request_id": "5f9b9e9f..."}
```

- Throw `FloCMS\Api\Exceptions\ApiException($message, $status, $errors, $headers)` for any status.
- Throw `FloCMS\Api\Exceptions\ValidationException($errors)` for a 422.
- Core's `HttpException` and `ValidationException` work too and keep their status (403, 404, 422).
- Anything else is a 500, written to `storage/logs/api.log`. With `APP_DEBUG=true` the response shows the message, class, file and line; in production only "Internal Server Error". Details of 5xx `ApiException`s are hidden in production too.

Every response carries an `X-Request-ID` header, and error bodies include it as `request_id`. A valid `X-Request-ID` sent by the client is reused, so you can follow one request through your logs.

## Resources

A resource turns a database row into the JSON your API promises:

```bash
php flo make:resource Post
```

```php
<?php

namespace App\Api\Resources;

use FloCMS\Api\Resources\JsonResource;
use FloCMS\Core\Http\Request;

final class PostResource extends JsonResource
{
    protected array $includes = ['author'];              // allowed in ?include=

    public function toArray(Request $request): array
    {
        return [
            'id'         => (int) $this->id,
            'title'      => $this->title,
            'url'        => '/blog/' . $this->slug,
            'created_at' => $this->date($this->created_at),  // ISO 8601
            'notes'      => $this->when($request->attribute('identity') !== null, fn () => $this->notes),
            'author'     => $this->whenIncluded($request, 'author', fn () => AuthorResource::make($this->author)),
        ];
    }
}
```

Inside `toArray()`, `$this->column` reads the row, whether it's an object or an array. Columns you don't list never reach the response, like the skeleton's `UserResource`, which leaves out the password and token.

| Helper | Does |
|---|---|
| `$this->when($condition, $value)` | Includes the field only when `$condition` is true. `$value` may be a closure. |
| `$this->whenIncluded($request, 'name', $value)` | Includes the field only when the client asked for `?include=name` |
| `$this->date($value)` | Formats a date as ISO 8601; `null` stays `null` |

`?include=` values that aren't in `$includes` are a 422.

Return one resource or a list:

```php
return PostResource::make($post);
return PostResource::collection($posts);
return PostResource::make($post)->response($request, 201);   // a Response with another status
```

For paginated lists, see [pagination](/docs/{{version}}/api-pagination).

## Database access

API controllers use the same database as your pages. Use your models, or `App::db()` and the [query builder](/docs/{{version}}/queries):

```php
use FloCMS\Core\App;

$db = App::db() ?? $this->fail('The database is not configured.', 503);
$post = $db->table('posts')->where('id', '=', $id)->first();
```
