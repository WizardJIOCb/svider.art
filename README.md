# svider.art

Static website for the engraving workshop of Sergey Mikhailovich Svider.

## Project

The site includes:

- a landing page with sections for featured works, collections, ordering, workshop, and contacts
- a separate catalog page with filters
- detail pages for collections and individual engravings
- JSON-based content storage

## Structure

- `index.html` - main page markup
- `app.js` - rendering, hash routing, filters, and UI logic
- `styles.css` - all site styles
- `content/` - artist, works, collections, contacts, and section texts
- `assets/` - local images and media
- `docs/` - project concept and content model
- `scripts/` - local helper scripts

## Local run

On Windows:

```bat
start-dev.bat
```

The site opens at:

```text
http://127.0.0.1:4173/
```

## Deployment

This project is deployed as a static site. Production requires:

- `index.html`
- `app.js`
- `styles.css`
- `assets/`
- `content/`

These files can be copied to the server and served directly by `nginx`.

## Mirror domain

The public site is served from `svider.art`, but a mirror `sweder.ru` now points to the same content. Once the new DNS record is live, add both hostnames to your web server (for example, `server_name svider.art sweder.ru;`) and make sure the TLS certificate covers each domain so requests stay secure on either address.

To keep outgoing notifications in sync with the mirror, copy `config/site.example.php` to `config/site.php` on the deployment host and adjust the host list there. That file is used by the request handler to mention whichever domains are in use and to build `no-reply@` addresses consistently. Because the `sweder.ru` A record may still be propagating, revisit the server once the hostname resolves so the mirror is added to nginx/TLS settings when it is reachable.
