# Contribution guide

FloCMS is developed in the open on GitHub by HostKurd. Bug reports, fixes and documentation improvements are welcome. This page explains where things live and what a good pull request looks like.

## The repositories

FloCMS is split into packages. Report a bug in the package whose code has it:

| Repository | Package | What it is |
|---|---|---|
| [hostkurd/FloCMS](https://github.com/hostkurd/FloCMS) | `hostkurd/flocms` | The project skeleton that `composer create-project` installs |
| [hostkurd/flocms-core](https://github.com/hostkurd/flocms-core) | `hostkurd/flocms-core` | The framework runtime: routing, controllers, views, database, sessions, permissions |
| [hostkurd/flocms-cli](https://github.com/hostkurd/flocms-cli) | `hostkurd/flocms-cli` | The `php flo` command line |
| [hostkurd/flocms-api](https://github.com/hostkurd/flocms-api) | `hostkurd/flocms-api` | The JSON API runtime |
| [hostkurd/flocms-uploader](https://github.com/hostkurd/flocms-uploader) | `hostkurd/flocms-uploader` | File, image, video and chunked uploads |
| [hostkurd/flocms-captcha](https://github.com/hostkurd/flocms-captcha) | `hostkurd/flocms-captcha` | Image captcha |

Not sure which one? Open the issue on [hostkurd/FloCMS](https://github.com/hostkurd/FloCMS/issues).

## Security vulnerabilities

Don't open a public issue for a security problem. Email the team at [dev&#64;flocms.com](mailto:dev@flocms.com) instead. Security reports are handled first.

## Reporting a bug

A good report lets someone reproduce the problem in a few minutes. Include:

- the versions involved. `php flo about` prints PHP, the extensions and every FloCMS package version.
- what you did, what you expected and what happened instead
- the error message, or the matching lines from `storage/logs/app.log`

## Pull requests

- **One change per pull request.** A small, focused PR is reviewed and released faster than a large one.
- **Keep the framework generic.** FloCMS is used by many different sites. A change to core, the CLI or the skeleton has to make sense for all of them. Features that only one site needs belong in that site's own code.
- **Add a test.** A bug fix comes with a test that fails without it. The packages have PHPUnit suites, and the skeleton, CLI and API run theirs on GitHub Actions with PHP 8.1 to 8.4.
- **Explain why.** Say in the description what was wrong or missing, and how you checked the fix.
- **Update the docs** when you change behavior: the package's `README.md` and `CHANGELOG.md`.

## Running the tests

The skeleton, core, CLI, API and uploader run their tests the same way:

```bash
composer install
composer test
```

`composer test` runs PHPUnit, so `vendor/bin/phpunit` works too. In the skeleton, core and API, tests that need MySQL or MariaDB are skipped unless you point them at a server:

```bash
FLO_TEST_MYSQL_HOST=127.0.0.1 FLO_TEST_MYSQL_USER=flo FLO_TEST_MYSQL_PASS=flo \
FLO_TEST_MYSQL_NAME=flocms_test vendor/bin/phpunit
```

`FLO_TEST_MYSQL_PORT` is read too. The CLI keeps its MySQL tests in a separate suite: `vendor/bin/phpunit --testsuite mysql` with the same variables. Core, the CLI and the API run their other database tests on SQLite, so those need no server.

## Versions and releases

Every FloCMS package follows [semantic versioning](https://semver.org), and the skeleton pins each package to an exact version. A site only changes when its owner upgrades it, so:

- fixes go out as patch releases (2.2.0 → 2.2.1)
- new features go out as minor releases, and must not break existing sites
- each release lists its changes in `CHANGELOG.md`, with upgrade steps when a site has to do something

## Improving these docs

The documentation is written in Markdown. Found a mistake or something unclear? Tell us in an issue on [hostkurd/FloCMS](https://github.com/hostkurd/FloCMS/issues), and say which page it's on.
