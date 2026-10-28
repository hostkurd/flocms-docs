# FloCMS documentation

The documentation of [FloCMS](https://github.com/hostkurd/FloCMS), the PHP framework by HostKurd, published at [flocms.com/docs](https://flocms.com/docs).

## Branches

Each FloCMS version has its own branch. The branch name is the version in the URL:

| Branch | Published at |
|---|---|
| `2.0` | `flocms.com/docs/2.0/...` (the default) |
| `1.7` | `flocms.com/docs/1.7/...` |

Fix a mistake in the branch of the version it applies to. The admin panel's guides are not here: they live in the [flocms-admin](https://github.com/hostkurd/flocms-admin) repository's `docs/` folder and are published at `flocms.com/docs/admin/...`, so link to them as `/docs/admin` (the check accepts that address, and `/docs/studio` for the Studio guide). When a new version comes out, its branch starts as a copy of the previous one.

## Contributing

Found a mistake or something unclear? Edit the page on GitHub, using the "Edit this page" link on flocms.com or the pencil icon here, and open a pull request against the version's branch. For larger changes, open an issue first.

Documentation describes what the code does. Check new statements against the source of the package they describe: [flocms-core](https://github.com/hostkurd/flocms-core), [flocms-cli](https://github.com/hostkurd/flocms-cli), [flocms-api](https://github.com/hostkurd/flocms-api), [flocms-admin](https://github.com/hostkurd/flocms-admin), [flocms-uploader](https://github.com/hostkurd/flocms-uploader) or [flocms-captcha](https://github.com/hostkurd/flocms-captcha).

## How pages are written

- **One page per file.** `routing.md` is published at `/docs/{version}/routing`.
- **The sidebar** is `documentation.md`: sections and pages, in order. Add every new page there.
- **Links between pages** use the `{{version}}` placeholder, so they work in every version: `[routing](/docs/{{version}}/routing#parameters)`.
- **Callouts** use GitHub's syntax: `> [!NOTE]`, `> [!TIP]`, `> [!IMPORTANT]`, `> [!WARNING]` and `> [!CAUTION]`. `> [!STUDIO]` says how to do the same in [FloCMS Studio](https://flocms.com/studio), the desktop app, and links to its guide as `/docs/studio/<page>` (GitHub shows it as a plain quote).
- **Headings** from `##` to `####` get anchors, and `##` and `###` appear in the page's "On this page" list.

## Checking your changes

Every pull request runs a check. It fails when a sidebar entry or link points to a missing page or heading, when a page is missing from the sidebar, or when a code block is never closed. Run it locally with PHP 8.1 or newer:

```bash
php .github/scripts/check-docs.php .
```

The [practical examples](examples.md) include complete files. Their CI check extracts those files from the Markdown and runs the blog, contact form, theme and admin examples against Core 2.9.0 on PHP 8.1 and 8.3. It checks rendered output, escaping, empty states, form errors, CSRF rejection, theme fallback, URL subdirectories, and the admin example's permissions and role rules (on an SQLite `users` table).

To run the example check locally, supply the path to a checkout of `hostkurd/flocms-core` at version 2.9.0:

```bash
php .github/scripts/check-examples.php /path/to/flocms-core
```

The checker creates a temporary application and does not modify your site. Keep the `**File: ...**` labels directly above each complete file's code block; the checker uses them to extract the actual documented code. The blog guide's `**Add to `lang/en.php`:**` block is extracted the same way and wrapped in a language file.

## License

The documentation is released under the [MIT license](LICENSE).

