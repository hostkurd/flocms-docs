# Routing

FloCMS routes pages by convention: the URL names the controller and the action, so most pages need no route definitions at all.

## How a URL is read

```text
/{language}/{area}/{controller}/{action}/{parameters...}
```

Every part is optional. Some examples:

| URL | Runs |
|---|---|
| `/` | `PagesController::index()` |
| `/blog` | `BlogController::index()` |
| `/blog/show/5` | `BlogController::show()` with parameter `5` |
| `/ar/blog/show/5` | The same, in Arabic |
| `/blog/show` | `BlogController::show()` with no parameters |
| `/admin/blog/edit/5` | `BlogController::admin_edit()`, in the admin panel |
| `/blog_post` | `BlogPostController::index()` |

- **Language.** A first segment listed in `LANGUAGES` sets the language. Without it, `DEFAULT_LANG` is used. See [localization](/docs/{{version}}/localization).
- **Area.** A segment named in the `routes` setting picks the area and its method prefix (see below).
- **Controller.** Defaults to `pages`. Underscores separate words: `blog_post` runs `BlogPostController`, and its views live in `views/blog_post/`.
- **Action.** Defaults to `index`. Controller and action names use letters, digits and `_`; anything else, including a hyphen, answers 404.
- **Parameters.** Everything after the action, decoded, in `$this->params`.

## Areas and method prefixes

`config/config.php` maps each area to a method prefix:

```php
$routes = array(
    'default' => '',
    'admin'   => 'admin_',
);
```

Under `/admin`, FloCMS calls `admin_` methods and uses the `admin` layout, and only signed-in users with admin access get in. So one controller serves both the site and its admin pages:

```php
class BlogController extends Controller
{
    public function index(): void { /* /blog */ }

    public function admin_index(): void { /* /admin/blog */ }
}
```

Each area uses the layout of the same name, `templates/<template>/layouts/<area>.html`. The skeleton also registers a `login` area with the `login_` prefix; a template needs a `layouts/login.html` file before that area can be used.

## Parameters

Parameters arrive as strings, in order:

```php
// /blog/show/5/comments
public function show(): void
{
    [$id, $tab] = $this->params + [null, 'details'];
    // $id === '5', $tab === 'comments'
}
```

Parameters come straight from the URL. Validate them before using them in a query or a file path.

## Which methods are reachable

Only public, non-static methods declared on your controller, or on your own base controller, can be called from a URL. Inherited `Controller` methods, protected and private methods, and names starting with `__` answer 404. Keep helper methods `protected` or `private`.

> [!NOTE]
> This rule arrived in flocms-core 2.2.1 as a security fix. Before it, a URL could reach methods inherited from the base controller.

## Redirects

```php
use FloCMS\Core\Router;

Router::redirect(SITE_URI . '/blog');
```

`redirect()` sends a 302 and stops the request.

## API routes

JSON endpoints don't use the convention routing. They are declared explicitly in `api/routes.php` and served under `/api/v1`. See [building an API](/docs/{{version}}/api).

## Listing routes

```bash
php flo route:list
```

shows every convention route of `controllers/`, with the permission each admin action needs, and the `/api/v1` routes in one table.
