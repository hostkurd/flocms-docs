# Controllers

A controller groups the actions for one part of your site. Each public method is an action that answers a URL.

## Creating a controller

```bash
php flo make:controller Blog
```

creates `controllers/BlogController.php`. Add `--admin` for an `admin_index()` action with a permission check, `--resource` for public and admin actions (`index`, `view`, `admin_index`, `admin_add`, `admin_edit`, `admin_delete`), and `--model` to create the model too. `php flo make:route Blog` creates the controller, a model and a view in one go. See [generators](/docs/{{version}}/generators).

```php
<?php
namespace FloCMS\Controllers;

use FloCMS\Core\Controller;
use FloCMS\Models\BlogModel;

class BlogController extends Controller
{
    protected function model(): BlogModel
    {
        return $this->model ??= new BlogModel();
    }

    public function index(): void
    {
        $this->data['title'] = 'Blog';
        $this->data['posts'] = $this->model()->latest();
    }
}
```

## Passing data to the view

Everything you put in `$this->data` becomes a variable in the view. After `index()` runs, FloCMS renders `views/blog/index.html`, where `$title` and `$posts` are available.

To render a different view, return its path:

```php
public function archive(): string
{
    $this->data['posts'] = $this->model()->archived();

    return VIEWS_PATH . DS . 'blog' . DS . 'index.html';
}
```

## The request

`$this->request` gives you the current request:

```php
if ($this->request->isMethod('POST')) {
    $email = $this->request->input('email');
}

$page = $this->request->queryValue('page', 1);
$file = $this->request->file('photo');
```

URL parameters are in `$this->params`. See [routing](/docs/{{version}}/routing#parameters). [Requests and validation](/docs/{{version}}/validation) lists every request method and shows how to check input.

> [!IMPORTANT]
> Every POST, PUT, PATCH and DELETE request must carry a CSRF token, or FloCMS answers 419. Put `@csrf` inside your forms. See [sessions and CSRF](/docs/{{version}}/session#csrf-protection).

## Models are created on first use

The `model()` accessor above creates the model the first time it's called. See [models](/docs/{{version}}/models). Pages that never query the database, like a contact page, keep working when the database is down or not set up yet. Generated controllers use this pattern.

## Admin actions

Actions prefixed with `admin_` answer under `/admin` and render inside the admin layout:

```php
public function admin_index(): void   // /admin/blog
{
    $this->data['posts'] = $this->model()->all();
}
```

Only signed-in users whose role is in `admin_access_roles` reach them. Decide what each action requires with `$actionPermissions`:

```php
protected array $actionPermissions = [
    '*'            => 'content.edit',   // every action not listed
    'admin_delete' => 'content.delete',
    'index'        => null,             // no check
];
```

The `*` entry covers every action of the controller, public pages included, so give public actions `null`. See [roles and permissions](/docs/{{version}}/permissions).

## Flash messages

Show a one-time message on the next page:

```php
use FloCMS\Core\Session;
use FloCMS\Core\Router;

Session::setFlash('Post saved.', 'success');
Router::redirect(SITE_URI . '/admin/blog');
```

In the view, `Session::hasFlash()`, `Session::flash()` and `Session::flashType()` print it. See [sessions](/docs/{{version}}/session#flash-messages).

## Not found and forbidden

Throw an `HttpException` to stop the action and show an error page:

```php
use FloCMS\Core\HttpException;

throw new HttpException(404);
```

See [error pages and logging](/docs/{{version}}/errors).

## Helper methods

Every public method declared on your controller can be called from a URL. Make helper methods `protected` or `private`, so they can't be:

```php
protected function findOrFail(int $id): object
{
    return $this->model()->find($id) ?? throw new HttpException(404);
}
```
