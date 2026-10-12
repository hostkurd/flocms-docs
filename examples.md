# Practical examples

Build a small site with FloCMS, one complete feature at a time. These guides show the files to create, the code inside them, the URL to open, and the result to expect.

## Before you start

Use a separate development project so that the examples do not replace controllers, views or themes in an existing site:

```bash
composer create-project hostkurd/flocms flocms-examples
cd flocms-examples
composer show hostkurd/flocms-core
```

These examples target FloCMS 1.7 with **flocms-core 2.2.2 or newer**. They use the normal project's bootstrap, Composer autoloading, language files and page router. See [installation](/docs/{{version}}/installation).

The first three examples use sample data and do not require a database; the admin guide uses the `users` table and explains its own setup. In this development project's `.env`, leave `DB_NAME` and `DB_USERNAME` empty. Keep `APP_DEBUG=true` while learning, and set:

```ini
LANGUAGES=en
DEFAULT_LANG=en
APP_TEMPLATE=demo
```

Create the demo layout in the blog guide before opening a page with `APP_TEMPLATE=demo`.

## Work through the guides

| Guide | What you build | What you learn |
|---|---|---|
| [A complete blog page](/docs/{{version}}/example-blog) | A translated listing with sample records and an empty state | Controllers, object data, escaped output, loops, partials, layouts and CSS assets |
| [A validated contact form](/docs/{{version}}/example-contact-form) | A form that validates input and redisplays values and errors | Requests, CSRF, core validation, explicit email checks and reusable error markup |
| [A reusable theme](/docs/{{version}}/example-theme) | Shared navigation and a footer for the blog and contact pages | Theme selection, explicit partial data, layout scope, fallback and compiled templates |
| [Build your own admin](/docs/{{version}}/example-admin) | A users page under `/admin` where administrators change names and roles | Admin actions, `$actionPermissions`, `Auth` role rules, CSRF forms, redirects after saving. Needs the database |

Follow them in that order: the contact form and theme guide build on the blog's `demo` theme. The blog guide is complete on its own.

Start the development server after adding the files:

```bash
php flo serve
```

Use the address printed by the command, followed by `/blog` or `/contact`. If the project runs under a URL subdirectory, keep that prefix. These pages need no route registration: FloCMS maps `/blog` to `BlogController` and `/contact` to `ContactController` by convention.

## What these examples do

The blog reads an array of sample objects. Its controller has an obvious replacement point for a [model](/docs/{{version}}/models) backed by the [database](/docs/{{version}}/database).

The contact form confirms that input passed validation. It does not send email or save messages. The guide identifies where your application's delivery or persistence service belongs.

All HTML templates here are application files, compiled and executed by PHP. Keep template source under developer control. Escape visitor data with `{{ ... }}`; reserve raw output for markup already rendered from trusted application templates.

## Explore the reference

- [Controllers](/docs/{{version}}/controllers) explains conventional action dispatch and view selection.
- [Views and templates](/docs/{{version}}/views) lists the engine's supported syntax.
- [Requests and validation](/docs/{{version}}/validation) explains input rules and errors.
- [Localization](/docs/{{version}}/localization) explains translations and language-aware URLs.

