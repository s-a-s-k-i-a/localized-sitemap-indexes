# Contributing

## Scope

Keep this plugin generic.

- Target the TranslatePress + Rank Math stack first.
- Treat NitroPack support as optional.
- Do not promise full Rank Math sitemap-provider parity unless that behavior is implemented and tested.

## Local setup

```bash
composer install
composer check
composer i18n:pot
composer build:zip
```

`composer i18n:pot` expects WP-CLI with the i18n command to be available on your machine.

## Pull requests

- Keep changes narrow and easy to review.
- Include manual test notes for sitemap URLs, `robots.txt`, and any WP-CLI behavior you touch.
- Update both `README.md` and `readme.txt` when public behavior changes.
- Call out any scope changes explicitly.
