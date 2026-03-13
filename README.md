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
