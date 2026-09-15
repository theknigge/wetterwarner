/**
 * Regionssuche im klassischen Widget-Formular.
 * Arbeitet per Event-Delegation, damit auch nachträglich eingefügte Widgets
 * (Customizer, Legacy-Widget-Block) ohne Initialisierung funktionieren.
 */
( function () {
	const { apiFetch, url } = window.wp;

	/**
	 * Liefert die zusammengehörenden Felder oder null, falls das Formular
	 * unvollständig ist.
	 */
	function field( element ) {
		const root = element.closest( '.wetterwarner-region' );
		const search = root && root.querySelector( '.wetterwarner-region-search' );
		const hidden = root && root.querySelector( '.wetterwarner-region-id' );
		const list = root && root.querySelector( '.wetterwarner-region-results' );
		return search && hidden && list ? { root, search, hidden, list } : null;
	}

	function close( f ) {
		f.list.hidden = true;
		f.list.innerHTML = '';
		f.search.setAttribute( 'aria-expanded', 'false' );
		f.search.removeAttribute( 'aria-activedescendant' );
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
			li.setAttribute( 'aria-selected', 'false' );
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
		if ( ! f ) {
			return;
		}
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
		if ( ! f ) {
			return;
		}
		const options = Array.from( f.list.children );
		const active = f.list.querySelector( '[aria-selected="true"]' );
		let index = options.indexOf( active );

		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();
			if ( ! options.length ) {
				return;
			}
			index = 'ArrowDown' === event.key ? Math.min( index + 1, options.length - 1 ) : Math.max( index - 1, 0 );
			options.forEach( ( option, i ) => option.setAttribute( 'aria-selected', i === index ? 'true' : 'false' ) );
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
		const target = event.target;
		if ( ! target.closest ) {
			return;
		}

		const option = target.closest( '.wetterwarner-region-results [role="option"]' );
		if ( option ) {
			const f = field( option );
			if ( f ) {
				// Verhindert, dass das Suchfeld den Fokus verliert, bevor die Auswahl greift.
				event.preventDefault();
				choose( f, option );
			}
			return;
		}

		document.querySelectorAll( '.wetterwarner-region-results:not([hidden])' ).forEach( ( list ) => {
			const f = field( list );
			if ( f && ! f.root.contains( target ) ) {
				close( f );
			}
		} );
	} );
}() );
