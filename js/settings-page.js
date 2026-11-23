/**
 * Settings page tabs and login button preview.
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

	var preview = wrap.querySelector( '.oidc-login-preview' );
	if ( ! preview ) {
		return;
	}

	var urls = JSON.parse( preview.getAttribute( 'data-oidc-preview-urls' ) );
	var notes = JSON.parse( preview.getAttribute( 'data-oidc-preview-notes' ) );
	var settingsVar = preview.getAttribute( 'data-oidc-preview-settings-var' );
	var frame = preview.querySelector( 'iframe' );
	var note = preview.querySelector( '.oidc-login-preview-note' );
	var autoCover = preview.querySelector( '.oidc-login-preview-auto' );
	var viewButtons = preview.querySelectorAll( '[data-oidc-preview-view]' );
	var fields = [ 'login_type', 'login_button_text', 'login_button_icon', 'woocommerce_login' ];
	// The setting each view's note depends on.
	var noteFields = { login: 'login_type', myaccount: 'woocommerce_login' };
	var view = viewButtons.length ? viewButtons[ 0 ].getAttribute( 'data-oidc-preview-view' ) : '';
	var timer;

	// Light or dark: start with the admin's own device setting.
	var schemeButtons = preview.querySelectorAll( '[data-oidc-preview-scheme]' );
	var scheme = window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light';
	// Whether the page in the frame has a dark mode, or null until it has loaded.
	var hasDarkMode = null;
	var originalMedia = new WeakMap();
	var schemeQuery = /\(\s*prefers-color-scheme\s*:\s*(light|dark)\s*\)/g;

	/**
	 * Turn the page's own prefers-color-scheme rules on or off for the chosen scheme.
	 * A frame always follows the device, so the rules are rewritten in the page instead.
	 *
	 * @param {MediaList} media A media list from the page's styles.
	 */
	function setSchemeMedia( media ) {
		if ( ! originalMedia.has( media ) ) {
			originalMedia.set( media, media.mediaText );
		}
		var text = originalMedia.get( media );
		if ( text.search( schemeQuery ) === -1 ) {
			return;
		}
		hasDarkMode = true;
		media.mediaText = text.replace( schemeQuery, function ( match, wanted ) {
			// Always true, or never true.
			return wanted === scheme ? '(min-width: 0px)' : '(min-width: 999999px)';
		} );
	}

	function setSchemeRules( rules ) {
		Array.prototype.forEach.call( rules, function ( rule ) {
			if ( rule.media ) {
				setSchemeMedia( rule.media );
			}
			if ( rule.styleSheet ) {
				setSchemeSheet( rule.styleSheet );
			}
			if ( rule.cssRules ) {
				setSchemeRules( rule.cssRules );
			}
		} );
	}

	function setSchemeSheet( sheet ) {
		setSchemeMedia( sheet.media );
		try {
			setSchemeRules( sheet.cssRules );
		} catch ( error ) {
			// Styles from other sites can't be read.
		}
	}

	function applyScheme() {
		var doc = frame.contentDocument;
		hasDarkMode = false;
		if ( doc ) {
			Array.prototype.forEach.call( doc.styleSheets, setSchemeSheet );
		}
		schemeButtons.forEach( function ( button ) {
			button.disabled = ! hasDarkMode;
			button.setAttribute( 'aria-pressed', hasDarkMode && button.getAttribute( 'data-oidc-preview-scheme' ) === scheme ? 'true' : 'false' );
		} );
		updateNote();
	}

	schemeButtons.forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			scheme = button.getAttribute( 'data-oidc-preview-scheme' );
			applyScheme();
		} );
	} );

	frame.addEventListener( 'load', applyScheme );

	function fieldValue( key ) {
		var field = document.getElementById( key );
		return field ? field.value : '';
	}

	function update() {
		if ( ! urls[ view ] ) {
			return;
		}

		var url = new URL( urls[ view ], window.location.href );
		fields.forEach( function ( key ) {
			url.searchParams.set( settingsVar + '[' + key + ']', fieldValue( key ) );
		} );
		// A text field's change event follows its last input, so don't reload for nothing.
		if ( frame.src !== url.toString() ) {
			hasDarkMode = null;
			frame.src = url.toString();
		}

		viewButtons.forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', button.getAttribute( 'data-oidc-preview-view' ) === view ? 'true' : 'false' );
		} );

		updateNote();
	}

	function updateNote() {
		var viewNotes = notes[ view ] || {};
		var value = noteFields[ view ] ? fieldValue( noteFields[ view ] ) : '';
		var text = viewNotes[ value ] || '';

		// With Auto Login, cover the page with why visitors don't see it.
		var auto = 'auto' === value;
		autoCover.hidden = ! auto;
		if ( auto ) {
			autoCover.querySelector( 'strong' ).textContent = notes.auto;
			autoCover.querySelector( 'p' ).textContent = text;
			text = '';
		}

		if ( false === hasDarkMode ) {
			text += ' ' + notes.no_dark;
		}
		note.textContent = text.trim();
	}

	viewButtons.forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			view = button.getAttribute( 'data-oidc-preview-view' );
			update();
		} );
	} );

	fields.forEach( function ( key ) {
		var field = document.getElementById( key );
		if ( ! field ) {
			return;
		}
		// Wait for a pause in typing. Selects update as soon as they change.
		if ( 'INPUT' === field.tagName ) {
			field.addEventListener( 'input', function () {
				clearTimeout( timer );
				timer = setTimeout( update, 500 );
			} );
		}
		field.addEventListener( 'change', function () {
			clearTimeout( timer );
			update();
		} );
	} );

	update();
}() );
