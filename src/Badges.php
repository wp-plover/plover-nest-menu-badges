<?php

namespace Plover\Nest\MenuBadges;

/**
 * Wires the notification-count registry into the admin menu and enqueues the client
 * 
 * @see automattic/jetpack-menu-badges
 * @since 1.0.0
 */
class Badges {

    const VERSION = '1.0.0';

    /**
     * Global instance
     * 
     * @var Badges|null
     */
    private static $_instance = null;

	/**
	 * Registered entries, keyed by id.
	 *
	 * @var array<string,array>
	 */
	private $entries = array();

	/**
	 * Memoized result of all(), or null when stale. Invalidated on register()/reset().
	 *
	 * @var array<string,array>|null
	 */
	private $visible_cache = null;

    /**
     * InitInitialize the menu badges.
     */
    private function __construct() {
		// Render badges late, after products have registered their menus and counts.
		add_action( 'admin_menu', array( Renderer::class, 'render' ), 100000 );
		// Client live-update API.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * @return Badges
     */
    public static function init() {
        if ( null === self::$_instance ) {
            self::$_instance = new static();
        }

        return self::$_instance;
    }

    /**
     * Enqueue the vanilla client that owns live badge updates.
     * 
     * @return void
     */
    public function enqueue_scripts() {
		if ( wp_script_is( 'plover-nest-menu-badges', 'enqueued' ) ) {
            return;
        }

		$relative_path  = 'static/js/menu-badges.js';
		$file_path = wp_normalize_path( dirname( __DIR__ ) ) . '/' . $relative_path;
		$script_url = $this->get_url( $relative_path );

		if ( ! $script_url || ! file_exists( $file_path ) ) {
            return;
        }

        wp_enqueue_script(
            'plover-nest-menu-badges',
            $script_url,
            array(),
            self::VERSION,
            array(
                'in_footer' => true
            )
        );
    }

	/**
	 * Get static file url by an relative path
	 * 
	 * @param mixed $relative_path
	 */
	private function get_url( $relative_path ) {
        $relative_path = ltrim( wp_normalize_path( $relative_path ), '/' );

        $package_dir = wp_normalize_path( dirname( __DIR__ ) );
        $full_path   = $package_dir . '/' . $relative_path;

        // The package is located in the plugins directory
        $plugins_dir = wp_normalize_path( WP_PLUGIN_DIR );
        if ( strpos( $full_path, trailingslashit( $plugins_dir ) ) === 0 ) {
            return plugins_url( $relative_path, $package_dir . '/index.php' );
        }

        // The package is located in the directory of the currently active theme or child theme.
        $stylesheet_dir = wp_normalize_path( get_stylesheet_directory() );
        if ( strpos( $full_path, trailingslashit( $stylesheet_dir ) ) === 0 ) {
            $sub_path = ltrim( substr( $full_path, strlen( $stylesheet_dir ) ), '/' );
            return trailingslashit( get_stylesheet_directory_uri() ) . $sub_path;
        }

        // Located in the parent theme directory (when using a child theme)
        $template_dir = wp_normalize_path( get_template_directory() );
        if ( strpos( $full_path, trailingslashit( $template_dir ) ) === 0 ) {
            $sub_path = ltrim( substr( $full_path, strlen( $template_dir ) ), '/' );
            return trailingslashit( get_template_directory_uri() ) . $sub_path;
        }

        // Other directories located under `wp-content` 
		// (such as `mu-plugins` or custom directories)
        $content_dir = wp_normalize_path( WP_CONTENT_DIR );
        if ( strpos( $full_path, trailingslashit( $content_dir ) ) === 0 ) {
            $sub_path = ltrim( substr( $full_path, strlen( $content_dir ) ), '/' );
            return trailingslashit( content_url() ) . $sub_path;
        }

        // Cannot be accessed via URL
        return '';
    }

	/**
	 * Register a notification count for a menu and submenus.
	 *
	 * @param string $parent_menu_slug   Top-Level menu slug (e.g. 'plover-forms').
	 * @param array  $args {
	 *     Al array of submenu notification count entry arguments.
	 *
	 *     @type string|null $menu_slug Submenu item slug to badge; null → top-level only.
	 *     @type int         $count     Magnitude for 'count' entries. Default 0.
	 *     @type string      $type      'count' | 'attention'. Default 'count'.
	 *     @type bool        $is_silent Exclude from totals/display. Default false.
	 * }[]
	 * @return void
	 */
	public function register( $parent_menu_slug, array $submenus ) {
		if ( ! isset( $this->entries[ $parent_menu_slug ] ) ) {
			$this->entries[ $parent_menu_slug ] = array();
		}

		foreach ( $submenus as $args ) {
			$this->entries[ $parent_menu_slug ][] = array(
				'menu_slug' => isset( $args['menu_slug'] ) ? (string) $args['menu_slug'] : null,
				'count'     => isset( $args['count'] ) ? (int) $args['count'] : 0,
				'type'      => ( isset( $args['type'] ) && 'attention' === $args['type'] ) ? 'attention' : 'count',
				'is_silent' => ! empty( $args['is_silent'] ),
			);
		}
	
		$this->visible_cache  = null;
	}

	/**
	 * All non-silent entries, after applying the extension filter.
	 *
	 * @return array<string,array>
	 */
	public function all() {
		if ( null !== $this->visible_cache ) {
			return $this->visible_cache;
		}

		$visible = array();
		foreach ( $this->entries as $top_level => $submenus ) {
			if ( ! isset( $visible[ $top_level ] ) ) {
				$visible[ $top_level ] = array();
			}

			foreach ($submenus as $entry) {
				if ( empty( $entry['is_silent'] ) ) {
					$visible[ $top_level ][] = $entry;
				}
			}
		}

		$this->visible_cache = $visible;
		return $visible;
	}

	/**
	 * Get all non-silent submenu entries by top-level menu slug
	 * 
	 * @param string $top_level
	 * @return array
	 */
	public function get( $top_level ) {
		$entries = $this->all();

		return $entries[ $top_level ] ?? array();
	}
	

	/**
	 * The magnitude an entry contributes to a total: its count, or 1 for attention.
	 *
	 * @param array $entry Entry.
	 * @return int
	 */
	private function magnitude( array $entry ) {
		if ( isset( $entry['type'] ) && 'attention' === $entry['type'] ) {
			return 1;
		}
		return isset( $entry['count'] ) ? (int) $entry['count'] : 0;
	}

	/**
	 * Summed count for a specific submenu item.
	 *
	 * @param string $top_level Top-Level menu slug.
	 * @param string $menu_slug Submenu slug.
	 * @return int
	 */
	public function get_for_menu( $top_level, $menu_slug ) {
		$entries = $this->get( $top_level );

		$total = 0;
		foreach ( $entries as $entry ) {
			if ( isset( $entry['menu_slug'] ) && $entry['menu_slug'] === $menu_slug ) {
				$total += $this->magnitude( $entry );
			}
		}
		return $total;
	}

	/**
	 * Top-level total across all non-silent entries.
	 * 
	 * @param string $top_level Top-Level menu slug.
	 *
	 * @return int
	 */
	public function get_total( $top_level ) {
		$total = 0;
		foreach ( $this->get( $top_level ) as $entry ) {
			$total += $this->magnitude( $entry );
		}
		return $total;
	}
}
