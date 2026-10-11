# A validated contact form

Add a form at `/contact` that accepts a name, email address and message. Invalid submissions redisplay the entered values and field errors. Valid submissions show a confirmation.

Complete [the blog example](/docs/{{version}}/example-blog) first so that the `demo` layout and stylesheet exist. This example uses the same theme and needs no database.

> [!NOTE]
> This is a validation demo. It does not send an email or store the message. The successful result says exactly that.

## Files to create

| File | Purpose |
|---|---|
| `controllers/ContactController.php` | Read expected fields and validate them |
| `views/contact/index.html` | Display the form, old values and confirmation |
| `templates/demo/partials/field-errors.html` | Render the messages for one field |

## Read and validate the input

**File: `controllers/ContactController.php`**

```php
<?php
namespace FloCMS\Controllers;

use FloCMS\Core\Controller;
use FloCMS\Core\ValidationException;
use FloCMS\Core\Validator;

class ContactController extends Controller
{
    public function index(): void
    {
        $this->data['title'] = 'Contact us';
        $this->data['errors'] = [];
        $this->data['old'] = ['name' => '', 'email' => '', 'message' => ''];
        $this->data['submitted'] = false;

        if (!$this->request->isMethod('POST')) {
            return;
        }

        $values = $this->formInput();
        $this->data['old'] = $values;
        $errors = [];

        try {
            Validator::validate($values, [
                'name' => 'required|string|min:2|max:100',
                'email' => 'required|string|max:254',
                'message' => 'required|string|min:10|max:2000',
            ]);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
        }

        // Core's validator has no "email" rule, so check its format explicitly.
        if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = 'Enter a valid email address.';
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->data['errors'] = $errors;
            return;
        }

        // In a real application, call your delivery or persistence service here.
        $this->data['submitted'] = true;
        $this->data['old'] = ['name' => '', 'email' => '', 'message' => ''];
    }

    protected function formInput(): array
    {
        $values = [];

        foreach (['name', 'email', 'message'] as $field) {
            $raw = $this->request->input($field);
            // Missing fields and array-shaped input become empty, invalid values.
            $values[$field] = is_string($raw) ? trim($raw) : '';
        }

        return $values;
    }
}
```

The helper is `protected`, so it cannot be called as a page action. It reads only the fields this form accepts and checks their shape before trimming or displaying them. Whitespace-only values become empty strings.

Core's `Validator::validate()` throws an exception containing an array of messages per field. It does not return a filtered input array and does not implement an `email` rule. This controller deliberately uses the core validator and PHP's email-format check; see [requests and validation](/docs/{{version}}/validation).

On a failed validation, the controller returns normally after setting status 422, so FloCMS renders the same view with its errors and old values.

## Create the error partial

**File: `templates/demo/partials/field-errors.html`**

```html
@if($messages !== [])
    <ul class="field-errors">
        @foreach($messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
```

The partial takes an explicit `messages` array. It has no dependency on a particular controller or field name, so every input can reuse it.

## Create the form view

**File: `views/contact/index.html`**

```html
<section aria-labelledby="contact-title">
    <h1 id="contact-title">{{ $title }}</h1>

    @if($submitted)
        <p class="success-message" role="status">
            Your demo message passed validation. No email was sent or message saved.
        </p>
    @endif

    <form method="post" action="" class="contact-form">
        @csrf

        <div class="form-field">
            <label for="contact-name">Name</label>
            <input id="contact-name" name="name" type="text" value="{{ $old['name'] }}" required minlength="2" maxlength="100">
            <?= render_partial('field-errors', ['messages' => $errors['name'] ?? []]) ?>
        </div>

        <div class="form-field">
            <label for="contact-email">Email</label>
            <input id="contact-email" name="email" type="email" value="{{ $old['email'] }}" required maxlength="254">
            <?= render_partial('field-errors', ['messages' => $errors['email'] ?? []]) ?>
        </div>

        <div class="form-field">
            <label for="contact-message">Message</label>
            <textarea id="contact-message" name="message" rows="6" required minlength="10" maxlength="2000">{{ $old['message'] }}</textarea>
            <?= render_partial('field-errors', ['messages' => $errors['message'] ?? []]) ?>
        </div>

        <button type="submit">Validate message</button>
    </form>
</section>
```

An empty `action` submits to the current page, retaining the language and any URL subdirectory. FloCMS validates the CSRF token before the controller runs. `@csrf` renders the hidden `_token` field; it does not perform validation itself.

The old values are escaped inside quoted attributes or the textarea's text content. Do not use raw output for them. Browser validation is useful feedback, but the controller's validation remains necessary when the request comes from another client.

## Add the form styles

Append this block to `public/themes/demo/css/site.css` from the blog example:

```css
.contact-form { max-width: 640px; display: grid; gap: 20px; }
.form-field { display: grid; gap: 8px; }
.form-field label { font-weight: 600; }
.form-field input, .form-field textarea {
    box-sizing: border-box;
    width: 100%;
    padding: 12px;
    border: 1px solid #a9b5c5;
    border-radius: 8px;
    font: inherit;
}
.contact-form button {
    justify-self: start;
    padding: 12px 20px;
    border: 0;
    border-radius: 8px;
    background: #214fc6;
    color: #fff;
    font: inherit;
    cursor: pointer;
}
.field-errors { margin: 0; padding-inline-start: 20px; color: #a71930; }
.success-message { padding: 16px; background: #e5f5eb; color: #185c35; border-radius: 8px; }
```

## Try each result

Open `/contact` beneath the development server's address.

| Test | Expected result |
|---|---|
| Open the page with GET | Empty form; no validation errors or confirmation |
| Submit a valid name, email and message of at least ten characters | Confirmation appears and the fields reset |
| Submit empty values or an invalid email | Server responds with 422 and field messages; string values remain in the form |
| Submit a name such as `<b>Sara</b>` with another invalid field | The name remains literal text in the input, without injecting markup |
| Send an array for `name` instead of a string | A name validation error; no array-to-string warning |
| Submit without a valid CSRF token | FloCMS rejects the request with status 419 before this action runs |

HTML input constraints can stop invalid submissions before they reach PHP. To inspect the server behavior, temporarily add `novalidate` to the form in your development copy or use a client that preserves the session cookie and hidden token.

## Adding actual message delivery

At the marked point in the controller, call an application service with the validated `$values`. Only show success after that service succeeds. When delivery or storage has side effects, follow success with a redirect to a GET page to avoid browser refresh resubmitting the form. See [controllers](/docs/{{version}}/controllers#flash-messages).

Keep delivery failures separate from field-validation errors. Add the spam protection your real form needs, such as [CAPTCHA](/docs/{{version}}/captcha), before deploying it as a public contact service.

Continue with [a reusable theme](/docs/{{version}}/example-theme).

