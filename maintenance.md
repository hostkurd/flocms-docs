# Maintenance mode

Maintenance mode takes the site offline while you deploy or fix something. Visitors see a "back soon" page, and you can keep access for yourself.

## Going down and coming back

```bash
php flo down --message="Upgrading, back in 10 minutes" --retry=600 --allow=203.0.113.7
php flo up
```

| Option | Meaning |
|---|---|
| `--message=` | Text shown to visitors |
| `--retry=` | Seconds, sent as the `Retry-After` header |
| `--allow=` | An IP address or CIDR range that keeps full access. Use your own IP. |

While the site is down:

- Pages answer **503** with `templates/<template>/errors/503.html` and a `Retry-After` header.
- The API answers 503 in JSON. IPs in `API_MAINTENANCE_ALLOWED_IPS` keep access, and `/api/v1/health` stays up for monitoring.
- Allowed IPs use the site normally.

## How it works

`down` writes `storage/framework/down.json`, and `public/index.php` and `public/api.php` check that file before the request is handled. When the database is reachable, `down` also turns on the `offline_mode` setting. `up` removes the file.

> [!TIP]
> Find your own IP by searching "what is my IP" before running `down`, and pass it with `--allow`, so you can check the site before bringing it back.
