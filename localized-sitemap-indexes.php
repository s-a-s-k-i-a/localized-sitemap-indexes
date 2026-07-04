<?php
/**
 * Plugin Name:       Language Sitemaps for TranslatePress
 * Description:       Adds language-specific XML sitemap indexes for TranslatePress while mirroring Rank Math sitemap visibility rules.
 * Version:           0.3.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Saskia Teichmann
 * Author URI:        https://isla-stud.io
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

define( 'LOCALIZED_SITEMAP_INDEXES_VERSION', '0.3.1' );
define( 'LOCALIZED_SITEMAP_INDEXES_FILE', __FILE__ );
define( 'LOCALIZED_SITEMAP_INDEXES_PATH', plugin_dir_path( __FILE__ ) );
define( 'LOCALIZED_SITEMAP_INDEXES_URL', plugin_dir_url( __FILE__ ) );

require_once LOCALIZED_SITEMAP_INDEXES_PATH . 'includes/class-localized-sitemap-indexes.php';

// The EDD Software Licensing SDK ships with release builds only; a source
// checkout without vendor/ simply runs without store update delivery.
if ( file_exists( LOCALIZED_SITEMAP_INDEXES_PATH . 'vendor/easy-digital-downloads/edd-sl-sdk/edd-sl-sdk.php' ) ) {
	require_once LOCALIZED_SITEMAP_INDEXES_PATH . 'vendor/easy-digital-downloads/edd-sl-sdk/edd-sl-sdk.php';
	require_once LOCALIZED_SITEMAP_INDEXES_PATH . 'includes/class-localized-sitemap-indexes-updater.php';
	Localized_Sitemap_Indexes_Updater::bootstrap();
}

register_activation_hook( __FILE__, array( 'Localized_Sitemap_Indexes', 'activate' ) );

Localized_Sitemap_Indexes::bootstrap( __FILE__ );
