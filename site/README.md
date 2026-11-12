# Project site

Static GitHub Pages site, deployed by `.github/workflows/pages.yml` on pushes to `main` that touch `site/`.

- `index.html` — project page (no build step).
- `assets/` — screenshots of the viewer.
- `demo/en`, `demo/vi` — a static copy of the real viewer with sample data, produced by crawling a local app that has the package installed, rewriting links to relative files and replacing host/IP values.

Regenerate the demo after viewer changes so the screenshots and the demo match the released version.
