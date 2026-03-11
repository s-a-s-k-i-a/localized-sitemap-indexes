# Localized Sitemap Indexes

`Localized Sitemap Indexes` is a public-plugin candidate extracted from the Storemaster maintenance work.

It targets the specific stack:

- TranslatePress
- Rank Math SEO
- optionally NitroPack

## What it does

- keeps Rank Math's root `sitemap_index.xml` untouched
- adds language-specific sitemap indexes such as `sitemap_index_en.xml`
- generates language-specific child sitemaps such as `sitemap_en_product_1.xml`
- mirrors Rank Math visibility decisions for post types and taxonomies
- exposes an optional `nitro-warmup-sitemap.xml`
- provides `wp localized-sitemaps sync-nitro`

## Current scope

This package is intentionally narrow and honest about that scope. It currently mirrors:

- published TranslatePress languages
- Rank Math XML sitemap object toggles for post types and taxonomies
- Rank Math `noindex` exclusions
- Rank Math `items_per_page`

It does not yet aim for full parity with every Rank Math sitemap provider.

## Why this may be useful beyond Storemaster

This is relevant for multilingual sites where:

- the default Rank Math sitemap is intentionally kept lean
- adding all localized URLs into the main sitemap is undesirable
- cache warmup tools benefit from a separate multilingual sitemap target

## Before a WordPress.org submission

Recommended next steps:

1. test against more TranslatePress setups
2. validate behavior with different Rank Math sitemap combinations
3. decide whether separate-domain TranslatePress setups should be supported
4. add automated tests around sitemap visibility and robots output
5. review naming carefully with respect to third-party product trademarks
