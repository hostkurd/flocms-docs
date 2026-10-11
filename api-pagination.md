# Pagination, sorting and filtering

List endpoints return one page at a time, and let clients sort, filter and pick fields through the query string. FloCMS checks every one of those values against lists you allow, so client input never becomes SQL.

## Pages

```php
use FloCMS\Api\Pagination\PageRequest;
use FloCMS\Api\ResponseFactory;
use FloCMS\Core\App;

public function index(Request $request): Response
{
    $page = PageRequest::fromRequest($request, defaultPerPage: 20, maxPerPage: 100);

    $result = App::db()->table('posts')
        ->where('status', '=', 'published')
        ->orderBy('created_at', 'DESC')
        ->paginate($page->page, $page->perPage);

    return ResponseFactory::paginated($result, $request, PostResource::class);
}
```

`GET /api/v1/posts?page=2&per_page=20` answers:

```json
{
  "success": true,
  "data": [ ... ],
  "meta": {"page": 2, "per_page": 20, "total": 95, "last_page": 5},
  "links": {
    "first": "/api/v1/posts?page=1&per_page=20",
    "prev": "/api/v1/posts?page=1&per_page=20",
    "next": "/api/v1/posts?page=3&per_page=20",
    "last": "/api/v1/posts?page=5&per_page=20"
  }
}
```

- `page` and `per_page` must be positive integers, or the request is a 422.
- `per_page` above the maximum is lowered to it.
- Links keep the rest of the query string, and are relative to the host. `prev` and `next` are `null` on the first and last page.
- The third argument of `paginated()` shapes each row: a resource class, a function, or `null` for the rows as they are.

### Cursor pagination

For long or fast-changing lists, page by a cursor instead of a page number. New rows don't shift the pages, and deep pages stay fast:

```php
use FloCMS\Api\Pagination\CursorPaginator;

$result = CursorPaginator::paginate(
    App::db()->table('messages')->where('status', '=', 'new'),
    $request,
    column: 'id',
    direction: 'desc',
);

return ResponseFactory::cursorPaginated($result, $request, MessageResource::class);
```

```json
{"success": true, "data": [ ... ],
 "meta": {"per_page": 20, "next_cursor": "eyJ2Ijo0MX0", "has_more": true},
 "links": {"next": "/api/v1/messages?cursor=eyJ2Ijo0MX0"}}
```

The client follows `links.next` until it's `null`. The cursor column must be unique and never `null`, normally the primary key, and must be among the selected columns.

## Sorting, filtering and fields

`QueryOptions` reads `sort`, `filter` and `fields` from the query string:

```php
use FloCMS\Api\Query\QueryOptions;

$options = QueryOptions::fromRequest(
    $request,
    sortable:    ['price', 'created_at'],
    filterable:  ['status' => ['eq', 'in'], 'price' => ['gte', 'lte'], 'city'],
    fields:      ['id', 'title', 'price', 'status'],
    defaultSort: '-created_at',
);

$result = $options->applyTo(App::db()->table('listings'))->paginate($page->page, $page->perPage);
```

```text
GET /api/v1/listings?sort=-price&filter[status][in]=sale,rent&filter[price][gte]=100000&fields=id,title
```

| Parameter | Format |
|---|---|
| `sort` | Comma-separated names; `-` in front sorts descending. Up to 5. |
| `filter[name]=value` | Equals |
| `filter[name][op]=value` | With an operator: `eq`, `ne`, `gt`, `gte`, `lt`, `lte`, or `in` with comma-separated values (up to 50) |
| `fields` | Comma-separated names of the columns to return |

A filter listed without operators, like `'city'` above, allows `eq`. Any name or operator you didn't allow is a 422 that lists the allowed ones. Values are always bound parameters.

When the public name differs from the column, map it: `sortable: ['price' => 'l.price']`.

The skeleton's `UsersController` uses both:

```text
GET /api/v1/users?page=2&per_page=50&sort=-id&filter[role]=2&filter[status][in]=1,2
```

## HTTP caching

API responses are sent with `Cache-Control: no-store` by default. For data that can be cached, set a policy on the route:

```php
use FloCMS\Api\Middleware\{CacheControl, ETagMiddleware};

$router->get('/pages', [PagesController::class, 'index'])
    ->middleware(CacheControl::public(300), new ETagMiddleware());
```

| Policy | Cache-Control |
|---|---|
| `CacheControl::public($maxAge, $sharedMaxAge)` | Any cache may keep it for `$maxAge` seconds |
| `CacheControl::private($maxAge)` | Only the client's own cache |
| `CacheControl::noCache()` | Cache, but check with the server every time |
| `CacheControl::noStore()` | Never cache |

Policies apply to successful GET and HEAD responses. Authenticated requests are never cached publicly.

`ETagMiddleware` adds an `ETag` header and answers `304 Not Modified` when the client's `If-None-Match` matches, so unchanged data isn't sent twice. When the handler sets a modification date, `If-Modified-Since` works too:

```php
return ResponseFactory::withLastModified($response, $post->updated_at);
```
