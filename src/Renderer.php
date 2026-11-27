<?php

namespace Plover\Nest\MenuBadges;

/**
 * Renders badges into the wp-admin $menu/$submenu globals.
 * 
 * @since 1.0.0
 */
class Renderer {
    
	/**
	 * Render badges into the current $menu/$submenu globals.
	 *
	 * @return void
	 */
	public static function render() {
		global $menu, $submenu;

		if ( ! is_array( $menu ) ) {
			return;
		}

        $badges = Badges::init();
        $entries = $badges->all();

		foreach ($entries as $top_level => $submenus) {
			// Submenu badges: one per registered menu_slug. A registered slug always gets a
			// badge span — even at count 0 — so a later client live-update (0 -> positive)
			// has an element to reveal. badge_markup() ships the zero case hidden.
			if ( isset( $submenu[$top_level] ) && is_array( $submenu[$top_level] ) ) {
				foreach ( $submenu[$top_level] as $i => $item ) {
					// 2 - submenu slug
					// 0 - submenu title
					if ( ! isset( $item[2] ) || ! isset( $item[0] ) ) {
						continue;
					}
					$slug  = $item[2];
					$title = self::strip( $item[0] );
					if ( self::has_registered_slug( $entries[ $top_level ], $slug ) ) {
						$title .= self::badge_markup( $top_level, $slug, $badges->get_for_menu( $top_level, $slug ) );
					}
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$submenu[$top_level][ $i ][0] = $title;
				}
			}

			// Top-level total on the plover-framework parent. Rendered even at 0 (hidden) so a live
			// update can reveal it without a reload.
			$parent_index = self::find_parent_index( $top_level, $menu );
			if ( null === $parent_index ) {
				return;
			}
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$menu[ $parent_index ][0] = self::strip( $menu[ $parent_index ][0] ) . self::badge_markup( '', $top_level, $badges->get_total( $top_level ), true );
		}
    }

	/**
	 * Strip any badge this renderer previously wrote from a menu title (idempotency).
	 *
	 * @param string $title Menu title.
	 * @return string
	 */
	private static function strip( $title ) {
		return trim( (string) preg_replace( '/\s*<span class="[^"]*menu-counter count-\d+" data-nest-menu-badge=.*$/s', '', (string) $title ) );
	}

	/**
	 * Build a single badge span.
	 *
	 * @param string $top_level 	Top-Level menu slug or empty.
	 * @param string $slug       	Submenu Slug.
	 * @param int    $count    		Count to show.
	 * @param bool   $is_total 		Whether this is the top-level total badge.
	 * @return string
	 */
	public static function badge_markup( $top_level, $slug, $count, $is_total = false ) {
		$count = (int) $count;
		// A zero-count badge still renders (so client live-updates have an element to
		// target) but ships hidden. The inline style trails the data-nest-* attributes so
		// strip()'s idempotency regex still matches; setBadgeCount() clears it to reveal.
		$hidden = $count > 0 ? '' : ' style="display:none"';
		// The top-level plover-framework total badge carries the core `awaiting-mod` and
		// `update-plugins` classes so it picks up WordPress's standard menu-bubble styling;
		// submenu badges use only our own `menu-counter` hook. `menu-counter` is always
		// present for CSS/JS targeting.
		$classes = $is_total ? 'awaiting-mod update-plugins menu-counter' : 'menu-counter';
		$attrs   = sprintf(
			'class="%1$s count-%2$d" data-nest-menu-badge="%3$s" data-nest-menu-count="%2$d"%4$s%5$s%6$s',
			$classes,
			$count,
			esc_attr( $is_total ? $slug . '_total' : $slug ),
			$is_total ? ' data-nest-menu-badge-total="' . esc_attr( $slug ) . '"' : '',
			$top_level ? ' data-nest-menu-badge-parent="' . esc_attr(  $top_level ) . '"' : '',
			$hidden
		);
		return sprintf(
			' <span %1$s><span class="count">%2$s</span></span>',
			$attrs,
			number_format_i18n( $count )
		);
	}

	/**
	 * Whether any registered entry badges the given submenu slug.
	 *
	 * @param array<string,array> $entries Registry entries (from Badges::all()).
	 * @param string              $slug    Submenu slug.
	 * @return bool
	 */
	private static function has_registered_slug( array $entries, $slug ) {
		foreach ( $entries as $entry ) {
			if ( isset( $entry['menu_slug'] ) && $entry['menu_slug'] === $slug ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Locate the Top-level menu row to carry the total badge.
	 *
	 * @param string $top_level The top-level menu slug.
	 * @param array $menu The wp-admin $menu global.
	 * @return int|string|null Matching key, or null when no parent is present.
	 */
	private static function find_parent_index( $top_level, array $menu ) {
		foreach ( $menu as $i => $item ) {
			if ( isset( $item[0] ) && isset( $item[2] ) && $top_level === $item[2] ) {
				return $i;
			}
		}
		return null;
	}
}
