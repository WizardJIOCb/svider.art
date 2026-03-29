# svider.art

Static website for the engraving workshop of Sergey Mikhailovich Svider.

## Project

The site includes:

- a landing page with sections for featured works, collections, ordering, workshop, and contacts
- a separate catalog page with filters
- detail pages for collections and individual engravings
- PostgreSQL-based content storage (with JSON auto-migration fallback)

## Structure

- `index.html` - main page markup
- `app.js` - rendering, hash routing, filters, and UI logic
- `styles.css` - all site styles
- `content/` - artist, works, collections, contacts, and section texts
- `assets/` - local images and media
- `docs/` - project concept and content model
- `scripts/` - local helper scripts
- `docker-compose.yml` - PostgreSQL container with persistent volume

## Local run

On Windows:

```bat
start-dev.bat
```

The site opens at:

```text
http://127.0.0.1:4173/
```

## PostgreSQL (Docker)

1. Start PostgreSQL in Docker:

```bash
docker compose up -d postgres
```

2. Create local DB config:

```text
copy config/db.example.php config/db.php
```

3. Update credentials in `config/db.php` (or set env variables `DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASSWORD`).

4. One-time migration of existing JSON content to PostgreSQL:

```bash
php scripts/migrate-content-to-postgres.php
```

Use `--overwrite` only if you intentionally want DB data replaced by current files:

```bash
php scripts/migrate-content-to-postgres.php --overwrite
```

## Deployment

This project is no longer purely static. Production requires PHP + PostgreSQL:

- `index.html`, `app.js`, `styles.css`
- `admin/`, `request.php`, `lib/`, `config/`
- `assets/`
- PostgreSQL (recommended via `docker compose`) with persistent `postgres_data` volume

Runtime flow:

- public site tries to load content from `admin/api.php?action=public-content` (PostgreSQL source of truth)
- if API is unavailable, client falls back to `content/*.json` for backward compatibility
- admin panel and request form read/write through PostgreSQL; first read auto-imports from old JSON when needed

## Mirror domain

The public site is served from `svider.art`, but a mirror `sweder.ru` now points to the same content. Once the new DNS record is live, add both hostnames to your web server (for example, `server_name svider.art sweder.ru;`) and make sure the TLS certificate covers each domain so requests stay secure on either address.

To keep outgoing notifications in sync with the mirror, copy `config/site.example.php` to `config/site.php` on the deployment host and adjust the host list there. That file is used by the request handler to mention whichever domains are in use and to build `no-reply@` addresses consistently. Because the `sweder.ru` A record may still be propagating, revisit the server once the hostname resolves so the mirror is added to nginx/TLS settings when it is reachable.
