# OpenAPI documentation

FloCMS can describe your API as an OpenAPI 3.1 document. Tools such as Swagger UI, Postman and client generators read it, so the people who use your API always have an up-to-date reference.

## Generating the document

```bash
php flo api:docs                                   # print it
php flo api:docs --out=public/openapi.json         # write a file
```

| Option | Default | |
|---|---|---|
| `--out=` | standard output | The file to write |
| `--title=` | `FloCMS API` | The API's title |
| `--api-version=` | `1.0.0` | The version of your API |
| `--server=` | none | The server URL, e.g. `https://example.com` |
| `--routes=` | `api/routes.php` | The routes file |
| `--prefix=` | `/api` | The URL prefix of the routes |

With `APP_DEBUG=true`, a new project also serves the document at `/api/v1/openapi.json`, built from the routes on every request and titled after `APP_NAME`. It's off in production, so your live site doesn't publish its API map unless you choose to, for example by committing the generated file.

## What's in it

Without any extra work, the document lists every route with:

- its method and path
- its path parameters, typed from the route's constraints and the handler's arguments (`{id:\d+}` or `int $id` becomes an integer)
- the route name as `operationId`
- a tag from the first path segment after the version, such as `users`
- deprecation, for routes behind the `Deprecated` middleware

Handlers are only inspected, never run, so generating the document has no side effects.

## Describing routes with attributes

Attributes on controllers and methods add summaries, request bodies and responses:

```php
use FloCMS\Api\OpenApi\{RequestBody, ResponseSchema, Summary, Tag};

#[Tag('Contact')]
final class ContactController extends Controller
{
    #[Summary('Send a message to the site owner')]
    #[RequestBody([
        'type' => 'object',
        'required' => ['name', 'email', 'message'],
        'properties' => [
            'name' => ['type' => 'string', 'maxLength' => 100],
            'email' => ['type' => 'string', 'format' => 'email'],
            'message' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 5000],
        ],
    ])]
    #[ResponseSchema(202, ['type' => 'object', 'properties' => ['received' => ['type' => 'boolean']]], 'Message accepted')]
    #[ResponseSchema(422, description: 'Validation failed')]
    #[ResponseSchema(429, description: 'Too many requests')]
    public function store(Request $request): Response
    {
        // ...
    }
}
```

This is part of the skeleton's `ContactController`.

| Attribute | Arguments |
|---|---|
| `#[Tag('Name')]` | Groups the controller's routes under a heading |
| `#[Summary('Short text', 'Longer description')]` | A summary and an optional description |
| `#[RequestBody($schema, required: true, contentType: 'application/json', description: null)]` | The request body as a JSON Schema |
| `#[ResponseSchema($status, $schema = [], $description = null)]` | One possible response; repeat it for each status |

Schemas are plain PHP arrays in [JSON Schema](https://json-schema.org) form, as in OpenAPI 3.1.

> [!TIP]
> Keep the `RequestBody` schema in step with the validation rules of the same method. The validator is what actually decides; the schema tells clients what to send.
