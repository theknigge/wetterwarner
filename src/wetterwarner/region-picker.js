import apiFetch from '@wordpress/api-fetch';
import { Button, SearchControl, Spinner } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Suchfeld für DWD-Warnregionen. Die Liste (über 11.000 Regionen) wird
 * serverseitig durchsucht, damit der Editor nicht die komplette Liste lädt.
 *
 * Bewusst kein ComboboxControl: Das filtert die Treffer im Browser noch einmal
 * nach ihrer Beschriftung und blendet so Suchen nach Warncell-ID oder in
 * Umschrift ("Muenchen") aus, obwohl der Server passende Regionen liefert.
 *
 * @param {Object}   props
 * @param {string}   props.value    Ausgewählte Warncell-ID.
 * @param {string}   props.label    Beschriftung der Auswahl.
 * @param {Function} props.onChange Callback ( id, label ).
 */
export default function RegionPicker( { value, label, onChange } ) {
	const [ term, setTerm ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ loading, setLoading ] = useState( false );
	const request = useRef( 0 );

	const search = useDebounce( ( text ) => {
		const current = ++request.current;
		setLoading( true );
		apiFetch( {
			path: addQueryArgs( '/wetterwarner/v1/regions', { search: text } ),
		} )
			.then( ( regions ) => {
				if ( current === request.current ) {
					setResults( Array.isArray( regions ) ? regions : [] );
					setError( '' );
				}
			} )
			.catch( ( err ) => {
				if ( current === request.current ) {
					setResults( null );
					// Die Meldung kommt bereits übersetzt von WordPress bzw. aus der PHP-Antwort.
					setError(
						err && err.message ? err.message : String( err )
					);
				}
			} )
			.finally(
				() => current === request.current && setLoading( false )
			);
	}, 250 );

	useEffect( () => {
		if ( term.trim().length < 2 ) {
			// Laufende Anfrage verwerfen.
			request.current++;
			search.cancel();
			setResults( null );
			setError( '' );
			setLoading( false );
			return;
		}
		search( term.trim() );
	}, [ term ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const choose = ( region ) => {
		onChange( region.id, region.label );
		setTerm( '' );
	};

	let status = null;
	if ( loading ) {
		status = <Spinner />;
	} else if ( error ) {
		status = (
			<p
				className="wetterwarner-region-picker__error"
				style={ { color: '#cc1818' } }
			>
				{ error }
			</p>
		);
	}

	return (
		<div className="wetterwarner-region-picker">
			{ value && (
				<p
					className="wetterwarner-region-picker__current"
					style={ { margin: '0 0 8px' } }
				>
					<strong>{ label || value }</strong>
					{ label && value !== 'demo' && <> ({ value })</> }
				</p>
			) }
			<SearchControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Warning region', 'wetterwarner' ) }
				hideLabelFromVision={ false }
				help={ __(
					'Search for a town, district or warncell ID. Search "demo" for sample warnings.',
					'wetterwarner'
				) }
				value={ term }
				onChange={ setTerm }
			/>
			<div aria-live="polite">
				{ status }
				{ ! loading && results && results.length > 0 && (
					<ul
						className="wetterwarner-region-picker__results"
						style={ {
							margin: '8px 0 0',
							padding: 0,
							listStyle: 'none',
						} }
					>
						{ results.map( ( region ) => (
							<li key={ region.id } style={ { margin: 0 } }>
								<Button
									__next40pxDefaultSize
									variant="tertiary"
									isPressed={ region.id === value }
									onClick={ () => choose( region ) }
									style={ {
										width: '100%',
										height: 'auto',
										minHeight: 40,
										textAlign: 'left',
										whiteSpace: 'normal',
									} }
								>
									{ region.label }
								</Button>
							</li>
						) ) }
					</ul>
				) }
			</div>
		</div>
	);
}
