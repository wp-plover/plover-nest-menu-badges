/**
 * Client live-update API for plover Nest admin-menu notification badges.
 *
 * Products call window.ploverNestMenuBadges.setCount( menuSlug, count ) after a
 * state change; this updates that badge and recomputes the top-level total from
 * the DOM (each badge carries its own data-nest-menu-count).
 */
/* eslint-disable no-var -- shipped as-is with no build step, so it stays ES5-compatible */
( function () {
	/**
	 * Update a single badge element's count, in its DOM attribute, class, and visible text.
	 *
	 * @param {Element} el    - The badge element.
	 * @param {number}  count - The new count.
	 */
	function setBadgeCount( el, count ) {
		el.setAttribute( 'data-nest-menu-count', String( count ) );
		var oldClass = ( el.className.match( /count-\d+/ ) || [] )[ 0 ];
		if ( oldClass ) {
			el.className = el.className.replace( oldClass, 'count-' + count );
		}
		var inner = el.querySelector( '.count' );
		if ( inner ) {
			inner.textContent = String( count );
		}
		// Server-rendered zero-count badges ship hidden (inline display:none) so an element
		// exists for this update. Reveal it when the count is positive, hide it again at zero;
		// clearing the inline style hands display back to the stylesheet.
		el.style.display = count > 0 ? '' : 'none';
	}

	/**
	 * Recompute the top-level total badge as the sum of all non-total badge counts.
	 *
	 * WordPress clones the top-level menu title (badge included) into a
	 * `li.wp-submenu-head` flyout header, so more than one element can carry
	 * `data-nest-menu-badge-total="1"`; update all of them, not just the first.
	 */
	function recomputeTotal( topLevelMenuSlug ) {
		if ( ! topLevelMenuSlug ) {
			return;
		}

		var total = 0;
		var badges = document.querySelectorAll( '[data-nest-menu-badge-parent="' + topLevelMenuSlug + '"]' );
		badges.forEach( function ( el ) {
			if ( el.getAttribute( 'data-nest-menu-badge-total' ) === topLevelMenuSlug ) {
				return;
			}
			total += parseInt( el.getAttribute( 'data-nest-menu-count' ), 10 ) || 0;
		} );
		var totalEls = document.querySelectorAll( '[data-nest-menu-badge-total="' + topLevelMenuSlug + '"]' );
		totalEls.forEach( function ( totalEl ) {
			setBadgeCount( totalEl, total );
		} );
	}

	/**
	 * Set the count for the badge matching the given menu slug, then recompute the total.
	 *
	 * @param {string} menuSlug - The menu slug the badge was registered with, i.e. `data-nest-menu-badge`.
	 * @param {number} count    - The new count. Clamped to >= 0.
	 */
	function setCount( menuSlug, count ) {
		count = Math.max( 0, parseInt( count, 10 ) || 0 );
		// Escape the slug so an unusual character can't break the selector; feature-detected since this file ships without a build step.
		var escaped = window.CSS && window.CSS.escape ? window.CSS.escape( menuSlug ) : menuSlug;
		var el = document.querySelector( '[data-nest-menu-badge="' + escaped + '"]' );
		if ( el ) {
			setBadgeCount( el, count );
			recomputeTotal( el.getAttribute('data-nest-menu-badge-parent') );
		}
	}

	window.ploverNestMenuBadges = window.ploverNestMenuBadges || {};
	window.ploverNestMenuBadges.setCount = setCount;
} )();
