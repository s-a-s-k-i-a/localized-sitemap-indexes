<?php
/**
 * Core plugin class.
 *
 * @package LocalizedSitemapIndexes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Localized_Sitemap_Indexes {
	const CACHE_GROUP         = 'localized_sitemap_indexes';
	const CACHE_VERSION_OPTION = 'localized_sitemap_indexes_cache_version';

	/**
	 * @var string
	 */
	private $plugin_file;

	/**
	 * @var array<string,mixed>|null
	 */
	private $trp_settings = null;

	/**
	 * @var array<string,mixed>|null
	 */
	private $rank_math_sitemap_options = null;

	/**
	 * @var array<string,mixed>|null
	 */
	private $rank_math_title_options = null;

	/**
	 * @var array<string,array<string,mixed>>|null
	 */
	private $languages = null;

	/**
	 * @var array<string,array<int,int>>|null
	 */
	private $taxonomy_term_id_cache = null;

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @param string $plugin_file Main plugin file path.
	 * @return self
	 */
	public static function bootstrap( $plugin_file ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $plugin_file );
		}

		return self::$instance;
	}

	/**
	 * @param string $plugin_file Main plugin file path.
	 */
	private function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;

		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_boot_runtime' ), 20 );
		add_action( 'admin_notices', array( $this, 'render_dependency_notice' ) );

		add_action( 'update_option_trp_settings', array( $this, 'bump_cache_version' ), 10, 0 );
		add_action( 'add_option_trp_settings', array( $this, 'bump_cache_version' ), 10, 0 );
		add_action( 'update_option_rank-math-options-sitemap', array( $this, 'bump_cache_version' ), 10, 0 );
		add_action( 'update_option_rank-math-options-titles', array( $this, 'bump_cache_version' ), 10, 0 );
		add_action( 'save_post', array( $this, 'bump_cache_version' ), 10, 0 );
		add_action( 'deleted_post', array( $this, 'bump_cache_version' ), 10, 0 );
		add_action( 'trashed_post', array( $this, 'bump_cache_version' ), 10, 0 );
		add_action( 'untrashed_post', array( $this, 'bump_cache_version' ), 10, 0 );
		add_action( 'created_term', array( $this, 'bump_cache_version' ), 10, 0 );
		add_action( 'edited_term', array( $this, 'bump_cache_version' ), 10, 0 );
		add_action( 'delete_term', array( $this, 'bump_cache_version' ), 10, 0 );
	}

	/**
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'localized-sitemap-indexes',
			false,
			dirname( plugin_basename( $this->plugin_file ) ) . '/languages'
		);
	}

	/**
	 * @return void
	 */
	public function maybe_boot_runtime() {
		if ( ! $this->dependencies_are_met() ) {
			return;
		}

		add_action( 'template_redirect', array( $this, 'maybe_render_sitemap' ), 0 );
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 30, 2 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command(
				'localized-sitemaps sync-nitro',
				array( $this, 'cli_sync_nitro_warmup_sitemap' )
			);
		}
	}

	/**
	 * @return bool
	 */
	private function dependencies_are_met() {
		$has_rank_math      = class_exists( 'RankMath' ) || class_exists( 'RankMath\\Helper' ) || defined( 'RANK_MATH_VERSION' );
		$has_translatepress = class_exists( 'TRP_Translate_Press' );

		return $has_rank_math && $has_translatepress;
	}

	/**
	 * @return void
	 */
	public function render_dependency_notice() {
		if ( $this->dependencies_are_met() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__(
				'Localized Sitemap Indexes requires both Rank Math SEO and TranslatePress to be active.',
				'localized-sitemap-indexes'
			)
		);
	}

	/**
	 * @return void
	 */
	public function maybe_render_sitemap() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( '' === $request_path ) {
			return;
		}

		if ( preg_match( '#^/sitemap_index_([a-z0-9-]+)\.xml$#i', $request_path, $matches ) ) {
			$this->render_language_index( sanitize_key( $matches[1] ) );
		}

		if ( preg_match( '#^/sitemap_([a-z0-9-]+)_([a-z0-9_-]+)_([0-9]+)\.xml$#i', $request_path, $matches ) ) {
			$this->render_language_object_sitemap(
				sanitize_key( $matches[1] ),
				sanitize_key( $matches[2] ),
				max( 1, absint( $matches[3] ) )
			);
		}

		if ( $this->should_expose_nitro_warmup_index() && '/nitro-warmup-sitemap.xml' === $request_path ) {
			$this->render_nitro_warmup_index();
		}
	}

	/**
	 * @param string $output Existing robots.txt output.
	 * @return string
	 */
	public function filter_robots_txt( $output ) {
		$output = trim( (string) $output );
		$lines  = '' === $output ? array() : preg_split( "/\r\n|\n|\r/", $output );

		if ( ! is_array( $lines ) ) {
			$lines = array();
		}

		$language_sitemaps = array();

		foreach ( $this->get_languages() as $language ) {
			if ( ! empty( $language['is_default'] ) && ! $this->should_advertise_default_language_index() ) {
				continue;
			}

			$language_sitemaps[] = $this->get_language_index_url( $language['slug'] );
		}

		$sitemaps = array_filter(
			array_merge(
				$this->should_advertise_rank_math_root_index() ? array( home_url( '/sitemap_index.xml' ) ) : array(),
				$language_sitemaps
			)
		);

		foreach ( array_unique( $sitemaps ) as $sitemap_url ) {
			$line = 'Sitemap: ' . $sitemap_url;
			if ( ! in_array( $line, $lines, true ) ) {
				$lines[] = $line;
			}
		}

		return trim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * @param array<int,string>  $args CLI positional arguments.
	 * @param array<string,mixed> $assoc_args CLI associative arguments.
	 * @return void
	 */
	public function cli_sync_nitro_warmup_sitemap( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! function_exists( 'get_nitropack_sdk' ) ) {
			WP_CLI::error(
				esc_html__( 'NitroPack is not active, so the warmup sitemap cannot be synced.', 'localized-sitemap-indexes' )
			);
		}

		$nitro = get_nitropack_sdk();

		if ( null === $nitro ) {
			WP_CLI::error(
				esc_html__( 'NitroPack SDK is not available.', 'localized-sitemap-indexes' )
			);
		}

		$warmup_url = $this->get_nitro_warmup_index_url();

		try {
			$nitro->getApi()->setWarmupSitemap( $warmup_url );
			update_option(
				'nitropack-warmup-sitemap',
				ltrim( (string) wp_parse_url( $warmup_url, PHP_URL_PATH ), '/' ) . ' used by Localized Sitemap Indexes'
			);
		} catch ( Exception $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}

		WP_CLI::success(
			sprintf(
				/* translators: %s: sitemap URL */
				esc_html__( 'NitroPack warmup sitemap set to %s', 'localized-sitemap-indexes' ),
				$warmup_url
			)
		);
	}

	/**
	 * @return void
	 */
	public function bump_cache_version() {
		update_option( self::CACHE_VERSION_OPTION, (string) microtime( true ), false );
		$this->trp_settings              = null;
		$this->rank_math_sitemap_options = null;
		$this->rank_math_title_options   = null;
		$this->languages                 = null;
		$this->taxonomy_term_id_cache    = null;
	}

	/**
	 * @return bool
	 */
	private function should_advertise_rank_math_root_index() {
		return (bool) apply_filters( 'localized_sitemap_indexes_advertise_rank_math_root_index', true );
	}

	/**
	 * @return bool
	 */
	private function should_advertise_default_language_index() {
		return (bool) apply_filters( 'localized_sitemap_indexes_advertise_default_language_index', false );
	}

	/**
	 * @return bool
	 */
	private function should_expose_nitro_warmup_index() {
		return (bool) apply_filters( 'localized_sitemap_indexes_enable_nitro_warmup_index', true );
	}

	/**
	 * @return int
	 */
	private function get_cache_ttl() {
		return (int) apply_filters( 'localized_sitemap_indexes_cache_ttl', 1800 );
	}

	/**
	 * @param string $language_slug Requested language slug.
	 * @return void
	 */
	private function render_language_index( $language_slug ) {
		$language = $this->get_language_by_slug( $language_slug );

		if ( null === $language ) {
			$this->render_not_found();
		}

		$cache_key = $this->get_cache_key( 'index:' . $language_slug );
		$xml       = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $xml ) {
			$entries = array();

			foreach ( $this->get_enabled_post_types() as $post_type ) {
				$total_items = $this->get_post_type_count( $post_type );

				if ( $total_items < 1 ) {
					continue;
				}

				$total_pages = (int) ceil( $total_items / $this->get_sitemap_page_size() );

				for ( $page = 1; $page <= $total_pages; $page++ ) {
					$entries[] = $this->build_sitemap_entry(
						$this->get_language_object_sitemap_url( $language_slug, $post_type, $page )
					);
				}
			}

			foreach ( $this->get_enabled_taxonomies() as $taxonomy ) {
				$total_items = count( $this->get_taxonomy_term_ids( $taxonomy ) );

				if ( $total_items < 1 ) {
					continue;
				}

				$total_pages = (int) ceil( $total_items / $this->get_sitemap_page_size() );

				for ( $page = 1; $page <= $total_pages; $page++ ) {
					$entries[] = $this->build_sitemap_entry(
						$this->get_language_object_sitemap_url( $language_slug, $taxonomy, $page )
					);
				}
			}

			$xml = $this->wrap_sitemap_index( $entries );
			wp_cache_set( $cache_key, $xml, self::CACHE_GROUP, $this->get_cache_ttl() );
		}

		$this->send_xml_response( $xml );
	}

	/**
	 * @param string $language_slug Requested language slug.
	 * @param string $object_name Post type or taxonomy.
	 * @param int    $page Page number.
	 * @return void
	 */
	private function render_language_object_sitemap( $language_slug, $object_name, $page ) {
		$language = $this->get_language_by_slug( $language_slug );

		if ( null === $language || ! $this->is_enabled_object_name( $object_name ) ) {
			$this->render_not_found();
		}

		$cache_key = $this->get_cache_key( 'urls:' . $language_slug . ':' . $object_name . ':' . $page );
		$xml       = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $xml ) {
			$entries = array();

			if ( $this->is_enabled_post_type( $object_name ) ) {
				$entries = $this->build_post_entries( $object_name, $language['locale'], $page );
			} elseif ( $this->is_enabled_taxonomy( $object_name ) ) {
				$entries = $this->build_taxonomy_entries( $object_name, $language['locale'], $page );
			}

			if ( empty( $entries ) ) {
				$this->render_not_found();
			}

			$xml = $this->wrap_url_set( $entries );
			wp_cache_set( $cache_key, $xml, self::CACHE_GROUP, $this->get_cache_ttl() );
		}

		$this->send_xml_response( $xml );
	}

	/**
	 * @return void
	 */
	private function render_nitro_warmup_index() {
		$cache_key = $this->get_cache_key( 'nitro-warmup-index' );
		$xml       = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $xml ) {
			$entries        = array();
			$language_slugs = $this->get_nitro_language_order();
			$object_names   = $this->get_nitro_object_names();

			foreach ( $language_slugs as $language_slug ) {
				foreach ( $object_names as $object_name ) {
					$total_items = $this->is_enabled_post_type( $object_name )
						? $this->get_post_type_count( $object_name )
						: count( $this->get_taxonomy_term_ids( $object_name ) );

					if ( $total_items < 1 ) {
						continue;
					}

					$total_pages = (int) ceil( $total_items / $this->get_sitemap_page_size() );

					for ( $page = 1; $page <= $total_pages; $page++ ) {
						$entries[] = $this->build_sitemap_entry(
							$this->get_language_object_sitemap_url( $language_slug, $object_name, $page )
						);
					}
				}
			}

			$xml = $this->wrap_sitemap_index( $entries );
			wp_cache_set( $cache_key, $xml, self::CACHE_GROUP, $this->get_cache_ttl() );
		}

		$this->send_xml_response( $xml );
	}

	/**
	 * @param string $post_type Post type name.
	 * @param string $locale TranslatePress locale.
	 * @param int    $page Page number.
	 * @return array<int,array<string,string>>
	 */
	private function build_post_entries( $post_type, $locale, $page ) {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => array( 'publish' ),
				'fields'                 => 'ids',
				'posts_per_page'         => $this->get_sitemap_page_size(),
				'paged'                  => $page,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'meta_query'             => $this->get_noindex_exclusion_meta_query(),
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'cache_results'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$entries = array();

		foreach ( $query->posts as $post_id ) {
			$permalink = get_permalink( $post_id );

			if ( ! $permalink ) {
				continue;
			}

			$entries[] = array(
				'loc'     => $this->translate_url_for_locale( $permalink, $locale ),
				'lastmod' => get_post_modified_time( DATE_W3C, true, $post_id ),
			);
		}

		wp_reset_postdata();

		return $entries;
	}

	/**
	 * @param string $taxonomy Taxonomy name.
	 * @param string $locale TranslatePress locale.
	 * @param int    $page Page number.
	 * @return array<int,array<string,string>>
	 */
	private function build_taxonomy_entries( $taxonomy, $locale, $page ) {
		$term_ids = $this->get_taxonomy_term_ids( $taxonomy );
		$offset   = ( $page - 1 ) * $this->get_sitemap_page_size();
		$term_ids = array_slice( $term_ids, $offset, $this->get_sitemap_page_size() );
		$entries  = array();

		foreach ( $term_ids as $term_id ) {
			$term_link = get_term_link( (int) $term_id, $taxonomy );

			if ( is_wp_error( $term_link ) ) {
				continue;
			}

			$entries[] = array(
				'loc' => $this->translate_url_for_locale( $term_link, $locale ),
			);
		}

		return $entries;
	}

	/**
	 * @param string $url Sitemap URL.
	 * @return array<string,string>
	 */
	private function build_sitemap_entry( $url ) {
		return array(
			'loc' => $url,
		);
	}

	/**
	 * @return array<int,string>
	 */
	private function get_nitro_object_names() {
		$preferred_order = apply_filters(
			'localized_sitemap_indexes_nitro_object_order',
			array( 'page', 'product', 'post', 'glossary', 'product_cat', 'category' )
		);
		$enabled_objects = array_merge( $this->get_enabled_post_types(), $this->get_enabled_taxonomies() );
		$ordered         = array();

		foreach ( $preferred_order as $object_name ) {
			if ( in_array( $object_name, $enabled_objects, true ) ) {
				$ordered[] = $object_name;
			}
		}

		foreach ( $enabled_objects as $object_name ) {
			if ( ! in_array( $object_name, $ordered, true ) ) {
				$ordered[] = $object_name;
			}
		}

		return $ordered;
	}

	/**
	 * @return array<int,string>
	 */
	private function get_nitro_language_order() {
		$default   = '';
		$ordered   = array();
		$languages = $this->get_languages();

		foreach ( $languages as $language ) {
			if ( ! empty( $language['is_default'] ) ) {
				$default = $language['slug'];
				break;
			}
		}

		if ( '' !== $default ) {
			$ordered[] = $default;
		}

		foreach ( $languages as $language ) {
			if ( $language['slug'] === $default ) {
				continue;
			}

			$ordered[] = $language['slug'];
		}

		return $ordered;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_trp_settings() {
		if ( null === $this->trp_settings ) {
			$settings           = get_option( 'trp_settings', array() );
			$this->trp_settings = is_array( $settings ) ? $settings : array();
		}

		return $this->trp_settings;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_rank_math_sitemap_options() {
		if ( null === $this->rank_math_sitemap_options ) {
			$options                      = get_option( 'rank-math-options-sitemap', array() );
			$this->rank_math_sitemap_options = is_array( $options ) ? $options : array();
		}

		return $this->rank_math_sitemap_options;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_rank_math_title_options() {
		if ( null === $this->rank_math_title_options ) {
			$options                    = get_option( 'rank-math-options-titles', array() );
			$this->rank_math_title_options = is_array( $options ) ? $options : array();
		}

		return $this->rank_math_title_options;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function get_languages() {
		if ( null !== $this->languages ) {
			return $this->languages;
		}

		$settings         = $this->get_trp_settings();
		$default_language = isset( $settings['default-language'] ) ? (string) $settings['default-language'] : '';
		$locales          = isset( $settings['publish-languages'] ) && is_array( $settings['publish-languages'] )
			? $settings['publish-languages']
			: array();
		$url_slugs        = isset( $settings['url-slugs'] ) && is_array( $settings['url-slugs'] )
			? $settings['url-slugs']
			: array();
		$languages        = array();

		foreach ( $locales as $locale ) {
			if ( ! is_string( $locale ) || '' === $locale ) {
				continue;
			}

			$slug = isset( $url_slugs[ $locale ] ) && is_string( $url_slugs[ $locale ] ) && '' !== $url_slugs[ $locale ]
				? $url_slugs[ $locale ]
				: strtolower( substr( $locale, 0, 2 ) );

			$languages[ $slug ] = array(
				'locale'     => $locale,
				'slug'       => $slug,
				'is_default' => $locale === $default_language,
			);
		}

		$this->languages = $languages;

		return $this->languages;
	}

	/**
	 * @param string $language_slug Language slug.
	 * @return array<string,mixed>|null
	 */
	private function get_language_by_slug( $language_slug ) {
		$languages = $this->get_languages();

		return isset( $languages[ $language_slug ] ) ? $languages[ $language_slug ] : null;
	}

	/**
	 * @return int
	 */
	private function get_sitemap_page_size() {
		$options = $this->get_rank_math_sitemap_options();
		$size    = isset( $options['items_per_page'] ) ? absint( $options['items_per_page'] ) : 300;

		if ( $size < 1 ) {
			$size = 300;
		}

		return $size;
	}

	/**
	 * @return array<int,string>
	 */
	private function get_enabled_post_types() {
		$options       = $this->get_rank_math_sitemap_options();
		$title_options = $this->get_rank_math_title_options();
		$post_types    = array();

		foreach ( $options as $key => $value ) {
			if ( 0 !== strpos( (string) $key, 'pt_' ) || 'on' !== $value || ! $this->string_ends_with( (string) $key, '_sitemap' ) ) {
				continue;
			}

			$post_type = substr( (string) $key, 3, -8 );

			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			$robots_key = 'pt_' . $post_type . '_robots';

			if ( isset( $title_options[ $robots_key ] ) && is_array( $title_options[ $robots_key ] ) && in_array( 'noindex', $title_options[ $robots_key ], true ) ) {
				continue;
			}

			$post_type_object = get_post_type_object( $post_type );

			if ( empty( $post_type_object ) || empty( $post_type_object->public ) ) {
				continue;
			}

			$post_types[] = $post_type;
		}

		return array_values( array_unique( $post_types ) );
	}

	/**
	 * @return array<int,string>
	 */
	private function get_enabled_taxonomies() {
		$options       = $this->get_rank_math_sitemap_options();
		$title_options = $this->get_rank_math_title_options();
		$taxonomies    = array();

		foreach ( $options as $key => $value ) {
			if ( 0 !== strpos( (string) $key, 'tax_' ) || 'on' !== $value || ! $this->string_ends_with( (string) $key, '_sitemap' ) ) {
				continue;
			}

			$taxonomy = substr( (string) $key, 4, -8 );

			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$robots_key = 'tax_' . $taxonomy . '_robots';

			if ( isset( $title_options[ $robots_key ] ) && is_array( $title_options[ $robots_key ] ) && in_array( 'noindex', $title_options[ $robots_key ], true ) ) {
				continue;
			}

			$taxonomy_object = get_taxonomy( $taxonomy );

			if ( empty( $taxonomy_object ) || empty( $taxonomy_object->public ) ) {
				continue;
			}

			$taxonomies[] = $taxonomy;
		}

		return array_values( array_unique( $taxonomies ) );
	}

	/**
	 * @param string $object_name Post type or taxonomy.
	 * @return bool
	 */
	private function is_enabled_object_name( $object_name ) {
		return $this->is_enabled_post_type( $object_name ) || $this->is_enabled_taxonomy( $object_name );
	}

	/**
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private function is_enabled_post_type( $post_type ) {
		return in_array( $post_type, $this->get_enabled_post_types(), true );
	}

	/**
	 * @param string $taxonomy Taxonomy.
	 * @return bool
	 */
	private function is_enabled_taxonomy( $taxonomy ) {
		return in_array( $taxonomy, $this->get_enabled_taxonomies(), true );
	}

	/**
	 * @param string $post_type Post type.
	 * @return int
	 */
	private function get_post_type_count( $post_type ) {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => array( 'publish' ),
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'meta_query'             => $this->get_noindex_exclusion_meta_query(),
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => false,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * @param string $taxonomy Taxonomy.
	 * @return array<int,int>
	 */
	private function get_taxonomy_term_ids( $taxonomy ) {
		if ( null === $this->taxonomy_term_id_cache ) {
			$this->taxonomy_term_id_cache = array();
		}

		if ( isset( $this->taxonomy_term_id_cache[ $taxonomy ] ) ) {
			return $this->taxonomy_term_id_cache[ $taxonomy ];
		}

		$hide_empty = $this->should_hide_empty_terms();
		$term_ids   = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'fields'     => 'ids',
				'hide_empty' => $hide_empty,
				'meta_query' => $this->get_noindex_exclusion_meta_query(),
			)
		);

		if ( is_wp_error( $term_ids ) || ! is_array( $term_ids ) ) {
			$term_ids = array();
		}

		$term_ids = array_map( 'absint', $term_ids );
		sort( $term_ids, SORT_NUMERIC );

		$this->taxonomy_term_id_cache[ $taxonomy ] = $term_ids;

		return $this->taxonomy_term_id_cache[ $taxonomy ];
	}

	/**
	 * @return bool
	 */
	private function should_hide_empty_terms() {
		$title_options = $this->get_rank_math_title_options();

		return isset( $title_options['noindex_empty_taxonomies'] ) && 'on' === $title_options['noindex_empty_taxonomies'];
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	private function get_noindex_exclusion_meta_query() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => 'rank_math_robots',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'rank_math_robots',
				'value'   => 'noindex',
				'compare' => 'NOT LIKE',
			),
		);
	}

	/**
	 * @param string $url Source URL.
	 * @param string $locale TranslatePress locale.
	 * @return string
	 */
	private function translate_url_for_locale( $url, $locale ) {
		if ( ! class_exists( 'TRP_Translate_Press' ) ) {
			return $url;
		}

		$trp = TRP_Translate_Press::get_trp_instance();

		if ( ! $trp || ! method_exists( $trp, 'get_component' ) ) {
			return $url;
		}

		$url_converter = $trp->get_component( 'url_converter' );

		if ( ! $url_converter || ! method_exists( $url_converter, 'get_url_for_language' ) ) {
			return $url;
		}

		return (string) $url_converter->get_url_for_language( $locale, $url, '' );
	}

	/**
	 * @param string $slug Language slug.
	 * @return string
	 */
	private function get_language_index_url( $slug ) {
		return home_url( '/sitemap_index_' . $slug . '.xml' );
	}

	/**
	 * @return string
	 */
	private function get_nitro_warmup_index_url() {
		return home_url( '/nitro-warmup-sitemap.xml' );
	}

	/**
	 * @param string $language_slug Language slug.
	 * @param string $object_name Post type or taxonomy.
	 * @param int    $page Page number.
	 * @return string
	 */
	private function get_language_object_sitemap_url( $language_slug, $object_name, $page ) {
		return home_url( sprintf( '/sitemap_%1$s_%2$s_%3$d.xml', $language_slug, $object_name, $page ) );
	}

	/**
	 * @param array<int,array<string,string>> $entries Sitemap entries.
	 * @return string
	 */
	private function wrap_sitemap_index( $entries ) {
		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

		foreach ( $entries as $entry ) {
			$xml .= "\t<sitemap>\n";
			$xml .= "\t\t<loc>" . $this->xml_escape( $entry['loc'] ) . "</loc>\n";

			if ( ! empty( $entry['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . $this->xml_escape( $entry['lastmod'] ) . "</lastmod>\n";
			}

			$xml .= "\t</sitemap>\n";
		}

		$xml .= "</sitemapindex>\n";

		return $xml;
	}

	/**
	 * @param array<int,array<string,string>> $entries URL entries.
	 * @return string
	 */
	private function wrap_url_set( $entries ) {
		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

		foreach ( $entries as $entry ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . $this->xml_escape( $entry['loc'] ) . "</loc>\n";

			if ( ! empty( $entry['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . $this->xml_escape( $entry['lastmod'] ) . "</lastmod>\n";
			}

			$xml .= "\t</url>\n";
		}

		$xml .= "</urlset>\n";

		return $xml;
	}

	/**
	 * @param string $value XML value.
	 * @return string
	 */
	private function xml_escape( $value ) {
		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
	}

	/**
	 * @param string $cache_fragment Cache fragment.
	 * @return string
	 */
	private function get_cache_key( $cache_fragment ) {
		$version = get_option( self::CACHE_VERSION_OPTION, '1' );

		return 'lsi:' . md5( $version . ':' . $cache_fragment );
	}

	/**
	 * @param string $xml XML body.
	 * @return void
	 */
	private function send_xml_response( $xml ) {
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );
		header( 'Cache-Control: public, max-age=900' );

		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @return void
	 */
	private function render_not_found() {
		status_header( 404 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo "Not found\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @param string $haystack Full string.
	 * @param string $needle Trailing substring.
	 * @return bool
	 */
	private function string_ends_with( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}

		return substr( $haystack, -strlen( $needle ) ) === $needle;
	}
}
