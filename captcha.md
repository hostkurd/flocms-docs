# Captcha

`hostkurd/flocms-captcha` adds an image captcha to forms that guests can submit, such as a contact or sign-up form, to keep automated spam out. New FloCMS projects already require it. It needs PHP's GD extension.

## How it works

1. A page shows an image with a random code, for example `K7MPQ2`. Creating the image stores the code in the visitor's session.
2. The visitor types the code into the form.
3. Your controller compares the typed text with the stored code.

## Showing the image

Serve the image from a controller action, so it runs with the site's session:

```php
<?php
namespace FloCMS\Controllers;

use FloCMS\Captcha\CaptchaManager;
use FloCMS\Core\Controller;

class CaptchaController extends Controller
{
    public function image(): void
    {
        (new CaptchaManager())->output();
        exit;
    }
}
```

`output()` creates a new code, stores it, and sends a PNG with headers that stop browsers from caching it. The `exit` matters: the action has no view to render.

In the form:

```html
<form method="post" action="">
    @csrf
    <img src="<?= SITE_URI ?>/captcha/image" alt="Security code" width="180" height="50">
    <input type="text" name="captcha" autocomplete="off" required>
    <button type="submit">Send</button>
</form>
```

A "new code" link only needs to reload the image, for example by adding `?` and a timestamp to its `src`.

## Checking the code

```php
use FloCMS\Captcha\CaptchaManager;

public function contact(): void
{
    if (!$this->request->isMethod('POST')) {
        return;
    }

    $captcha = new CaptchaManager();
    $valid = $captcha->verify($this->request->input('captcha'));
    $captcha->clear();

    if (!$valid) {
        $this->data['error'] = 'The security code is wrong. Please try again.';
        return;
    }

    // handle the form
}
```

`verify()` returns `true` when the typed text matches. Spaces around it are ignored, and by default upper and lower case don't matter.

> [!IMPORTANT]
> Call `clear()` after every check, successful or not. `verify()` doesn't remove the code, so without `clear()` a script could keep guessing the same code. After `clear()`, the form shows a new image.

A captcha doesn't replace CSRF protection; keep `@csrf` in the form. See [sessions and CSRF](/docs/{{version}}/session#csrf-protection).

## Configuration

Pass the settings you want to change. Use the same settings for `output()` and `verify()`, or at least the same `session_key`:

```php
$captcha = new CaptchaManager([
    'length' => 5,
    'width' => 200,
    'height' => 60,
]);
```

| Setting | Default | |
|---|---|---|
| `length` | `6` | Number of characters |
| `width`, `height` | `180`, `50` | Image size in pixels |
| `font_size` | `24` | |
| `angle_min`, `angle_max` | `-15`, `15` | Rotation range of each character |
| `noise_lines` | `8` | Lines drawn across the image |
| `characters` | `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` | The characters to pick from; easily confused ones like `0`, `O`, `1` and `I` are left out |
| `case_sensitive` | `false` | |
| `session_key` | `flocms_captcha` | Where the code is stored in the session |
| `fonts` | three fonts in the package | TrueType font files to pick from |

The code is generated with `random_int()` and compared with `hash_equals()`.

## Your own renderer

To draw the captcha differently, implement `FloCMS\Captcha\Renderer\RendererInterface` and pass it as the second argument:

```php
use FloCMS\Captcha\Renderer\RendererInterface;

class CustomRenderer implements RendererInterface
{
    public function render(string $text): void
    {
        // send headers and output an image of $text
    }
}

$captcha = new CaptchaManager([], new CustomRenderer());
```

## Captchas in the API

The captcha is stored in the session, and the API has no session by default. For forms submitted through the API, rely on the API's rate limits and idempotency instead. See [CORS and rate limiting](/docs/{{version}}/api-cors#rate-limiting).
