# A reusable theme

Give the blog and contact pages shared navigation and a footer. This guide builds on [the blog page](/docs/{{version}}/example-blog) and [contact form](/docs/{{version}}/example-contact-form), keeping their controllers and page views.

## The two template locations

| Location | Holds |
|---|---|
| `views/blog/`, `views/contact/` | Page markup selected from the controller and action |
| `templates/demo/layouts/` | The surrounding HTML document |
| `templates/demo/partials/` | Reusable server-rendered markup |
| `public/themes/demo/` | CSS, images and JavaScript requested by the browser |

`APP_TEMPLATE=demo` selects the theme's layouts, partials and assets. It does not move conventional page views into `templates/demo/`.

## Create the shared header

**File: `templates/demo/partials/site-header.html`**

```html
<header class="site-header">
    <div class="container header-inner">
        <a class="site-name" href="{{ $blogUrl }}">{{ __('examples.site.name') }}</a>
        <nav aria-label="Main navigation">
            <a href="{{ $blogUrl }}">Blog</a>
            <a href="{{ $contactUrl }}">Contact</a>
        </nav>
    </div>
</header>
```

The partial expects `$blogUrl` and `$contactUrl`. It escapes these values inside quoted HTML attributes. The layout supplies application-generated URLs rather than visitor-provided URLs.

## Create the shared footer

**File: `templates/demo/partials/site-footer.html`**

```html
<footer class="site-footer">
    <div class="container">
        <p>&copy; {{ $year }} {{ __('examples.site.name') }}</p>
    </div>
</footer>
```

The year is supplied by the layout. It is not inherited from the controller or the calling template.

## Replace the demo layout

Replace the layout created in the blog guide with this complete file.

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
    <?= render_partial('site-header', [
        'blogUrl' => SITE_URI . '/' . ACTIVE_LANG_PATH . 'blog',
        'contactUrl' => SITE_URI . '/' . ACTIVE_LANG_PATH . 'contact',
    ]) ?>

    <main class="container">
        {!! $content !!}
    </main>

    <?= render_partial('site-footer', ['year' => date('Y')]) ?>
</body>
</html>
```

`SITE_URI` supplies the site's base URL, including a configured subdirectory. `ACTIVE_LANG_PATH` is empty for the default language and contains the active language prefix otherwise. See [localization](/docs/{{version}}/localization#languages-in-urls).

The layout receives only the rendered page content from the standard page dispatcher. Its header URLs, footer year and site title are computed independently. Passing explicit data to each partial makes its requirements clear.

> [!NOTE]
> FloCMS does not currently implement `@extends`, `@section`, `@yield` or `@include`. Conventional layouts wrap `$content`; `render_partial()` composes reusable markup. These files use only supported syntax.

## Add the theme styles

Append this block to the stylesheet from the previous guides:

```css
.site-header { background: #fff; border-bottom: 1px solid #dce2eb; }
.header-inner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; padding-block: 20px; }
.site-name { font-weight: 700; }
.site-header a { color: #214fc6; text-decoration: none; }
.site-header a:hover { text-decoration: underline; }
.site-header nav { display: flex; gap: 20px; }
.site-footer { margin-top: 32px; border-top: 1px solid #dce2eb; color: #526178; }
```

## Check theme fallback

FloCMS looks for a layout or partial in the active theme, then in `templates/default/`. If neither contains the requested file, rendering fails with a missing-template exception. It does not silently produce an empty partial.

To try this without affecting another theme:

1. Copy `templates/demo/partials/site-footer.html` to `templates/default/partials/site-footer.html`.
2. Temporarily rename the demo copy to `site-footer.html.bak`.
3. Reload `/blog`. The footer still renders from the default theme.
4. Restore the demo file and remove the temporary default copy.

The application resolves a partial's path before rendering it, so fallback chooses the file; compilation then caches that chosen file.

Assets also use an active-theme/default-theme lookup. Unlike a missing partial, an asset missing from both themes still produces a default-theme URL; the browser may receive a 404. Check the browser's Network panel when CSS or an image does not load.

## Compiled templates

The page, layout and rendered partials all use the same compiled-template cache. Keep `views/cache/` writable by the PHP process.

For this example's default cache location:

```bash
php flo view:clear
php flo optimize
```

The first command removes compiled templates; the second warms `.html` files in `views/` and `templates/`. They do not cache rendered page output: a different visitor's data is still evaluated on each request.

Compilation is not a PHP syntax check or a full rendering test. Open both pages after a template change.

> [!IMPORTANT]
> Core supports a custom `view.cache_path`, but the CLI 2.0.1 cache commands target `views/cache/`. If your application uses another cache directory, these commands do not clear or warm that directory. This example keeps the default path.

## Expected result

Open `/blog` and `/contact`. Both pages now share:

- The site name and Blog/Contact navigation.
- The same stylesheet and outer layout.
- A footer displaying the current year.

The blog still displays its post cards and empty state, and the contact form still displays its own title, inputs and errors. Neither controller needs to know how the shared header or footer is rendered.

See [views and templates](/docs/{{version}}/views) for the syntax reference and [helpers](/docs/{{version}}/helpers#theme-assets-and-partials) for path and rendering helpers.

