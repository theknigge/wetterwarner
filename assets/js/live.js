/**
 * Frontend ohne jQuery: Warnungen nachladen (auch auf gecachten Seiten) und
 * das Warnbanner schließen.
 */
( function () {
	const config = window.wetterwarnerLive || null;
	const SELECTOR = '[data-ww-live]';
	const STORAGE = 'wetterwarnerBannerClosed';
	const INTERVAL = 5 * 60 * 1000;
	let last = 0;

	function closedKey() {
		try {
			return window.localStorage.getItem( STORAGE );
		} catch ( e ) {
			return null;
		}
	}

	/** Ein geschlossenes Banner bleibt zu, bis sich die Warnungen ändern. */
	function applyBanner() {
		const closed = closedKey();
		document.querySelectorAll( '.wetterwarner-banner[data-ww-key]' ).forEach( ( banner ) => {
			if ( banner.dataset.wwKey === closed ) {
				banner.hidden = true;
			}
		} );
	}

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest && event.target.closest( '.wetterwarner-banner__close' );
		if ( ! button ) {
			return;
		}
		const banner = button.closest( '.wetterwarner-banner' );
		banner.hidden = true;
		try {
			window.localStorage.setItem( STORAGE, banner.dataset.wwKey );
		} catch ( e ) {}
	} );

	function openDetails( element ) {
		return Array.from( element.querySelectorAll( '.wetterwarner__details[open]' ) ).map( ( d ) => d.parentElement.textContent );
	}

	function placeholder( source ) {
		const empty = document.createElement( 'div' );
		empty.hidden = true;
		empty.dataset.wwEmpty = '1';
		[ 'data-ww-live', 'data-ww-sig', 'data-ww-time', 'data-ww-floating' ].forEach( ( name ) => {
			if ( source.hasAttribute( name ) ) {
				empty.setAttribute( name, source.getAttribute( name ) );
			}
		} );
		return empty;
	}

	function replace( element, result ) {
		const open = openDetails( element );
		const template = document.createElement( 'template' );
		// Nur beim ersten Erscheinen kommt der Widget-Rahmen des Themes dazu.
		template.innerHTML = element.dataset.wwEmpty && result.html ? result.before + result.html + result.after : result.html;

		if ( ! template.content.querySelector( SELECTOR ) ) {
			if ( ! element.dataset.wwEmpty ) {
				element.replaceWith( placeholder( element ) );
			}
			return;
		}
		template.content.querySelectorAll( '.wetterwarner__item' ).forEach( ( item ) => {
			const details = item.querySelector( '.wetterwarner__details' );
			if ( details && open.includes( item.textContent ) ) {
				details.open = true;
			}
		} );
		const banner = element.dataset.wwFloating && template.content.querySelector( '.wetterwarner-banner' );
		if ( banner ) {
			banner.classList.add( 'wetterwarner-banner--floating' );
			banner.dataset.wwFloating = '1';
		}
		element.replaceWith( template.content );
	}

	/** Ohne Verbindung keine veralteten Warnungen stehen lassen. */
	function expire( element ) {
		const time = Number( element.dataset.wwTime );
		if ( element.dataset.wwEmpty || ! time || Date.now() / 1000 - time <= config.maxStale ) {
			return;
		}
		if ( element.classList.contains( 'wetterwarner-banner' ) ) {
			element.hidden = true;
			return;
		}
		if ( element.querySelector( '.wetterwarner__error' ) ) {
			return;
		}
		element.querySelectorAll( '.wetterwarner__intro, .wetterwarner__list, .wetterwarner__none, .wetterwarner__map' ).forEach( ( node ) => node.remove() );
		const error = document.createElement( 'p' );
		error.className = 'wetterwarner__error';
		const link = document.createElement( 'a' );
		link.href = config.dwdUrl;
		link.target = '_blank';
		link.rel = 'noopener';
		link.textContent = config.link;
		error.append( config.error + ' ', link );
		element.insertBefore( error, element.querySelector( '.wetterwarner__source' ) );
	}

	function update() {
		if ( ! config || document.hidden || Date.now() - last < 60000 ) {
			return;
		}
		last = Date.now();
		document.querySelectorAll( SELECTOR ).forEach( ( element ) => {
			const url = new URL( config.url, window.location.href );
			url.searchParams.set( 'w', element.dataset.wwLive );
			url.searchParams.set( 's', element.dataset.wwSig );
			window
				.fetch( url, { credentials: 'same-origin' } )
				.then( ( response ) => ( response.ok ? response.json() : Promise.reject( response.status ) ) )
				.then( ( result ) => {
					replace( element, result );
					applyBanner();
				} )
				.catch( () => expire( element ) );
		} );
	}

	applyBanner();
	update();
	window.setInterval( update, INTERVAL );
	document.addEventListener( 'visibilitychange', update );
}() );
