# Localized Sitemap Indexes

Localized Sitemap Indexes is a WordPress plugin for multilingual sites that use TranslatePress, Rank Math SEO, and optionally NitroPack.

## Status

- Current development version: `0.2.0`
- Intended as a public GitHub repository first
- Kept intentionally narrow until the core sitemap behavior is well-tested

## What it does

- keeps Rank Math's root `sitemap_index.xml` untouched
- adds language-specific sitemap indexes such as `sitemap_index_en.xml`
- generates language-specific child sitemaps such as `sitemap_en_product_1.xml`
- mirrors Rank Math sitemap visibility for enabled post types and taxonomies
- mirrors Rank Math `noindex` exclusions and `items_per_page`
- exposes an optional `nitro-warmup-sitemap.xml`
- provides `wp localized-sitemaps list-indexes`
- provides `wp localized-sitemaps sync-nitro`

## Scope boundaries

This plugin is deliberately scoped around TranslatePress directory-based languages and the core Rank Math XML sitemap toggles for post types and taxonomies.

Current non-goals:

- full parity with every Rank Math sitemap provider
- hreflang annotations inside sitemap XML
- separate-domain TranslatePress language setups

## Why this exists

This is useful when:

- the main Rank Math sitemap should remain lean
- language-specific discovery paths are preferred over one combined sitemap tree
- a cache warmer should target multilingual URLs through a separate index

## Development

Local quality checks use Composer and PHPCS:

```bash
composer install
composer check
```

The default Composer scripts are:

- `composer lint:php`
- `composer lint:phpcs`
- `composer check`
- `composer i18n:pot`
- `composer build:zip`

Translation template updates use WP-CLI's i18n command:

```bash
composer i18n:pot
```

Release archives are built from the current Git commit:

```bash
composer build:zip
```

## Extensibility

The plugin already exposes filters for:

- translated URL generation
- language sitemap index entries
- language child sitemap entries
- Nitro warmup index entries

That keeps the package generic while allowing project-specific integration work outside the plugin core.

## Roadmap

Near-term priorities:

1. Add automated WordPress integration tests around sitemap visibility and `robots.txt`.
2. Validate behavior across more TranslatePress language configurations.
3. Decide whether separate-domain TranslatePress support belongs in scope.
4. Document release and packaging steps for GitHub releases and future wp.org deployment.

## License

GPL-2.0-or-later
