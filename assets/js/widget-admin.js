/**
 * Regionssuche im klassischen Widget-Formular.
 * Arbeitet per Event-Delegation, damit auch nachträglich eingefügte Widgets
 * (Customizer, Legacy-Widget-Block) ohne Initialisierung funktionieren.
 */
( function () {
	const { apiFetch, url } = window.wp;

	function field( input ) {
		const root = input.closest( '.wetterwarner-region' );
		return {
			root,
			search: root.querySelector( '.wetterwarner-region-search' ),
			hidden: root.querySelector( '.wetterwarner-region-id' ),
			list: root.querySelector( '.wetterwarner-region-results' ),
		};
	}

	function close( f ) {
		f.list.hidden = true;
		f.list.innerHTML = '';
		f.search.setAttribute( 'aria-expanded', 'false' );
	}

	function choose( f, option ) {
		f.hidden.value = option.dataset.id;
		f.search.value = option.textContent;
		close( f );
		// Widget als geändert markieren, damit "Speichern" aktiv wird.
		[ 'input', 'change' ].forEach( ( type ) =>
			f.hidden.dispatchEvent( new Event( type, { bubbles: true } ) )
		);
	}

	function render( f, regions ) {
		f.list.innerHTML = '';
		regions.forEach( ( region, index ) => {
			const li = document.createElement( 'li' );
			li.id = f.list.id + '-' + index;
			li.setAttribute( 'role', 'option' );
			li.dataset.id = region.id;
			li.textContent = region.label;
			f.list.appendChild( li );
		} );
		f.list.hidden = ! regions.length;
		f.search.setAttribute( 'aria-expanded', regions.length ? 'true' : 'false' );
	}

	document.addEventListener( 'input', ( event ) => {
		if ( ! event.target.matches || ! event.target.matches( '.wetterwarner-region-search' ) ) {
			return;
		}
		const f = field( event.target );
		const term = f.search.value.trim();

		clearTimeout( f.search.wetterwarnerTimer );
		if ( term.length < 2 ) {
			close( f );
			return;
		}
		f.search.wetterwarnerTimer = setTimeout( () => {
			apiFetch( { path: url.addQueryArgs( '/wetterwarner/v1/regions', { search: term } ) } )
				.then( ( regions ) => f.search.value.trim() === term && render( f, regions ) )
				.catch( () => close( f ) );
		}, 250 );
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( ! event.target.matches || ! event.target.matches( '.wetterwarner-region-search' ) ) {
			return;
		}
		const f = field( event.target );
		const options = Array.from( f.list.children );
		const active = f.list.querySelector( '[aria-selected="true"]' );
		let index = options.indexOf( active );

		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();
			if ( ! options.length ) {
				return;
			}
			index = 'ArrowDown' === event.key ? Math.min( index + 1, options.length - 1 ) : Math.max( index - 1, 0 );
			options.forEach( ( o, i ) => o.setAttribute( 'aria-selected', i === index ? 'true' : 'false' ) );
			f.search.setAttribute( 'aria-activedescendant', options[ index ].id );
			options[ index ].scrollIntoView( { block: 'nearest' } );
		} else if ( 'Enter' === event.key && active ) {
			event.preventDefault();
			choose( f, active );
		} else if ( 'Escape' === event.key ) {
			close( f );
		}
	} );

	document.addEventListener( 'mousedown', ( event ) => {
		const option = event.target.closest && event.target.closest( '.wetterwarner-region-results [role="option"]' );
		if ( option ) {
			event.preventDefault();
			choose( field( option ), option );
			return;
		}
		document.querySelectorAll( '.wetterwarner-region-results:not([hidden])' ).forEach( ( list ) => {
			if ( ! list.closest( '.wetterwarner-region' ).contains( event.target ) ) {
				close( field( list ) );
			}
		} );
	} );
}() );
