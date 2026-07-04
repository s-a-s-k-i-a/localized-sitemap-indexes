<?php
/**
 * EDD Software Licensing updater bootstrap.
 *
 * Registers this plugin with the EDD Software Licensing SDK so installed
 * copies receive updates from the isla-stud.io store. The SDK handles
 * license storage, the activate/deactivate UI in the Plugins list, and
 * update delivery on its own.
 *
 * @package LocalizedSitemapIndexes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Localized_Sitemap_Indexes_Updater {
	const STORE_URL = 'https://isla-stud.io';
	const ITEM_ID   = 3859;

	/**
	 * Hooks into the SDK registry action.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		add_action( 'edd_sl_sdk_registry', array( __CLASS__, 'register_with_sdk' ) );
	}

	/**
	 * Declares which store product this plugin is and where the store lives.
	 *
	 * @param object $registry EDD SL SDK registry instance.
	 * @return void
	 */
	public static function register_with_sdk( $registry ) {
		if ( ! is_object( $registry ) || ! method_exists( $registry, 'register' ) ) {
			return;
		}

		$registry->register(
			array(
				'id'      => 'localized-sitemap-indexes',
				'url'     => self::STORE_URL,
				'item_id' => self::ITEM_ID,
				'version' => LOCALIZED_SITEMAP_INDEXES_VERSION,
				'file'    => LOCALIZED_SITEMAP_INDEXES_FILE,
			)
		);
	}
}
