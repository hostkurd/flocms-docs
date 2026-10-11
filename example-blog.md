# A complete blog page

Create a page at `/blog` that lists two sample posts, uses a reusable post-card partial, and displays an empty state. This example runs without a database.

Follow the [example setup](/docs/{{version}}/examples#before-you-start) first. Use Core 2.2.2 or newer and `APP_TEMPLATE=demo`.

## Files to create

| File | Purpose |
|---|---|
| `controllers/BlogController.php` | Prepare the page's title and sample objects |
| `views/blog/index.html` | Display the listing or the empty state |
| `templates/demo/partials/post-card.html` | Render one post |
| `templates/demo/layouts/default.html` | Wrap the page in a complete HTML document |
| `public/themes/demo/css/site.css` | Style the theme |
| `lang/en.php` | Add the example's translation keys to the existing language file |

Create any missing parent directories. Keep the project's existing `templates/default/` files for theme fallback.

## Prepare the data

**File: `controllers/BlogController.php`**

```php
<?php
namespace FloCMS\Controllers;

use FloCMS\Core\Controller;

class BlogController extends Controller
{
    public function index(): void
    {
        $this->data['title'] = __('examples.blog.heading');
        $this->data['posts'] = [
            (object) [
                'title' => 'Getting started with FloCMS',
                'excerpt' => 'Build your first page with a controller and a view.',
                'author' => 'Sara',
            ],
            (object) [
                'title' => 'Views & templates',
                'excerpt' => 'Reuse markup with layouts and partials.',
                'author' => 'Ahmed',
            ],
        ];

        // A convenient way to exercise the empty state.
        if ($this->request->queryValue('empty') === '1') {
            $this->data['posts'] = [];
        }
    }
}
```

FloCMS maps `/blog` to `BlogController::index()` and selects `views/blog/index.html`. The keys in `$this->data` become `$title` and `$posts` in that view. You do not need a database model to prepare template data.

These records are objects, matching the shape returned by the core query builder's `get()`. Use `$post->title` for an object and `$post['title']` for an array; they are different data shapes.

## Add the translations

Merge these keys into the array returned by the existing `lang/en.php`; keep its other entries. The block shows a complete minimal language file for the demo.

**File: `lang/en.php`**

```php
<?php
return [
    'lng.dir' => 'ltr',
    'examples.site.name' => 'FloCMS Examples',
    'examples.blog.heading' => 'Latest posts',
    'examples.blog.empty' => 'No posts yet.',
    'examples.blog.author' => 'By :name',
];
```

Language files use flat keys. The translation helper `__()` returns a string and replaces `:name` from the supplied array.

## Render the listing

**File: `views/blog/index.html`**

```html
<section aria-labelledby="blog-title">
    <h1 id="blog-title">{{ $title }}</h1>

    <div class="post-list">
        @forelse($posts)
            <?= render_partial('post-card', ['post' => $value]) ?>
        @empty
            <p class="empty-state">{{ __('examples.blog.empty') }}</p>
        @endforelse
    </div>
</section>
```

FloCMS's `@forelse($posts)` uses `$key` and `$value` inside the loop. It does not use Blade's `@forelse($posts as $post)` syntax. This example passes the current object as `post` to the partial.

Partials do not automatically inherit the calling view's variables. Pass every value they need in the second argument of `render_partial()`. The helper returns rendered markup, so print its result without HTML-escaping that markup.

## Create the post card

**File: `templates/demo/partials/post-card.html`**

```html
<article class="post-card">
    <h2>{{ $post->title }}</h2>
    <p>{{ $post->excerpt }}</p>
    <p class="post-author">{{ __('examples.blog.author', ['name' => $post->author]) }}</p>
</article>
```

`render_partial('post-card', ...)` finds `post-card.html` under the active theme's `partials/` folder. The `.html` extension is optional in the helper call.

The title, excerpt and translated author line are escaped for HTML text. Even when an author's name contains HTML, it remains text inside the card. Translation replacement alone does not escape it; the surrounding `{{ ... }}` does.

## Create the layout

**File: `templates/demo/layouts/default.html`**

```html
<!doctype html>
<html lang="{{ ACTIVE_LANG }}" dir="{{ __('lng.dir') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('examples.site.name') }}</title>
    <link rel="stylesheet" href="{{ template_asset('css/site.css') }}">
</head>
<body>
    <main class="container">
        {!! $content !!}
    </main>
</body>
</html>
```

FloCMS renders the page view first, then renders this layout with its output in `$content` and `$data['content']`. Raw output is appropriate here because `$content` is markup rendered from the application's view.

> [!IMPORTANT]
> The layout does not automatically receive the controller's `$title` or `$posts`. This layout uses a site-wide translated title and prints the controller's title inside the page view. Do not assume that every controller variable is available in layouts.

## Add the stylesheet

**File: `public/themes/demo/css/site.css`**

```css
:root {
    color-scheme: light;
    font-family: system-ui, sans-serif;
    color: #182335;
    background: #f4f6fa;
}

body { margin: 0; }
.container { max-width: 960px; margin: 0 auto; padding: 32px 20px; }
.post-list { display: grid; gap: 16px; }
.post-card { padding: 24px; background: #fff; border: 1px solid #dce2eb; border-radius: 12px; }
.post-card h2 { margin-top: 0; }
.post-author, .empty-state { color: #526178; }
```

`template_asset('css/site.css')` points to `/themes/demo/css/site.css` beneath the site's base URL. It uses `public/themes/` for browser assets; `templates/` holds server-rendered files.

## Run the example

```bash
php flo serve
```

Open `/blog` beneath the address printed by the command. You should see:

- A page heading of **Latest posts**.
- Two styled cards, with **By Sara** and **By Ahmed**.
- A browser-tab title of **FloCMS Examples**.
- The second card's title displayed as **Views & templates**.

Inspecting the rendered HTML shows `Views &amp; templates`, because the title was escaped.

Open `/blog?empty=1`. The heading stays visible, the cards disappear, and **No posts yet.** appears.

To check escaping, temporarily put `<strong>Sara</strong>` in the sample author's name. The card should display those tags as text rather than making the name bold.

## Moving to real records

Keep the view, partial and layout. Replace the controller's sample array with records from your model. Make each returned object expose `title`, `excerpt` and `author`, or update the partial to match your model's fields. A database query may return a different field name, such as `author_name`; map it explicitly.

Configure MySQL/MariaDB, create the table through a migration, and add sample rows before making that change. See [models](/docs/{{version}}/models), [migrations](/docs/{{version}}/migrations) and the [query builder](/docs/{{version}}/queries). This guide's sample objects are demonstration data, not persistent posts.

Continue with [a validated contact form](/docs/{{version}}/example-contact-form).

