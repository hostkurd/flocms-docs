# Views and templates

Views hold your page markup. The Flo Template Engine adds a short syntax for printing values, conditions, loops and forms, and plain PHP works too.

## Views and layouts

The view for `BlogController::index()` is `views/blog/index.html`. FloCMS renders it, then places the result inside the active layout:

- `templates/<template>/layouts/default.html` for site pages
- `templates/<template>/layouts/admin.html` for `/admin` pages

In the layout, `<?= $data['content'] ?>` prints the rendered view. `APP_TEMPLATE` in `.env` picks the template; files missing from it come from `templates/default/`.

## Printing values

```html
<h1>{{ $title }}</h1>
<p>{{ $post['author'] }}</p>
```

`{{ }}` escapes HTML, so it is safe for any value. To print trusted HTML as it is, use `{!! $html !!}`. In plain PHP, `e($value)` escapes a value the same way.

> [!WARNING]
> Never use `{!! !!}` for anything a visitor typed. It prints raw HTML, which allows cross-site scripting.

## Conditions and loops

```html
@if($posts)
    @foreach($posts as $post)
        <article>{{ $post['title'] }}</article>
    @endforeach
@else
    <p>No posts yet.</p>
@endif
```

| Syntax | Meaning |
|---|---|
| `@if(...)`, `@elseif(...)`, `@else`, `@endif` | Conditions |
| `@foreach(...)`, `@endforeach` | Loop over an array |
| `@for(...)`, `@endfor`, `@while(...)`, `@endwhile` | Other loops |
| `@forelse($items)` ... `@empty` ... `@endforelse` | Loop with a fallback when the array is empty (items are `$key` and `$value`) |
| `@php` ... `@endphp` | A block of plain PHP |
| `@lang('key')` | A translation from `lang/<language>.php` |
| `@config('key')`, `@env('KEY')` | A config or `.env` value |
| `@csrf` | The CSRF field for a form |

> [!IMPORTANT]
> The expression inside `@if()`, `@elseif()`, `@foreach()`, `@for()`, `@while()` and `@forelse()` ends at the first `)`, so it can't contain parentheses of its own. `@if(count($posts) > 0)` doesn't work. Compute the value first, or use plain PHP for that line:
>
> ```html
> <?php if (count($posts) > 0): ?> ... <?php endif; ?>
> ```

The engine replaces these words anywhere in a view or layout, also inside HTML comments and JavaScript. To show a literal `@` before one of them, write it as `&#64;`.

## Forms

Every form that posts data needs a CSRF token. `@csrf` prints the hidden field:

```html
<form method="post" action="">
    @csrf
    <input type="email" name="email" required>
    <button type="submit">Subscribe</button>
</form>
```

For JavaScript requests, the admin layout includes a `csrf-token` meta tag and `js/csrf.js`, which sends the token as the `X-CSRF-TOKEN` header with fetch and jQuery requests. See [sessions and CSRF](/docs/{{version}}/session#csrf-protection).

## Partials

Split repeated markup into partials in `templates/<template>/partials/` and render them with data:

```php
<?= render_partial('header.html', ['title' => $title]) ?>
```

Partials use the same template syntax as views.

## Assets

Link to CSS, JavaScript and images in the active theme with `template_asset()`. It falls back to the `default` theme when the file isn't in yours:

```html
<link rel="stylesheet" href="<?= template_asset('css/style.css') ?>">
```

## Translations

`@lang('key')` prints the text for the current language from `lang/<language>.php`, and `__('key')` returns it in PHP. See [localization](/docs/{{version}}/localization) for placeholders, right-to-left languages and adding languages.

## Compiled templates

Templates are compiled to PHP in `views/cache/` the first time they're used, and again whenever they change. The web server must be able to write to that folder. `php flo optimize` compiles everything ahead of time, which is handy after a deployment, and `php flo view:clear` empties the cache.

`Config::set('view.cache_path', ...)` moves the cache to another folder. When the folder can't be written, templates still work: they are compiled on every request instead, which is slower.
