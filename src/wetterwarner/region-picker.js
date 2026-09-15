import apiFetch from '@wordpress/api-fetch';
import { ComboboxControl } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Suchfeld für DWD-Warnregionen. Die Liste (über 11.000 Regionen) wird
 * serverseitig durchsucht, damit der Editor nicht die komplette Liste lädt.
 *
 * @param {Object}   props
 * @param {string}   props.value    Ausgewählte Warncell-ID.
 * @param {string}   props.label    Beschriftung der Auswahl.
 * @param {Function} props.onChange Callback ( id, label ).
 */
export default function RegionPicker( { value, label, onChange } ) {
	const [ results, setResults ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const request = useRef( 0 );

	const search = useDebounce( ( term ) => {
		if ( term.trim().length < 2 ) {
			return;
		}
		const current = ++request.current;
		setLoading( true );
		apiFetch( { path: addQueryArgs( '/wetterwarner/v1/regions', { search: term } ) } )
			.then( ( regions ) => {
				if ( current === request.current ) {
					setResults( regions.map( ( r ) => ( { value: r.id, label: r.label } ) ) );
				}
			} )
			.catch( () => current === request.current && setResults( [] ) )
			.finally( () => current === request.current && setLoading( false ) );
	}, 250 );

	const options = useMemo( () => {
		if ( value && ! results.some( ( o ) => o.value === value ) ) {
			return [ { value, label: label || value }, ...results ];
		}
		return results;
	}, [ value, label, results ] );

	return (
		<ComboboxControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ __( 'Warning region', 'wetterwarner' ) }
			help={ __( 'Search for a town, district or warncell ID. Search "demo" for sample warnings.', 'wetterwarner' ) }
			value={ value || null }
			options={ options }
			isLoading={ loading }
			onFilterValueChange={ search }
			onChange={ ( id ) => {
				const option = options.find( ( o ) => o.value === id );
				onChange( id || '', option ? option.label : '' );
			} }
		/>
	);
}
