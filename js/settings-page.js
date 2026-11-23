/**
 * Settings page tabs.
 *
 * Without this script every section shows on one page, as before.
 */
( function () {
	var wrap = document.querySelector( '.oidc-settings' );
	if ( ! wrap ) {
		return;
	}

	var tabLinks = wrap.querySelectorAll( '[data-oidc-tab]' );
	var panels = wrap.querySelectorAll( '[data-oidc-tab-panel]' );
	var currentTab = '';

	function showTab( tab ) {
		currentTab = tab;
		tabLinks.forEach( function ( link ) {
			var active = link.getAttribute( 'data-oidc-tab' ) === tab;
			link.classList.toggle( 'nav-tab-active', active );
			if ( active ) {
				link.setAttribute( 'aria-current', 'page' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
		panels.forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-oidc-tab-panel' ) !== tab;
		} );
	}

	function showTabFromHash() {
		var tab = window.location.hash.slice( 1 );
		var exists = tab && wrap.querySelector( '[data-oidc-tab="' + CSS.escape( tab ) + '"]' );
		showTab( exists ? tab : tabLinks[ 0 ].getAttribute( 'data-oidc-tab' ) );
	}

	showTabFromHash();
	window.addEventListener( 'hashchange', showTabFromHash );

	tabLinks.forEach( function ( link ) {
		link.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			showTab( link.getAttribute( 'data-oidc-tab' ) );
			history.replaceState( null, '', '#' + currentTab );
		} );
	} );

	// Come back to the same tab after saving: the redirect after a form post keeps the URL's #tab.
	wrap.querySelectorAll( 'form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function () {
			// The settings form has a field named "action", so form.action isn't the URL.
			var action = form.getAttribute( 'action' ) || '';
			form.setAttribute( 'action', action.split( '#' )[ 0 ] + '#' + currentTab );
		} );
	} );
}() );
