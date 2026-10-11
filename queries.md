# Query builder

The query builder writes SQL for you. You chain methods that describe the query, and every value you pass is sent as a bound parameter, so it can't break out of the query.

## Starting a query

In a model, `$this->db` is the connection. Anywhere else, use `App::db()`:

```php
use FloCMS\Core\App;

$posts = App::db()->table('posts')
    ->where('status', '=', 'published')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();
```

`App::db()` returns `null` when the database isn't configured in `.env`.

`table()` starts a new, independent query each time it's called, so you can build two queries at once:

```php
$posts = $db->table('posts')->where('author_id', '=', $id);
$count = $db->table('comments')->where('author_id', '=', $id)->count();
$rows  = $posts->get();
```

## Getting results

| Method | Returns |
|---|---|
| `get()` | A list of rows, each an object: `$post->title` |
| `first()` | The first row as an object, or `null` |
| `count()`, `count('column')` | The number of matching rows |
| `paginate($page, $perPage)` | One page of rows and the page numbers. See [models](/docs/{{version}}/models#pagination). |
| `toSql()` | `[$sql, $bindings]` without running anything, handy for debugging |

## Choosing columns

```php
$db->table('users')->select('id, full_name AS name, email')->get();
$db->table('users')->select(['id', 'email'])->distinct()->get();
```

Column names may contain letters, digits, `_` and a `.` for `table.column`, with an optional alias. Anything else throws an `InvalidArgumentException`, so a column name can never smuggle in SQL.

For an expression, use `selectRaw()` with `?` placeholders:

```php
$db->table('listings')
    ->select('id, title')
    ->selectRaw('ST_Distance_Sphere(location, POINT(?, ?)) AS meters', [$lng, $lat])
    ->get();
```

## Conditions

`where()` takes a column, an operator and a value. Conditions are joined with `AND`; `orWhere()` joins with `OR`:

```php
$db->table('posts')
    ->where('status', '=', 'published')
    ->where('views', '>=', 100)
    ->orWhere('featured', '=', true)
    ->get();
```

The operators are `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `IS` and `IS NOT`. Values are converted for the database: `true` and `false` become `1` and `0`, dates become `Y-m-d H:i:s`.

There are shortcuts for the common cases, each with an `or` version (`orWhereIn()`, `orWhereNull()` and so on):

| Method | SQL |
|---|---|
| `whereIn('id', [1, 2, 3])`, `whereNotIn(...)` | `id IN (?, ?, ?)`; the array must not be empty |
| `whereNull('deleted_at')`, `whereNotNull(...)` | `deleted_at IS NULL` |
| `whereBetween('price', [100, 500])`, `whereNotBetween(...)` | `price BETWEEN ? AND ?` |

### Grouping conditions

Pass a function to `where()` or `orWhere()` to put conditions in parentheses:

```php
// status = ? AND (title LIKE ? OR city LIKE ?)
$db->table('listings')
    ->where('status', '=', 'active')
    ->where(fn ($q) => $q->where('title', 'LIKE', "%{$term}%")
                         ->orWhere('city', 'LIKE', "%{$term}%"))
    ->get();
```

### Raw conditions

`whereRaw()` and `orWhereRaw()` accept SQL the builder can't write, such as full-text search:

```php
$db->table('posts')
    ->whereRaw('MATCH(title, body) AGAINST(? IN BOOLEAN MODE)', [$words])
    ->get();
```

Raw SQL must use `?` placeholders with exactly one binding each. Named placeholders and a wrong number of bindings are rejected with an `InvalidArgumentException`.

> [!WARNING]
> Never put request values into raw SQL by joining strings. A literal `?` inside a quoted string also counts as a placeholder, so bind such values too.

## Joins

```php
$db->table('posts p')
    ->select('p.id, p.title, u.full_name AS author')
    ->join('users u', 'u.id', '=', 'p.author_id')
    ->join('categories c', 'c.id', '=', 'p.category_id', 'LEFT')
    ->get();
```

`join($table, $left, $operator, $right, $type)` compares two columns. The type is `INNER` (the default), `LEFT`, `RIGHT` or `CROSS`.

## Grouping

```php
$db->table('orders')
    ->select('customer_id')
    ->selectRaw('SUM(total) AS spent')
    ->groupBy('customer_id')
    ->havingRaw('SUM(total) > ?', [1000])
    ->get();
```

`having()`, `orHaving()`, `havingIn()`, `havingNotIn()`, `havingNull()` and `havingNotNull()` work like their `where` versions.

## Ordering and limits

```php
$db->table('posts')
    ->orderBy('featured', 'DESC')
    ->orderBy('created_at')            // ASC by default
    ->limit(20)
    ->offset(40)
    ->get();
```

`offset()` only applies together with `limit()`. `orderByRaw()` takes an expression with optional bindings, for example `orderByRaw('meters ASC')`.

## Inserting, updating and deleting

```php
$id = $db->table('posts')->insert([
    'title'      => 'Hello',
    'created_at' => new DateTimeImmutable(),
]);

$db->table('posts')->where('id', '=', $id)->update(['title' => 'Hello again']);

$db->table('posts')->where('id', '=', $id)->delete();
```

- `insert()` returns the new row's ID.
- `update()` and `delete()` refuse to run without a `where()`, so a forgotten condition can't change or empty a whole table.
- Array values are stored as JSON.

## Raw queries and transactions

`query($sql, $params)` runs any prepared statement and returns a `PDOStatement`, and `transaction()` runs a function in a transaction. Both are described on the [models](/docs/{{version}}/models#raw-sql) page.
