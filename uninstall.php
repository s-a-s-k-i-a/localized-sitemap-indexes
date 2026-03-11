<?php
/**
 * Uninstall hooks for Localized Sitemap Indexes.
 *
 * @package LocalizedSitemapIndexes
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'localized_sitemap_indexes_cache_version' );
