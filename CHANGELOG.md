# Changelog

All notable changes to this plugin are documented in this file. Each released
version has a matching Git tag in the form `vX.Y.Z` and a GitHub release with
an installable plugin ZIP.

## Unreleased

- Nothing yet.

## 0.3.1 - 2026-07-04

- Rename the display name to "Language Sitemaps for TranslatePress". The
  technical slug, plugin folder, and text domain stay
  `localized-sitemap-indexes`.

## 0.3.0 - 2026-07-04

- Add EDD Software Licensing SDK integration so copies installed from the
  isla-stud.io store receive automatic plugin updates.
- Ship production Composer dependencies inside the release ZIP.

## 0.2.0 - 2026-07-04

- Add `wp localized-sitemaps list-indexes` for operational endpoint visibility.
- Expand cache invalidation hooks for sitemap-related option lifecycle changes.
- Add activation and uninstall lifecycle housekeeping for plugin cache metadata.
- Add extension filters for translated URLs and generated sitemap entries.
- Build out public-repository tooling: Composer scripts, PHPCS, CI, contribution
  templates, release ZIP build, and translation template generation.

## 0.1.0 - 2026-03-11

- Initial plugin bootstrap.
- Add language-specific sitemap indexes for TranslatePress.
- Mirror Rank Math sitemap visibility for post types and taxonomies.
- Add optional NitroPack warmup sitemap support.
