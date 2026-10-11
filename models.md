# Models

A model holds the database code for one part of your site: finding posts, saving a user, counting orders. Controllers call the model, and the model talks to the database, so SQL stays out of your controllers and views.

## Creating a model

```bash
php flo make:model Post
```

creates `models/PostModel.php`. Add `--table=blog_posts` to choose the table name, and `--migration` to create a migration for the table as well. If `controllers/PostController.php` exists, the command also adds a `model()` accessor to it.

```php
<?php
declare(strict_types=1);

namespace FloCMS\Models;

use FloCMS\Core\Model;

class PostModel extends Model
{
    protected string $table = 'posts';

    /**
     * @return list<object>
     */
    public function all(): array
    {
        return $this->db->table($this->table)->get();
    }

    public function find(int $id): ?object
    {
        return $this->db->table($this->table)->where('id', '=', $id)->first();
    }
}
```

Models live in `models/` with the namespace `FloCMS\Models`, and extend `FloCMS\Core\Model`.

## Using a model in a controller

Create the model the first time an action needs it:

```php
use FloCMS\Models\PostModel;

class PostController extends Controller
{
    protected function model(): PostModel
    {
        return $this->model ??= new PostModel();
    }

    public function show(): void
    {
        $post = $this->model()->find((int) ($this->params[0] ?? 0));

        if ($post === null) {
            throw new \FloCMS\Core\HttpException(404);
        }

        $this->data['post'] = $post;
    }
}
```

Generated controllers use this pattern. Creating the model inside `model()`, instead of the constructor, means actions that don't need the database keep working when it is down or not set up yet.

## The database connection

`$this->db` is the shared database connection, a `FloCMS\Core\Database`. It connects the first time you use it, not when the model is created, so creating a model never touches the database. Every model and every query in a request share the same connection.

When the database can't be used, the first access throws:

| Exception | When |
|---|---|
| `DatabaseNotConfiguredException` | `DB_NAME` or `DB_USERNAME` is empty in `.env` |
| `DatabaseConnectionException` | The server can't be reached, refuses the login, or the database doesn't exist |

You don't have to catch them. The error handler shows the matching database error page. See [database](/docs/{{version}}/database).

## Writing queries

Build queries with the query builder. `table()` starts a new query, and the methods chain:

```php
public function published(int $limit = 10): array
{
    return $this->db->table('posts')
        ->where('status', '=', 'published')
        ->orderBy('created_at', 'DESC')
        ->limit($limit)
        ->get();
}

public function create(array $data): int
{
    return $this->db->table('posts')->insert([
        'title'      => $data['title'],
        'body'       => $data['body'],
        'created_at' => new \DateTimeImmutable(),
    ]);
}
```

`get()` returns a list of objects, `first()` one object or `null`, and `insert()` the new row's ID. Values are always sent as bound parameters, never pasted into the SQL. The [query builder](/docs/{{version}}/queries) page covers conditions, joins, grouping, updates and deletes.

## Raw SQL

For SQL the builder can't express, `query()` runs a prepared statement and returns a `PDOStatement`:

```php
$rows = $this->db->query(
    'SELECT category, COUNT(*) AS total FROM posts WHERE status = ? GROUP BY category',
    ['published']
)->fetchAll();
```

Use `?` or named placeholders (`:status`) for every value. Rows from `query()` are associative arrays, unlike `get()`, which returns objects.

> [!WARNING]
> Never build SQL by joining strings with values from a request. Pass them as parameters, so the database treats them as data.

## Transactions

`transaction()` runs a function inside a transaction. When the function returns, the transaction is committed; when it throws, everything is rolled back and the exception continues:

```php
$orderId = $this->db->transaction(function ($db) use ($cart) {
    $id = $db->table('orders')->insert(['total' => $cart->total()]);

    foreach ($cart->items() as $item) {
        $db->table('order_items')->insert(['order_id' => $id, 'product_id' => $item->id]);
    }

    return $id;
});
```

The function's return value is returned by `transaction()`. Transactions can be nested; the inner ones use savepoints.

## Pagination

`paginate()` runs a query for one page and counts all matching rows:

```php
$result = $this->db->table('posts')
    ->where('status', '=', 'published')
    ->orderBy('created_at', 'DESC')
    ->paginate($page, 20);

$result['data'];        // the rows of this page
$result['pagination'];  // page numbers for your links
```

`pagination` holds `items_count`, `total_pages`, `cur_page`, `per_page`, `next_page`, `prev_page` (0 when there is none), `has_next`, `has_prev`, `last_page` and `last_pages`. When you count rows yourself, `$this->pagingArray($page, $perPage, $total)` returns the same array.
