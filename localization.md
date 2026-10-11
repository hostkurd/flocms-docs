# Localization

FloCMS sites can speak several languages. New projects come with English, Arabic and Kurdish, including right-to-left support for Arabic and Kurdish.

## Enabling languages

Languages are set in `.env`:

```ini
LANGUAGES=en,ar,ku
DEFAULT_LANG=en
```

Each code needs a file in `lang/`, such as `lang/ar.php`. Use lowercase codes, because the language is read from the URL in lowercase.

## Languages in URLs

The first segment of a URL picks the language when it's one of `LANGUAGES`:

| URL | Language |
|---|---|
| `/blog` | The default language |
| `/ar/blog` | Arabic |
| `/ku/admin/posts` | Kurdish, in the admin panel |

Everything after the language works as usual. See [routing](/docs/{{version}}/routing).

These constants describe the active language. They're defined on every page request:

| Constant | Example (Arabic) | Use |
|---|---|---|
| `ACTIVE_LANG` | `ar` | The active language code |
| `ACTIVE_LANG_PATH` | `ar/` | The URL prefix; empty for the default language |
| `LANG_SUFFIX` | `_ar` | A column suffix such as `title_ar`; empty for English |

Build links that keep the visitor's language with `ACTIVE_LANG_PATH`:

```php
<a href="<?= SITE_URI . '/' . ACTIVE_LANG_PATH ?>blog">@lang('nav.blog')</a>
```

For a language switcher, `App::getRouter()->changeLang('ar')` returns the path of the current page in another language:

```php
<?php $router = \FloCMS\Core\App::getRouter(); ?>
<a href="<?= SITE_URI . $router->changeLang('en') ?>">English</a>
<a href="<?= SITE_URI . $router->changeLang('ar') ?>">العربية</a>
```

## Translation files

A language file returns an array of keys and texts:

```php
<?php
// lang/en.php
return array(
    'lng.dir' => 'ltr',

    'nav.blog'      => 'Blog',
    'auth.welcome'  => 'Welcome back, :name!',
);
```

Keys are looked up in lowercase, so write them in lowercase. If the file of the active language is missing, `lang/en.php` is used.

## Printing translations

In a view, `@lang('key')` prints a translation:

```html
<h1>@lang('website.title')</h1>
```

In PHP, `__('key')` returns it:

```php
$this->data['title'] = __('nav.blog');
```

A missing key returns the key itself, so you can spot it on the page. Pass a third argument for a different fallback: `__('nav.blog', [], 'Blog')`.

### Placeholders

Words starting with `:` are replaced from an array:

```php
__('auth.welcome', ['name' => $user['fullname']]);   // Welcome back, Sara!
```

`:Name` gives the value with a capital first letter, and `:NAME` in capitals.

> [!WARNING]
> `@lang()` and `__()` don't escape HTML, so translations may contain markup. When a placeholder holds something a visitor typed, print the result escaped: `{{ __('auth.welcome', ['name' => $name]) }}`.

## Right-to-left languages

A language is right-to-left when its file has `'lng.dir' => 'rtl'`. `Lang::isRTL()` tells you, and the default layout uses it to load an extra stylesheet:

```php
<?php if (\FloCMS\Core\Lang::isRTL()): ?>
    <link rel="stylesheet" href="<?= template_asset('css/style.rtl.css') ?>">
<?php endif; ?>
```

Set the page direction and language on the `<html>` element of your layout as well:

```php
<html lang="<?= ACTIVE_LANG ?>" dir="@lang('lng.dir')">
```

## Translated content in the database

For content that differs per language, a common pattern is one column per language, such as `title`, `title_ar` and `title_ku`. `LANG_SUFFIX` picks the right one:

```php
$title = ($post['title' . LANG_SUFFIX] ?? '') ?: $post['title'];
```

`LANG_SUFFIX` is empty for English, so the plain column holds the English text.

## Adding a language

```bash
php flo lang:add fr
php flo lang:add fa --rtl
```

`lang:add` creates `lang/<code>.php` with every key of `lang/en.php` and empty values, ready to translate. `--rtl` marks it right-to-left, `--base=ar` copies the keys of another language, and `--force` replaces an existing file. Then add the code to `LANGUAGES`.

## Finding missing translations

```bash
php flo lang:missing
php flo lang:missing --lang=ar
```

`lang:missing` compares each language with `lang/en.php` and lists missing, empty and extra keys. It also reports codes in `LANGUAGES` that have no file. It exits with 1 when keys or files are missing, so you can run it in CI. `--base=` picks another reference language, and `--format=json` prints the report as JSON.

## The API

API requests pick their language from `?lang=`, then the `Accept-Language` header, then the default language. The response's `Content-Language` header says which one was used, and `__()` works in API controllers too. See [API middleware](/docs/{{version}}/api-middleware).
