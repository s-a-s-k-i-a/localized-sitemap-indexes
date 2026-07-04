/**
 * Bridges the pre-init dead window of the EDD SL SDK "Manage License"
 * trigger: the SDK binds its click listener only once its footer script has
 * executed, which on heavy plugin screens can take several seconds. Clicks
 * before that are caught here, get the core spinner state as feedback, and
 * are re-dispatched as soon as the SDK modal wrapper exists.
 */
( function () {
	'use strict';

	var SLUG = 'localized-sitemap-indexes';
	var pending = false;

	document.addEventListener(
		'click',
		function ( event ) {
			var trigger = event.target && event.target.closest
				? event.target.closest( '.edd-sdk__notice__trigger[data-slug="' + SLUG + '"]' )
				: null;

			if ( ! trigger || pending ) {
				return;
			}

			// SDK already initialized: its own listener will handle the click.
			if ( document.querySelector( '.edd-sdk__notice__overlay' ) ) {
				return;
			}

			event.preventDefault();
			event.stopImmediatePropagation();
			pending = true;
			trigger.classList.add( 'updating-message' );

			var started = Date.now();
			var timer = window.setInterval( function () {
				var ready = document.querySelector( '.edd-sdk__notice__overlay' );

				if ( ! ready && Date.now() - started < 15000 ) {
					return;
				}

				window.clearInterval( timer );
				trigger.classList.remove( 'updating-message' );
				pending = false;

				if ( ready ) {
					trigger.click();
				}
			}, 200 );
		},
		true
	);
} )();
