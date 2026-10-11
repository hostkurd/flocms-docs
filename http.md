# Requests and responses

`FloCMS\Core\Http\Request` and `Response` are the HTTP classes shared by core and the API. Page controllers receive a request as `$this->request`; API handlers receive it as an argument. See [validation](/docs/{{version}}/validation) for checking input.

## Creating a request

```php
use FloCMS\Core\Http\Request;

$request = Request::fromGlobals();
```

This captures `$_GET`, `$_POST`, `$_SERVER`, `$_FILES`, `$_COOKIE` and the raw body. `fromGlobals($rawBody)` lets you supply the body yourself. For API tests, prefer [TestClient](/docs/{{version}}/api-testing).

## Input and JSON

| Method | Returns |
|---|---|
| `input($key, $default = null)` | A value from the merged input |
| `all()` | Query parameters, then form fields, then JSON, with later values replacing earlier ones |
| `query()` | Only query parameters |
| `queryValue($key, $default = null)` | One query parameter |
| `rawBody()` | The unparsed body string |
| `json()` | The decoded JSON array, or `[]` for empty, malformed or scalar JSON |
| `jsonError()` | The decoding exception for malformed JSON, otherwise `null` |

JSON participates in `all()` only for `application/json` or a content type ending in `+json`. Input is not validated or HTML-escaped automatically.

```php
$title = $request->input('title', '');
$page = $request->queryValue('page', 1);

if ($request->jsonError() !== null) {
    // Reject the malformed body; json() alone would return [].
}
```

The API's `JsonBodyMiddleware` rejects malformed JSON before your handler runs. See [middleware](/docs/{{version}}/api-middleware).

## Method, URL and headers

| Method | Returns |
|---|---|
| `method()` | Uppercase method, such as `GET` |
| `isMethod('POST', 'PATCH')` | Whether the method matches any supplied method, ignoring case |
| `isStateChanging()` | Whether it is POST, PUT, PATCH or DELETE |
| `uri()` | The request URI, including its query string |
| `path()` | Normalized path without the query; repeated slashes are collapsed |
| `header($name, $default = null)` | A header, looked up without regard to case |
| `headers()` | The header map, with lowercase keys |
| `contentType()` | Lowercase media type without parameters such as `charset` |
| `acceptsJson()` | Whether `Accept` contains `application/json` or `+json` |
| `server($key, $default = null)` | A value from the captured server array |
| `bearerToken()` | A Bearer token from `Authorization` or `X-FloCMS-Authorization`, otherwise `null` |

`acceptsJson()` is a simple media-type check, not full HTTP content negotiation. `bearerToken()` extracts a credential; authenticators verify it. See [API authentication](/docs/{{version}}/api-authentication).

## Files and cookies

```php
$file = $request->file('attachment');   // $_FILES entry, or null
$files = $request->files();
$theme = $request->cookie('theme', 'light');
$cookies = $request->cookies();
```

The request is a snapshot. Later changes made by `Cookie::set()` do not update this request's captured cookies. See [cookies](/docs/{{version}}/session#cookies) to write them and [uploads](/docs/{{version}}/uploader) to process files.

## Route parameters and attributes

API route parameters and middleware attributes are separate from input:

```php
$id = $request->route('id');
$params = $request->routeParams();
$identity = $request->attribute('identity');
```

`route($name, $default = null)` returns a string or `null`; `attribute($name, $default = null)` returns the stored value. `attributes()` returns all attributes.

`withAttribute($name, $value)` and `withRouteParams($params)` return a cloned request. Pass the returned object onward:

```php
$nextRequest = $request->withAttribute('client_version', '2');
// $request is unchanged; the middleware's next handler receives $nextRequest.
```

Page routing also exposes positional parameters through the page controller's `$params`; those are not API named route parameters. See [routing](/docs/{{version}}/routing).

## Creating responses

```php
use FloCMS\Core\Http\Response;

$response = Response::json(['status' => 'ok']);
$response->header('X-Example', 'yes');
```

| Factory | Default status and content type |
|---|---|
| `make($body = '', $status = 200)` | 200; no content type added |
| `html($html, $status = 200)` | 200; `text/html; charset=utf-8` |
| `text($text, $status = 200)` | 200; `text/plain; charset=utf-8` |
| `json($data, $status = 200)` | 200; `application/json; charset=utf-8` |
| `noContent($status = 204)` | 204; empty body |

`Response::json()` encodes exactly the data you supply. It does not add the API's `success` envelope. For that envelope use `FloCMS\Api\ResponseFactory` or the [API controller helpers](/docs/{{version}}/api-controllers#controller-helpers).

```php
use FloCMS\Api\ResponseFactory;

$plain = Response::json(['status' => 'ok']);
// {"status":"ok"}

$api = ResponseFactory::success(['status' => 'ok']);
// {"success":true,"data":{"status":"ok"}}
```

## Headers and sending

| Method | Does |
|---|---|
| `header($name, $value)` | Sets/replaces a header, case-insensitively, and returns this response |
| `appendHeader($name, $value)` | Appends to the header using a comma separator |
| `status()`, `body()`, `headers()` | Inspect the response |
| `withoutBody()` | Returns a clone with an empty body |
| `send()` | Sends status, headers and body; it does not exit |

Header setters mutate the response. Header names are validated and values cannot contain line breaks. `appendHeader()` is for comma-separated headers, not multiple `Set-Cookie` values; use the cookie API for cookies.

`send()` omits the body for status 204 or 304. Invalid status codes and JSON encoding failures throw `RuntimeException`.

API handlers return a response and the kernel sends it. The page runtime renders views; it does not automatically send an action's returned `Response`. To output a response from a page action, send it and stop before view rendering:

```php
public function status(): void
{
    \FloCMS\Core\Http\Response::text('ok')->send();
    exit;
}
```
