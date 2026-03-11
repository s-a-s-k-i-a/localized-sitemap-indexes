<?php
/**
 * Plugin Name:       Localized Sitemap Indexes
 * Description:       Adds language-specific XML sitemap indexes for TranslatePress while mirroring Rank Math sitemap visibility rules.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Saskia Lund
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       localized-sitemap-indexes
 * Domain Path:       /languages
 * Requires Plugins:  seo-by-rank-math, translatepress-multilingual
 *
 * @package LocalizedSitemapIndexes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LOCALIZED_SITEMAP_INDEXES_VERSION', '0.1.0' );
define( 'LOCALIZED_SITEMAP_INDEXES_FILE', __FILE__ );
define( 'LOCALIZED_SITEMAP_INDEXES_PATH', plugin_dir_path( __FILE__ ) );
define( 'LOCALIZED_SITEMAP_INDEXES_URL', plugin_dir_url( __FILE__ ) );

require_once LOCALIZED_SITEMAP_INDEXES_PATH . 'includes/class-localized-sitemap-indexes.php';

Localized_Sitemap_Indexes::bootstrap( __FILE__ );
