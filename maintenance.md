# Maintenance mode

Maintenance mode takes the site offline while you deploy or fix something. Visitors see a "back soon" page, and you can keep access for yourself.

## Going down and coming back

```bash
php flo down --message="Upgrading, back in 10 minutes" --retry=600 --allow=203.0.113.7
php flo up
```

| Option | Meaning |
|---|---|
| `--message=` | Text shown to visitors. Default: "The site is under maintenance. Please try again later." |
| `--retry=` | Seconds, sent as the `Retry-After` header, so search engines come back later instead of dropping your pages |
| `--allow=` | An IP address or CIDR range that keeps full access. Repeat it, or separate several with commas. Use your own IP. |

While the site is down:

- Pages answer **503** with a `Retry-After` header and the page `templates/default/errors/503.html`.
- The API answers 503 in JSON. IPs from `--allow` and from `API_MAINTENANCE_ALLOWED_IPS` keep access, and `/api/v1/health` stays up for monitoring.
- Allowed IPs use the site normally.

> [!TIP]
> Find your own IP by searching "what is my IP" before running `down`, and pass it with `--allow`, so you can check the site before bringing it back.

## The maintenance page

The page is `templates/default/errors/503.html`, a plain HTML file. It's sent before the rest of FloCMS starts, so it doesn't go through the template engine and doesn't use your active template. `{{message}}` in the file is replaced with the `--message` text, escaped. Edit the file to match your design, and keep its CSS inline.

## How it works

`down` writes `storage/framework/down.json`:

```json
{"time": 1767225600, "message": "Back soon", "retry": 600, "allow": ["203.0.113.7"]}
```

`public/index.php` and `public/api.php` check that file right after loading the configuration, before routing and before any database connection, so maintenance mode works even when the database is down. `up` removes the file. Behind a proxy, set `TRUSTED_PROXIES` so the allowed IPs are recognized. See [authentication](/docs/{{version}}/authentication#behind-a-proxy-or-cdn).

## The offline_mode setting

Sites that keep settings in a `settings` table (columns `param`, `value`, `lang`) have a second switch: the `offline_mode` setting, which an admin panel can turn on.

- When the database is reachable, `down` sets `offline_mode` to `1` (adding the row when it's missing), and `up` sets it back to `0`. Without a `settings` table, `down` warns and the file alone applies.
- While `offline_mode` is `1`, visitors see `templates/<template>/offline.html` instead of the page, and signed-in admins and `/admin` keep working. The API answers 503.

New projects don't have a `settings` table, so for them the file is all there is.

## During a deployment

```bash
php flo down --allow=YOUR.IP --retry=300
# upload the new code or git pull
composer install --no-dev
php flo migrate --force
php flo optimize
php flo up
```

See [deployment](/docs/{{version}}/deployment#deploying-updates).
